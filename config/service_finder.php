<?php

/*
|--------------------------------------------------------------------------
| Service Finder configuration
|--------------------------------------------------------------------------
| Settings for the public "Find Security Services in Your Area" tool:
| geocoding providers used by the site import, CSV import normalisation,
| quote-lead spam tuning, and the postcode-area → display-city map that
| groups imported sites into aggregated map areas.
|
| House rules: env() is only read here (never in routes/controllers), every
| value is cast, and every env has a sensible default.
*/

$bool = static fn (string $key, bool $default): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
    FILTER_NULL_ON_FAILURE
) ?? $default;

return [

    /*
    | Master kill switch.
    |
    | Set SERVICE_FINDER_ENABLED=false to switch the whole feature off from
    | the environment: the public endpoints stop serving, the website drops
    | the section entirely, and the admin module is hidden. It overrides the
    | in-app toggle, so it stays off even if someone re-enables the section in
    | the configurator — the intended behaviour for an incident or a cost stop.
    |
    | Leave it on and use the configurator's own switches for day-to-day
    | control (hide just the map, or just the address autocomplete).
    */
    'enabled' => $bool('SERVICE_FINDER_ENABLED', false),

    /*
    | Geocoding — bulk postcode resolution for imported sites.
    | postcodes.io (free, OS OpenData) is always tried first:
    |   1. bulk postcode lookup  2. terminated-postcode lookup
    |   3. outcode centroid lookup 4. Google Geocoding API (paid, fallback)
    | Aggregated area pins only need outcode-level precision, so the paid
    | fallback is rarely reached.
    */
    'geocoding' => [
        'postcodes_io_url' => rtrim((string) env('POSTCODES_IO_URL', 'https://api.postcodes.io'), '/'),
        'bulk_chunk_size' => 100,
        'google_api_key' => (string) env('GOOGLE_MAPS_API_KEY', ''),
        'google_fallback_enabled' => $bool('SERVICE_FINDER_GOOGLE_GEOCODE_FALLBACK', true),
        'fail_open' => $bool('SERVICE_FINDER_GEOCODE_FAIL_OPEN', true),
        'timeout' => (int) env('SERVICE_FINDER_GEOCODE_TIMEOUT', 15),

        /*
        | Optional CA bundle override for outbound HTTPS.
        | Only needed where PHP's curl.cainfo points at a path that does not
        | exist (a moved or renamed PHP install), which makes every HTTPS
        | request fail with cURL error 77. Leave unset in production so the
        | system trust store is used. Never disable verification.
        */
        'ca_bundle' => (string) env('SERVICE_FINDER_CA_BUNDLE', ''),
    ],

    /*
    | CSV import — normalisation of the operations site export.
    | service_type_map keys are lowercased CSV "Services Type" values and
    | resolve to service SLUGS (stable across environments; IDs are not).
    | Rows whose type is missing from the map (e.g. "Internal Management"),
    | rows with non-UK/unresolvable postcodes, and rows whose site name
    | matches an inactive pattern are imported with is_active = false so
    | nothing is lost and admins can toggle them later.
    */
    'import' => [
        'default_file' => (string) env(
            'SERVICE_FINDER_IMPORT_FILE',
            // storage/app/private/ — not base_path('../rough/...'): that sibling
            // folder only ever existed on this machine's local checkout, so the
            // path broke the moment it was read from anywhere else (a VPS has no
            // such sibling directory, and Windows vs Linux disagree on '../').
            // storage_path() resolves identically on every environment and the
            // file lives inside the app's own (non-public) storage disk, which
            // matters here since the export names real client sites. Override
            // with SERVICE_FINDER_IMPORT_FILE if it moves again.
            storage_path('app/private/service-finder-tool-data/Site_2026-07-18_23-57-45.csv')
        ),
        'service_type_map' => [
            'guarding services' => 'manned-guarding-services',
            'mobile services' => 'mobile-security-patrols-services',
        ],
        // Matched as whole phrases. Deliberately specific: a bare "demo"
        // would flag "St Hellier Group Demolition", and a bare "test" would
        // flag "Crematorium - Test Valley", both of which are real sites.
        'inactive_name_patterns' => [
            'test site', 'test mobile', 'test internal', 'demo home',
            'sample ocs', 'annual holiday', 'event site', 'prm',
        ],
    ],

    /*
    | Quote leads (popup form). The spam stack is inherited from the
    | inquiry pipeline (captcha, timing gate, link/keyword scan, field
    | rules) via ServiceFinderSpamGuard; only the honeypot field name
    | differs because the quote form has a REAL "company" field, which is
    | the inquiry honeypot's default name.
    */
    'leads' => [
        'rate_limit_per_minute' => (int) env('SERVICE_FINDER_LEAD_RATE_LIMIT', 5),
        'honeypot_field' => (string) env('SERVICE_FINDER_HONEYPOT_FIELD', 'website'),
        'notifications_enabled' => $bool('SERVICE_FINDER_LEAD_NOTIFICATIONS_ENABLED', true),
        'start_date_options' => ['ASAP', 'Within 1 week', 'Within 1 month', '1-3 months', 'Just researching'],
    ],

    /*
    | Search logging (fire-and-forget analytics from the finder UI).
    */
    'searches' => [
        'rate_limit_per_minute' => (int) env('SERVICE_FINDER_SEARCH_RATE_LIMIT', 30),
    ],

    /*
    | Usage-event logging (fire-and-forget beacon from the map and search
    | box, one call per map load and one per new autocomplete session — see
    | FinderUsageService). Same shape as search logging, kept separate so
    | one limit can be tuned without affecting the other.
    */
    'usage' => [
        'rate_limit_per_minute' => (int) env('SERVICE_FINDER_USAGE_RATE_LIMIT', 60),
    ],

    /*
    | Postcode area → public display city. London postal districts all
    | collapse into a single "London" area; every other area keeps its
    | conventional postal-town name. Import groups sites by the mapped
    | NAME, so all eight London codes land in one area row. Admins can
    | rename or deactivate any created area afterwards.
    */
    'area_map' => [
        'AB' => 'Aberdeen', 'AL' => 'St Albans', 'B' => 'Birmingham', 'BA' => 'Bath',
        'BB' => 'Blackburn', 'BD' => 'Bradford', 'BH' => 'Bournemouth', 'BL' => 'Bolton',
        'BN' => 'Brighton', 'BR' => 'Bromley', 'BS' => 'Bristol', 'BT' => 'Belfast',
        'CA' => 'Carlisle', 'CB' => 'Cambridge', 'CF' => 'Cardiff', 'CH' => 'Chester',
        'CM' => 'Chelmsford', 'CO' => 'Colchester', 'CR' => 'Croydon', 'CT' => 'Canterbury',
        'CV' => 'Coventry', 'CW' => 'Crewe', 'DA' => 'Dartford', 'DD' => 'Dundee',
        'DE' => 'Derby', 'DG' => 'Dumfries', 'DH' => 'Durham', 'DL' => 'Darlington',
        'DN' => 'Doncaster', 'DT' => 'Dorchester', 'DY' => 'Dudley',
        'E' => 'London', 'EC' => 'London',
        'EH' => 'Edinburgh', 'EN' => 'Enfield', 'EX' => 'Exeter', 'FK' => 'Falkirk',
        'FY' => 'Blackpool', 'G' => 'Glasgow', 'GL' => 'Gloucester', 'GU' => 'Guildford',
        'GY' => 'Guernsey', 'HA' => 'Harrow', 'HD' => 'Huddersfield', 'HG' => 'Harrogate',
        'HP' => 'Hemel Hempstead', 'HR' => 'Hereford', 'HS' => 'Outer Hebrides', 'HU' => 'Hull',
        'HX' => 'Halifax', 'IG' => 'Ilford', 'IM' => 'Isle of Man', 'IP' => 'Ipswich',
        'IV' => 'Inverness', 'JE' => 'Jersey', 'KA' => 'Kilmarnock', 'KT' => 'Kingston upon Thames',
        'KW' => 'Kirkwall', 'KY' => 'Kirkcaldy', 'L' => 'Liverpool', 'LA' => 'Lancaster',
        'LD' => 'Llandrindod Wells', 'LE' => 'Leicester', 'LL' => 'Llandudno', 'LN' => 'Lincoln',
        'LS' => 'Leeds', 'LU' => 'Luton', 'M' => 'Manchester', 'ME' => 'Medway',
        'MK' => 'Milton Keynes', 'ML' => 'Motherwell',
        'N' => 'London', 'NW' => 'London',
        'NE' => 'Newcastle upon Tyne', 'NG' => 'Nottingham', 'NN' => 'Northampton',
        'NP' => 'Newport', 'NR' => 'Norwich', 'OL' => 'Oldham', 'OX' => 'Oxford',
        'PA' => 'Paisley', 'PE' => 'Peterborough', 'PH' => 'Perth', 'PL' => 'Plymouth',
        'PO' => 'Portsmouth', 'PR' => 'Preston', 'RG' => 'Reading', 'RH' => 'Redhill',
        'RM' => 'Romford', 'S' => 'Sheffield', 'SA' => 'Swansea',
        'SE' => 'London', 'SW' => 'London',
        'SG' => 'Stevenage', 'SK' => 'Stockport', 'SL' => 'Slough', 'SM' => 'Sutton',
        'SN' => 'Swindon', 'SO' => 'Southampton', 'SP' => 'Salisbury', 'SR' => 'Sunderland',
        'SS' => 'Southend-on-Sea', 'ST' => 'Stoke-on-Trent', 'SY' => 'Shrewsbury',
        'TA' => 'Taunton', 'TD' => 'Galashiels', 'TF' => 'Telford', 'TN' => 'Tonbridge',
        'TQ' => 'Torquay', 'TR' => 'Truro', 'TS' => 'Middlesbrough', 'TW' => 'Twickenham',
        'UB' => 'Uxbridge',
        'W' => 'London', 'WC' => 'London',
        'WA' => 'Warrington', 'WD' => 'Watford', 'WF' => 'Wakefield', 'WN' => 'Wigan',
        'WR' => 'Worcester', 'WS' => 'Walsall', 'WV' => 'Wolverhampton', 'YO' => 'York',
        'ZE' => 'Lerwick',
    ],

    /*
    | Map defaults consumed by the public coverage endpoint (admin can
    | override via Site Settings group "service_finder").
    */
    'map' => [
        'center' => ['lat' => 53.0, 'lng' => -1.8],
        'zoom' => (int) env('SERVICE_FINDER_MAP_ZOOM', 6),
    ],
];
