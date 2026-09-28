<?php

namespace App\Services;

use App\Models\FinderGeocode;
use App\Models\FinderSite;
use App\Models\Service;
use App\Support\ServiceFinderSettings;
use App\Support\UkPostcode;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bootstraps coverage from an operations site export.
 *
 * The CSV is reference data, not the source of truth: it seeds areas and
 * gives each one a realistic site count so an operator starts from something
 * real instead of a blank map. What the finder actually advertises is then
 * decided in the admin coverage editor, and nothing an operator authors is
 * ever overwritten by a later import.
 *
 * Nothing is discarded. Rows that cannot be placed on a UK map — foreign
 * offices, test sites, unmapped service types, unparseable postcodes — are
 * stored inactive with a reason, so they can be inspected or re-enabled.
 */
class FinderSiteImporter
{
    public function __construct(
        protected ServiceFinderGeocoder $geocoder,
        protected FinderRecountService $recount,
    ) {
    }

    /** @var array<string, int> */
    protected array $report = [];

    /**
     * @param  array{dry_run?:bool, force?:bool, skip_geocode?:bool}  $options
     * @return array<string, mixed>
     */
    public function import(string $path, array $options = []): array
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $skipGeocode = (bool) ($options['skip_geocode'] ?? false)
            || ! ServiceFinderSettings::get('import_geocode_enabled');

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("CSV file not found or unreadable: {$path}");
        }

        $this->report = [
            'rows_read' => 0, 'parsed' => 0, 'duplicates' => 0, 'skipped_existing' => 0,
            'created' => 0, 'updated' => 0, 'active' => 0, 'inactive' => 0,
            'split_postcodes' => 0,
        ];

        $reasons = [];
        $rows = $this->parseCsv($path, $reasons);

        if ($dryRun) {
            return $this->finishReport($rows, $reasons, [], true);
        }

        if ($force) {
            // Only import-owned rows are cleared; admin-authored sites survive.
            $this->report['deleted'] = FinderSite::query()->imported()->delete();
        }

        $geocodes = $skipGeocode ? [] : $this->geocodeRows($rows);

        $this->persist($rows, $geocodes);

        $this->recount->rebuildAll();

        return $this->finishReport($rows, $reasons, $this->geocoder->stats(), false);
    }

    /**
     * Read and normalise every CSV row.
     *
     * @param  array<string, int>  $reasons
     * @return array<int, array<string, mixed>>
     */
    protected function parseCsv(string $path, array &$reasons): array
    {
        $serviceMap = $this->resolveServiceMap();
        $areaMap = ServiceFinderSettings::postcodeAreaMap();
        $testPatterns = array_map('strtolower', (array) ServiceFinderSettings::get('import_test_patterns'));

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV: {$path}");
        }

        $rows = [];
        $seenFingerprints = [];
        $header = null;

        while (($cells = fgetcsv($handle)) !== false) {
            if ($cells === [null] || $cells === false) {
                continue;
            }

            // Strip a UTF-8 BOM from the very first cell if present.
            if ($header === null) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                $header = array_map(fn ($c) => strtolower(trim((string) $c)), $cells);

                continue;
            }

            $this->report['rows_read']++;

            $serviceType = trim((string) ($cells[0] ?? ''));
            $postcodeRaw = trim((string) ($cells[1] ?? ''));
            $siteName = $this->collapse((string) ($cells[2] ?? ''));

            if ($siteName === '' && $postcodeRaw === '') {
                continue;
            }

            $parsed = UkPostcode::parse($postcodeRaw);

            if ($parsed['discarded']) {
                $this->report['split_postcodes']++;
            }

            $serviceId = $serviceMap[strtolower($serviceType)] ?? null;
            $area = $parsed['area'] ? strtoupper($parsed['area']) : null;
            $areaKnown = $area !== null && isset($areaMap[$area]);

            [$isActive, $reason] = $this->classify($siteName, $serviceType, $serviceId, $parsed, $areaKnown, $testPatterns);

            if ($reason) {
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }

            // Exact repeats of the same operational row collapse into one;
            // two different sites sharing a postcode stay separate, because
            // they are genuinely two client premises.
            $fingerprint = sha1(strtolower($serviceType.'|'.$postcodeRaw.'|'.$siteName));

            if (isset($seenFingerprints[$fingerprint])) {
                $this->report['duplicates']++;

                continue;
            }

            $seenFingerprints[$fingerprint] = true;
            $this->report['parsed']++;
            $isActive ? $this->report['active']++ : $this->report['inactive']++;

            $rows[] = [
                'service_id' => $serviceId,
                // The site name from the CSV is used above to fingerprint the
                // row (so two genuinely different premises sharing a postcode
                // never collapse into one) and to classify test/demo rows —
                // both need the real value. It is never written to the
                // database: nothing reads it (the API has never exposed it,
                // and the admin sites list no longer shows it either), so
                // there is no reason to keep a client's name on file at all.
                'site_name' => '',
                'postcode_raw' => mb_substr($postcodeRaw, 0, 60),
                'postcode' => $parsed['postcode'],
                'outcode' => $parsed['outcode'],
                'postcode_area' => $area,
                'is_active' => $isActive,
                'inactive_reason' => $reason,
                'source' => FinderSite::SOURCE_IMPORT,
                'import_fingerprint' => $fingerprint,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Decide whether a row belongs on the public map.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array<int, string>  $testPatterns
     * @return array{0: bool, 1: ?string}
     */
    protected function classify(
        string $siteName,
        string $serviceType,
        ?int $serviceId,
        array $parsed,
        bool $areaKnown,
        array $testPatterns,
    ): array {
        $haystack = strtolower($siteName.' '.$serviceType);

        // Whole-phrase matching only. Substring matching wrongly flagged real
        // sites: "demo" hit "St Hellier Group Demolition" and "test" hit
        // "Crematorium - Test Valley", a genuine Hampshire borough.
        foreach ($testPatterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            if (preg_match('/\b'.preg_quote($pattern, '/').'\b/', $haystack)) {
                return [false, FinderSite::REASON_TEST_ROW];
            }
        }

        if ($serviceId === null) {
            return [false, FinderSite::REASON_UNMAPPED_SERVICE];
        }

        if (! $parsed['valid'] && ! $parsed['partial']) {
            return [false, FinderSite::REASON_INVALID_POSTCODE];
        }

        // A prefix outside the postcode-area table is not a UK postcode —
        // this is what filters Eircodes and continental codes.
        if (! $areaKnown) {
            return [false, FinderSite::REASON_NON_UK];
        }

        return [true, null];
    }

    /**
     * Resolve coordinates for the active rows only. Inactive rows are never
     * geocoded, so foreign and test sites cost nothing.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, FinderGeocode>
     */
    protected function geocodeRows(array $rows): array
    {
        $postcodes = [];
        $outcodes = [];

        foreach ($rows as $row) {
            if (! $row['is_active']) {
                continue;
            }

            if ($row['postcode']) {
                $postcodes[] = $row['postcode'];
            } elseif ($row['outcode']) {
                $outcodes[] = $row['outcode'];
            }
        }

        return $this->geocoder->resolveMany($postcodes, $outcodes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, FinderGeocode>  $geocodes
     */
    protected function persist(array $rows, array $geocodes): void
    {
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $geocodes) {
                foreach ($chunk as $row) {
                    $existing = FinderSite::query()
                        ->where('import_fingerprint', $row['import_fingerprint'])
                        ->first();

                    // An operator's manual edits outrank the spreadsheet.
                    if ($existing && $existing->source === FinderSite::SOURCE_ADMIN) {
                        $this->report['skipped_existing']++;

                        continue;
                    }

                    [$lat, $lng] = $this->coordinatesFor($row, $geocodes);
                    $source = $this->geocodeSourceFor($row, $geocodes);

                    // A row that survived classification but could not be
                    // placed cannot be drawn, so it is parked as inactive.
                    $isActive = $row['is_active'] && $lat !== null && $lng !== null;
                    $reason = $row['inactive_reason'];

                    if ($row['is_active'] && ! $isActive) {
                        $reason = FinderSite::REASON_INVALID_POSTCODE;
                        $this->report['active']--;
                        $this->report['inactive']++;
                    }

                    $payload = $row + [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'geocode_source' => $source,
                    ];
                    $payload['is_active'] = $isActive;
                    $payload['inactive_reason'] = $reason;

                    if ($existing) {
                        $existing->fill($payload)->save();
                        $this->report['updated']++;

                        continue;
                    }

                    FinderSite::query()->create($payload);
                    $this->report['created']++;
                }
            });
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, FinderGeocode>  $geocodes
     * @return array{0: ?float, 1: ?float}
     */
    protected function coordinatesFor(array $row, array $geocodes): array
    {
        foreach ([$row['postcode'], $row['outcode']] as $key) {
            if (! $key) {
                continue;
            }

            $hit = $geocodes[strtoupper($key)] ?? null;

            if ($hit && $hit->isResolved()) {
                return [$hit->latitude, $hit->longitude];
            }
        }

        return [null, null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, FinderGeocode>  $geocodes
     */
    protected function geocodeSourceFor(array $row, array $geocodes): ?string
    {
        foreach ([$row['postcode'], $row['outcode']] as $key) {
            if (! $key) {
                continue;
            }

            $hit = $geocodes[strtoupper($key)] ?? null;

            if ($hit && $hit->isResolved()) {
                return $hit->source;
            }
        }

        return null;
    }

    /**
     * CSV service type => service id, resolved through slugs so the mapping
     * survives a reseed where ids change.
     *
     * @return array<string, int>
     */
    protected function resolveServiceMap(): array
    {
        $slugMap = ServiceFinderSettings::importServiceMap();

        if ($slugMap === []) {
            return [];
        }

        $services = Service::query()
            ->with('slug')
            ->where('status', true)
            ->get()
            ->mapWithKeys(fn (Service $service) => [
                (string) ($service->slug?->slug ?? '') => (int) $service->id,
            ])
            ->forget('');

        $resolved = [];

        foreach ($slugMap as $type => $slug) {
            $id = $services[$slug] ?? null;

            if ($id) {
                $resolved[$type] = $id;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $reasons
     * @param  array<string, int>  $geocodeStats
     * @return array<string, mixed>
     */
    protected function finishReport(array $rows, array $reasons, array $geocodeStats, bool $dryRun): array
    {
        return [
            'dry_run' => $dryRun,
            'counts' => $this->report,
            'inactive_reasons' => $reasons,
            'geocoding' => $geocodeStats,
            'sample' => array_slice(array_map(
                static fn ($row) => [
                    'postcode' => $row['postcode'] ?? $row['outcode'] ?? $row['postcode_raw'],
                    'area' => $row['postcode_area'],
                    'active' => $row['is_active'] ? 'yes' : 'no',
                    'reason' => $row['inactive_reason'] ?? '',
                ],
                $rows
            ), 0, 10),
        ];
    }

    protected function collapse(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
