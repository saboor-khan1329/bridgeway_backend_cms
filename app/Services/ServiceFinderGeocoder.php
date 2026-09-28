<?php

namespace App\Services;

use App\Models\FinderGeocode;
use App\Support\ServiceFinderSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Resolves UK postcodes to coordinates as cheaply as possible.
 *
 * Order of attack, stopping at the first hit:
 *   1. finder_geocodes  — permanent local cache, including negative results
 *   2. postcodes.io bulk — free OS OpenData, 100 postcodes per request
 *   3. postcodes.io outcode centroid — for partial/terminated postcodes
 *   4. Google Geocoding — paid, and only ever reached by a handful of rows
 *
 * Aggregated area pins need outcode-level precision at most, so the paid
 * step is a genuine last resort rather than a routine cost.
 */
class ServiceFinderGeocoder
{
    /** @var array{postcodes_io:int, postcodes_io_outcode:int, google:int, cache:int} */
    protected array $stats = [
        'cache' => 0,
        'postcodes_io' => 0,
        'postcodes_io_terminated' => 0,
        'postcodes_io_outcode' => 0,
        'google' => 0,
        'failed' => 0,
    ];

    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * Resolve many postcodes at once.
     *
     * @param  array<int, string>  $postcodes  normalised full postcodes
     * @param  array<int, string>  $outcodes   bare outcodes ("HA7")
     * @return array<string, FinderGeocode>    keyed by the lookup string
     */
    public function resolveMany(array $postcodes, array $outcodes = []): array
    {
        $resolved = [];

        $postcodes = $this->unique($postcodes);
        $outcodes = $this->unique($outcodes);

        // 1. Cache sweep for everything up front.
        $cached = FinderGeocode::query()
            ->whereIn('postcode', array_merge($postcodes, $outcodes))
            ->get()
            ->keyBy('postcode');

        foreach ($cached as $key => $row) {
            // A miss with no recorded source means no provider ever actually
            // answered — typically a network outage — so it is retried rather
            // than trusted. Confirmed misses carry a source and are final.
            if (! $row->isResolved() && $row->source === null) {
                continue;
            }

            $resolved[$key] = $row;
            $this->stats['cache']++;
        }

        $pendingPostcodes = array_values(array_diff($postcodes, array_keys($resolved)));
        $pendingOutcodes = array_values(array_diff($outcodes, array_keys($resolved)));

        // 2. Bulk full-postcode lookups.
        foreach (array_chunk($pendingPostcodes, $this->chunkSize()) as $chunk) {
            foreach ($this->bulkLookup($chunk) as $key => $row) {
                $resolved[$key] = $row;
            }

            // Fair-use pacing for a free public service.
            usleep(150000);
        }

        // 3. Anything the bulk call could not resolve falls back to its
        //    outcode centroid, which is ample for an aggregated area pin.
        foreach ($pendingPostcodes as $postcode) {
            if (isset($resolved[$postcode]) && $resolved[$postcode]->isResolved()) {
                continue;
            }

            $outcode = $this->outcodeOf($postcode);

            if ($outcode !== null && ! in_array($outcode, $pendingOutcodes, true)) {
                $pendingOutcodes[] = $outcode;
            }
        }

        foreach ($pendingOutcodes as $outcode) {
            $row = $this->outcodeLookup($outcode);

            if ($row) {
                $resolved[$outcode] = $row;
            }
        }

        return $resolved;
    }

    /** Resolve a single postcode or outcode, cache included. */
    public function resolve(string $lookup): ?FinderGeocode
    {
        $lookup = strtoupper(trim($lookup));

        if ($lookup === '') {
            return null;
        }

        $cached = FinderGeocode::query()->where('postcode', $lookup)->first();

        if ($cached) {
            $this->stats['cache']++;

            return $cached;
        }

        if (str_contains($lookup, ' ')) {
            $found = $this->bulkLookup([$lookup]);

            if (isset($found[$lookup]) && $found[$lookup]->isResolved()) {
                return $found[$lookup];
            }

            $outcode = $this->outcodeOf($lookup);

            return $outcode ? $this->outcodeLookup($outcode) : null;
        }

        return $this->outcodeLookup($lookup);
    }

    /**
     * postcodes.io bulk endpoint. Returns HTTP 200 even when individual
     * postcodes are unknown (their "result" is null), so each entry is
     * checked rather than trusting the envelope status.
     *
     * @param  array<int, string>  $chunk
     * @return array<string, FinderGeocode>
     */
    protected function bulkLookup(array $chunk): array
    {
        if ($chunk === []) {
            return [];
        }

        $out = [];

        try {
            $response = $this->client()->post($this->baseUrl().'/postcodes', ['postcodes' => $chunk]);

            $results = (array) data_get($response->json(), 'result', []);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        foreach ($results as $entry) {
            $query = strtoupper(trim((string) data_get($entry, 'query', '')));
            $result = data_get($entry, 'result');

            if ($query === '') {
                continue;
            }

            if (! is_array($result) || data_get($result, 'latitude') === null) {
                // Live postcodes.io does not know it. Many of these are simply
                // retired (Apple Birmingham's B2 4DB was withdrawn in 2019),
                // and the terminated register still holds exact coordinates.
                $terminated = $this->terminatedLookup($query);

                $out[$query] = $terminated ?: $this->store(
                    $query, null, null, $this->areaOf($query), null,
                    FinderGeocode::STATUS_NOT_FOUND, FinderGeocode::SOURCE_POSTCODES_IO,
                );

                continue;
            }

            $this->stats['postcodes_io']++;

            $out[$query] = $this->store(
                $query,
                (float) data_get($result, 'latitude'),
                (float) data_get($result, 'longitude'),
                $this->areaOf((string) data_get($result, 'outcode', $query)),
                (string) data_get($result, 'region', '') ?: (string) data_get($result, 'admin_district', ''),
                FinderGeocode::STATUS_OK,
                FinderGeocode::SOURCE_POSTCODES_IO,
            );
        }

        return $out;
    }

    /**
     * Retired postcodes remain in the terminated register with their original
     * coordinates. Long-standing client sites frequently sit on one, so this
     * free step resolves them before any paid lookup is considered.
     */
    protected function terminatedLookup(string $postcode): ?FinderGeocode
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl().'/terminated_postcodes/'.rawurlencode($postcode));

            $result = $response->successful() ? data_get($response->json(), 'result') : null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! is_array($result) || data_get($result, 'latitude') === null) {
            return null;
        }

        $this->stats['postcodes_io_terminated']++;

        return $this->store(
            strtoupper($postcode),
            (float) data_get($result, 'latitude'),
            (float) data_get($result, 'longitude'),
            $this->areaOf($postcode),
            null,
            FinderGeocode::STATUS_OK,
            FinderGeocode::SOURCE_POSTCODES_IO_TERMINATED,
        );
    }

    /**
     * Outcode centroid. Terminated or unknown outcodes 404 here, in which
     * case Google is asked before giving up.
     */
    protected function outcodeLookup(string $outcode): ?FinderGeocode
    {
        $outcode = strtoupper(trim($outcode));

        if ($outcode === '') {
            return null;
        }

        $existing = FinderGeocode::query()->where('postcode', $outcode)->first();

        if ($existing) {
            $this->stats['cache']++;

            return $existing->isResolved() ? $existing : $this->googleLookup($outcode, $existing);
        }

        try {
            $response = $this->client()->get($this->baseUrl().'/outcodes/'.rawurlencode($outcode));

            $result = $response->successful() ? data_get($response->json(), 'result') : null;
        } catch (Throwable $exception) {
            report($exception);
            $result = null;
        }

        if (is_array($result) && data_get($result, 'latitude') !== null) {
            $this->stats['postcodes_io_outcode']++;

            return $this->store(
                $outcode,
                (float) data_get($result, 'latitude'),
                (float) data_get($result, 'longitude'),
                $this->areaOf($outcode),
                (string) data_get($result, 'region', ''),
                FinderGeocode::STATUS_OK,
                FinderGeocode::SOURCE_POSTCODES_IO_OUTCODE,
            );
        }

        return $this->googleLookup($outcode);
    }

    /**
     * Paid fallback, restricted to GB postal codes so a stray match in
     * another country can never be accepted.
     */
    protected function googleLookup(string $lookup, ?FinderGeocode $existing = null): ?FinderGeocode
    {
        $key = (string) config('service_finder.geocoding.google_api_key', '');
        $enabled = (bool) config('service_finder.geocoding.google_fallback_enabled', true)
            && ServiceFinderSettings::get('import_google_fallback');

        if (! $enabled || $key === '') {
            $this->stats['failed']++;

            return $existing ?: $this->store($lookup, null, null, $this->areaOf($lookup), null, FinderGeocode::STATUS_NOT_FOUND, null);
        }

        try {
            $response = $this->client()->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'components' => 'postal_code:'.$lookup.'|country:GB',
                    'key' => $key,
                ]);

            $result = data_get($response->json(), 'results.0');
        } catch (Throwable $exception) {
            report($exception);
            $result = null;
        }

        if (! is_array($result) || data_get($result, 'geometry.location.lat') === null) {
            $this->stats['failed']++;

            $row = $existing ?: new FinderGeocode(['postcode' => $lookup]);
            $row->fill([
                'status' => FinderGeocode::STATUS_NOT_FOUND,
                'postcode_area' => $this->areaOf($lookup),
            ])->save();

            return $row;
        }

        $this->stats['google']++;

        return $this->store(
            $lookup,
            (float) data_get($result, 'geometry.location.lat'),
            (float) data_get($result, 'geometry.location.lng'),
            $this->areaOf($lookup),
            null,
            FinderGeocode::STATUS_OK,
            FinderGeocode::SOURCE_GOOGLE,
        );
    }

    protected function store(
        string $postcode,
        ?float $lat,
        ?float $lng,
        ?string $area,
        ?string $region,
        string $status,
        ?string $source,
    ): FinderGeocode {
        return FinderGeocode::query()->updateOrCreate(
            ['postcode' => $postcode],
            [
                'latitude' => $lat,
                'longitude' => $lng,
                'postcode_area' => $area,
                'region' => $region ?: null,
                'status' => $status,
                'source' => $source,
            ]
        );
    }

    /** "SW15 2SU" => "SW15" */
    protected function outcodeOf(string $postcode): ?string
    {
        $postcode = strtoupper(trim($postcode));

        if (preg_match('/^([A-Z]{1,2}\d[A-Z\d]?)\s*\d[A-Z]{2}$/', $postcode, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^([A-Z]{1,2}\d[A-Z\d]?)$/', $postcode, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /** "SW15" => "SW" */
    protected function areaOf(string $value): ?string
    {
        return preg_match('/^([A-Z]{1,2})/', strtoupper(trim($value)), $matches)
            ? $matches[1]
            : null;
    }

    /** @param array<int, string> $values */
    protected function unique(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => strtoupper(trim((string) $value)),
            $values
        ))));
    }

    /**
     * Outbound HTTP client for every provider call.
     *
     * Honours an optional CA bundle override: some local PHP builds ship a
     * curl.cainfo path that no longer exists, which fails every HTTPS request
     * with cURL error 77. Verification itself is never disabled.
     */
    protected function client(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout($this->timeout())
            ->retry(2, 400, throw: false);

        $bundle = (string) config('service_finder.geocoding.ca_bundle', '');

        if ($bundle !== '' && is_file($bundle)) {
            $request = $request->withOptions(['verify' => $bundle]);
        }

        return $request;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('service_finder.geocoding.postcodes_io_url', 'https://api.postcodes.io'), '/');
    }

    protected function chunkSize(): int
    {
        return max(1, min(100, (int) config('service_finder.geocoding.bulk_chunk_size', 100)));
    }

    protected function timeout(): int
    {
        return max(3, (int) config('service_finder.geocoding.timeout', 15));
    }
}
