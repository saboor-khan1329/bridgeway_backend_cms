<?php

namespace App\Support;

/**
 * UK postcode normalisation for the Service Finder import and search.
 *
 * The operations export is hand-maintained, so it contains every flavour of
 * near-miss: missing spaces ("NR21SU"), truncated inward codes ("TN1 2T"),
 * outcode-only entries ("HA7"), two postcodes in one cell
 * ("RH11 7AE / RH10 7AE"), trailing whitespace, and non-UK values such as
 * Irish Eircodes and continental codes. Rather than discard those rows, the
 * parser reports exactly what it could salvage and why, so the importer can
 * store them with an accurate reason.
 */
class UkPostcode
{
    /** Full postcode, already space-normalised. */
    public const FULL_PATTERN = '/^([A-Z]{1,2}\d[A-Z\d]?)\s(\d[A-Z]{2})$/';

    /** Outward code on its own. */
    public const OUTCODE_PATTERN = '/^[A-Z]{1,2}\d[A-Z\d]?$/';

    /**
     * Parse one raw CSV cell.
     *
     * @return array{
     *     postcode: ?string,
     *     outcode: ?string,
     *     area: ?string,
     *     valid: bool,
     *     partial: bool,
     *     discarded: ?string,
     *     reason: ?string
     * }
     */
    public static function parse(?string $raw): array
    {
        $empty = [
            'postcode' => null, 'outcode' => null, 'area' => null,
            'valid' => false, 'partial' => false, 'discarded' => null, 'reason' => null,
        ];

        $raw = trim((string) $raw);

        if ($raw === '') {
            return array_merge($empty, ['reason' => 'empty']);
        }

        // A cell may hold two postcodes; keep the first that parses and
        // remember the rest for the import report.
        $candidates = preg_split('#[/\\\\,;]+#', $raw) ?: [$raw];
        $candidates = array_values(array_filter(array_map('trim', $candidates)));
        $discarded = count($candidates) > 1 ? implode(' / ', array_slice($candidates, 1)) : null;

        foreach ($candidates as $candidate) {
            $parsed = self::parseOne($candidate);

            if ($parsed['valid'] || $parsed['partial']) {
                $parsed['discarded'] = $discarded;

                return $parsed;
            }
        }

        $first = self::parseOne($candidates[0] ?? $raw);
        $first['discarded'] = $discarded;

        return $first;
    }

    /**
     * @return array{postcode: ?string, outcode: ?string, area: ?string, valid: bool, partial: bool, discarded: ?string, reason: ?string}
     */
    protected static function parseOne(string $value): array
    {
        $result = [
            'postcode' => null, 'outcode' => null, 'area' => null,
            'valid' => false, 'partial' => false, 'discarded' => null, 'reason' => null,
        ];

        $compact = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value) ?? '');

        if ($compact === '') {
            $result['reason'] = 'empty';

            return $result;
        }

        // The one non-geographic special case still in use.
        if ($compact === 'GIR0AA') {
            return [
                'postcode' => 'GIR 0AA', 'outcode' => 'GIR', 'area' => 'GIR',
                'valid' => true, 'partial' => false, 'discarded' => null, 'reason' => null,
            ];
        }

        // Re-insert the space before the three-character inward code.
        $spaced = preg_replace('/^([A-Z]{1,2}\d[A-Z\d]?)(\d[A-Z]{2})$/', '$1 $2', $compact) ?? $compact;

        if (preg_match(self::FULL_PATTERN, $spaced, $matches)) {
            return [
                'postcode' => $spaced,
                'outcode' => $matches[1],
                'area' => self::areaOf($matches[1]),
                'valid' => true,
                'partial' => false,
                'discarded' => null,
                'reason' => null,
            ];
        }

        if (preg_match(self::OUTCODE_PATTERN, $compact)) {
            return [
                'postcode' => null,
                'outcode' => $compact,
                'area' => self::areaOf($compact),
                'valid' => false,
                'partial' => true,
                'discarded' => null,
                'reason' => 'outcode_only',
            ];
        }

        // Truncated inward code ("TN1 2T") still identifies a real UK
        // district, so keep the outcode and treat it as partial.
        if (preg_match('/^([A-Z]{1,2}\d[A-Z\d]?)/', $compact, $matches)) {
            $outcode = $matches[1];

            // Guard against Irish Eircodes and similar, which also start
            // letter+digit but never form a valid UK outward code alone.
            if (preg_match(self::OUTCODE_PATTERN, $outcode)) {
                return [
                    'postcode' => null,
                    'outcode' => $outcode,
                    'area' => self::areaOf($outcode),
                    'valid' => false,
                    'partial' => true,
                    'discarded' => null,
                    'reason' => 'salvaged_outcode',
                ];
            }
        }

        $result['reason'] = 'unparseable';

        return $result;
    }

    /** Leading letters of an outward code: "SW15" => "SW". */
    public static function areaOf(string $outcode): ?string
    {
        return preg_match('/^([A-Z]{1,2})/', strtoupper(trim($outcode)), $matches)
            ? $matches[1]
            : null;
    }

    /**
     * True when the text looks like the beginning of a UK postcode
     * (letter(s) followed by a digit). Used by the frontend-facing search to
     * decide it can resolve locally instead of calling an address API.
     */
    public static function looksLikePostcode(string $value): bool
    {
        return (bool) preg_match('/^[A-Z]{1,2}\d/', strtoupper(trim($value)));
    }
}
