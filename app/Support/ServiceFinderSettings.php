<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Single source of truth for every operator-tunable Service Finder value.
 *
 * The admin configurator renders itself from FIELDS, validates from the same
 * definitions, and persists into the site_settings key/value store under the
 * "service_finder" group. Application code never reads SiteSetting directly —
 * it calls all()/get() here so a missing or blank setting always resolves to
 * the documented default instead of an empty screen.
 *
 * Adding a tunable = add one entry to FIELDS. Nothing else needs editing.
 */
class ServiceFinderSettings
{
    public const GROUP = 'service_finder';

    /** Prefix keeps finder keys from colliding with other groups. */
    public const PREFIX = 'sf_';

    /**
     * Tabs rendered by the configurator, in order.
     */
    public const TABS = [
        'placement' => 'Where It Appears',
        'section' => 'Section Copy',
        'copy' => 'Popup & Modal Copy',
        'map' => 'Map & Pins',
        'behaviour' => 'Behaviour & Cost',
        'import' => 'Import Rules',
        'areas' => 'Postcode Areas',
        'spam' => 'Spam & Limits',
        'notifications' => 'Notifications',
        // Read-only. Holds no settings — it is the written guide to the whole
        // tool, kept beside the switches it describes so an operator never has
        // to go looking for a document elsewhere.
        'guide' => 'Guide',
    ];

    /**
     * Field definitions.
     *
     * type:     text | textarea | number | toggle | select | lines | json | keyvalue
     * default:  used when the stored value is null or ''
     * tokens:   placeholder names allowed in the value (shown as help text)
     */
    public const FIELDS = [
        // -------------------------------------------------------------- placement
        // One switch per page type. The website asks for the finder on every
        // page it is coded into; these decide whether it actually renders, so
        // a new placement can be turned on later without a deploy.
        //
        // Only the home page ships enabled. The others are wired end to end
        // and waiting — flip the switch when the page is ready for it.
        'placement_home' => [
            'tab' => 'placement', 'type' => 'toggle', 'label' => 'Show on the home page',
            'default' => '1', 'col' => 6,
            'help' => 'The section sits directly under the hero. This is the only placement enabled by default.',
        ],
        'placement_service' => [
            'tab' => 'placement', 'type' => 'toggle', 'label' => 'Show on service pages',
            'default' => '0', 'col' => 6,
            'help' => 'When on, the finder appears on every service page and preselects that service.',
        ],
        'placement_sector' => [
            'tab' => 'placement', 'type' => 'toggle', 'label' => 'Show on sector pages',
            'default' => '0', 'col' => 6,
            'help' => 'When on, the finder appears on every sector page and preselects that sector service.',
        ],
        'placement_location' => [
            'tab' => 'placement', 'type' => 'toggle', 'label' => 'Show on location pages',
            'default' => '0', 'col' => 6,
            'help' => 'When on, the finder appears on every location page and opens focused on that area.',
        ],
        'placement_preselect' => [
            'tab' => 'placement', 'type' => 'toggle', 'label' => 'Preselect from the page',
            'default' => '1', 'col' => 6,
            'help' => 'Fills the search from the page it is on — the area on a location page, the service on a service or sector page. Turn off to always start blank.',
        ],
        'placement_autoscroll' => [
            'tab' => 'placement', 'type' => 'toggle', 'label' => 'Scroll results to the preselection',
            'default' => '1', 'col' => 6,
            'help' => 'Moves the map to the preselected area on load. Off means the visitor sees the full UK map first.',
        ],
        'placement_heading_service' => [
            'tab' => 'placement', 'type' => 'text', 'label' => 'Heading on service pages',
            'default' => 'Where We Provide {service}',
            'tokens' => ['{service}'], 'col' => 12, 'max' => 190,
            'help' => 'Leave blank to reuse the standard section heading.',
        ],
        'placement_heading_sector' => [
            'tab' => 'placement', 'type' => 'text', 'label' => 'Heading on sector pages',
            'default' => 'Our {service} Coverage Across the UK',
            'tokens' => ['{service}'], 'col' => 12, 'max' => 190,
            'help' => 'Leave blank to reuse the standard section heading.',
        ],
        'placement_heading_location' => [
            'tab' => 'placement', 'type' => 'text', 'label' => 'Heading on location pages',
            'default' => 'Security Services We Cover in {area}',
            'tokens' => ['{area}'], 'col' => 12, 'max' => 190,
            'help' => 'Leave blank to reuse the standard section heading.',
        ],

        // ---------------------------------------------------------------- section
        'heading' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Section heading',
            'default' => 'Find Security Services in Your Area', 'col' => 6, 'max' => 190,
        ],
        'subheading' => [
            'tab' => 'section', 'type' => 'textarea', 'label' => 'Section intro',
            'default' => 'Explore our UK coverage. Select a map pin to view security services available near you.',
            'col' => 6, 'max' => 500,
        ],
        'location_label' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Location field label',
            'default' => 'Enter location or postcode', 'col' => 4, 'max' => 120,
        ],
        'location_placeholder' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Location field placeholder',
            'default' => 'Enter location or postcode', 'col' => 4, 'max' => 120,
        ],
        'service_label' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Service field label',
            'default' => 'Enter service required', 'col' => 4, 'max' => 120,
        ],
        'service_placeholder' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Service field placeholder',
            'default' => 'All services', 'col' => 4, 'max' => 120,
        ],
        'search_button_label' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Search button label',
            'default' => 'Search', 'col' => 4, 'max' => 60,
        ],
        'results_count_template' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Results counter',
            'default' => 'Showing {shown} of {total} results', 'col' => 4,
            'tokens' => ['{shown}', '{total}'], 'max' => 190,
        ],
        'card_available_label' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Card "available in" label',
            'default' => 'Available in :', 'col' => 4, 'max' => 60,
        ],
        'card_view_label' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Card view-service link',
            'default' => 'View Service', 'col' => 4, 'max' => 60,
        ],
        'card_quote_label' => [
            'tab' => 'section', 'type' => 'text', 'label' => 'Card quote button',
            'default' => 'Get a Quote', 'col' => 4, 'max' => 60,
        ],
        'show_coverage_index' => [
            'tab' => 'section', 'type' => 'toggle', 'label' => 'Show the "browse all areas" list',
            'help' => 'Off by default, matching the approved design. Turning it on adds a collapsible list of every covered area under the results, giving search engines a direct link to each location page.',
            'default' => '0', 'col' => 4,
        ],
        'coverage_index_label' => [
            'tab' => 'section', 'type' => 'text', 'label' => '"Browse all areas" label',
            'default' => 'Browse all {count} covered areas',
            'tokens' => ['{count}'], 'col' => 8, 'max' => 190,
        ],
        'empty_state_text' => [
            'tab' => 'section', 'type' => 'textarea', 'label' => 'No-results message',
            'default' => 'We do not have registered sites in {query} yet. Your nearest coverage is {area}. Request a quote and a local specialist will still call you back.',
            'tokens' => ['{query}', '{area}'], 'col' => 12, 'max' => 500,
        ],

        // ------------------------------------------------------------------- copy
        'popup_title_template' => [
            'tab' => 'copy', 'type' => 'text', 'label' => 'Map pin popup title',
            'default' => '{area}: Bridgeway Digital Coverage',
            'tokens' => ['{area}'], 'col' => 6, 'max' => 190,
        ],
        'popup_body_template' => [
            'tab' => 'copy', 'type' => 'textarea', 'label' => 'Map pin popup body',
            'default' => 'We currently support {count} digital growth services across {area}, including {services}.',
            'tokens' => ['{count}', '{area}', '{services}', '{sites}'], 'col' => 6, 'max' => 500,
        ],
        'popup_cta_template' => [
            'tab' => 'copy', 'type' => 'text', 'label' => 'Map pin popup CTA',
            'default' => '{service} in {area}',
            'tokens' => ['{service}', '{area}'], 'col' => 6, 'max' => 190,
        ],
        // Off by default: a declustered pin's popup shows the area-level copy
        // above unless this is switched on, at which point it also states
        // the exact service and postcode district for that one site.
        'popup_show_site_postcode' => [
            'tab' => 'copy', 'type' => 'toggle', 'label' => 'Show the exact service and postcode on a single-site pin',
            'default' => '0',
            'help' => 'When a visitor clicks one specific site (not a numbered cluster), adds a line naming the service offered there and its postcode district, e.g. "Manned Guarding Services at LS10". Off by default to match the approved popup design.',
        ],
        'modal_heading_template' => [
            'tab' => 'copy', 'type' => 'text', 'label' => 'Quote modal heading',
            'default' => 'Great news - Bridgeway Digital supports {area}.',
            'tokens' => ['{area}', '{service}'], 'col' => 6, 'max' => 190,
        ],
        'modal_heading_no_area' => [
            'tab' => 'copy', 'type' => 'text', 'label' => 'Quote modal heading (no area matched)',
            'default' => 'Request your free digital growth quote.', 'col' => 6, 'max' => 190,
        ],
        'modal_subheading_template' => [
            'tab' => 'copy', 'type' => 'textarea', 'label' => 'Quote modal subheading',
            'default' => 'Get your free {service} quote today - No Obligation.',
            'tokens' => ['{service}', '{area}'], 'col' => 6, 'max' => 500,
        ],
        'modal_submit_label' => [
            'tab' => 'copy', 'type' => 'text', 'label' => 'Submit button label',
            'default' => 'Get My Free Quote', 'col' => 6, 'max' => 60,
        ],
        'modal_microcopy' => [
            'tab' => 'copy', 'type' => 'textarea', 'label' => 'Under-button microcopy',
            'default' => 'No obligation. No spam. A local specialist will call you back within 2 working hours.',
            'col' => 6, 'max' => 500,
        ],
        'usp_items' => [
            'tab' => 'copy', 'type' => 'lines', 'label' => 'USP strip (one per line)',
            'default' => "Free Risk Assessment\nFree Site Survey\nNo Long Contracts\n30-Days Notice\nLive in 7 Days",
            'col' => 6,
        ],
        'success_heading_template' => [
            'tab' => 'copy', 'type' => 'text', 'label' => 'Success heading',
            'default' => 'Thanks, {name} — your request is in.',
            'tokens' => ['{name}'], 'col' => 6, 'max' => 190,
        ],
        'success_body' => [
            'tab' => 'copy', 'type' => 'textarea', 'label' => 'Success body',
            'default' => 'A Bridgeway Digital specialist will contact you within 2 working hours to discuss your goals. No commitment, no pressure.',
            'col' => 6, 'max' => 1000,
        ],
        'success_cta_label' => [
            'tab' => 'copy', 'type' => 'text', 'label' => 'Success button label',
            'default' => 'Back to Map', 'col' => 6, 'max' => 60,
        ],
        'error_message' => [
            'tab' => 'copy', 'type' => 'textarea', 'label' => 'Submission failure message',
            'default' => 'Sorry — we could not send your request just now. Please try again, or call us directly.',
            'col' => 6, 'max' => 500,
        ],
        'generic_service_description' => [
            'tab' => 'copy', 'type' => 'textarea', 'label' => 'Fallback service description',
            'default' => 'Professional digital growth support tailored to your business goals and operating model.',
            'help' => 'Used when a service has neither a coverage override nor a card description.',
            'col' => 12, 'max' => 500,
        ],

        // -------------------------------------------------------------------- map
        'map_center_lat' => [
            'tab' => 'map', 'type' => 'number', 'label' => 'Default centre latitude',
            'default' => '53.4', 'step' => '0.0000001', 'col' => 3,
        ],
        'map_center_lng' => [
            'tab' => 'map', 'type' => 'number', 'label' => 'Default centre longitude',
            'default' => '-2.2', 'step' => '0.0000001', 'col' => 3,
        ],
        'map_zoom' => [
            'tab' => 'map', 'type' => 'number', 'label' => 'Default zoom',
            'default' => '6', 'min' => 3, 'max' => 18, 'col' => 3,
        ],
        'map_zoom_on_match' => [
            'tab' => 'map', 'type' => 'number', 'label' => 'Zoom when an area matches',
            'default' => '9', 'min' => 3, 'max' => 18, 'col' => 3,
        ],
        'map_restrict_to_uk' => [
            'tab' => 'map', 'type' => 'toggle', 'label' => 'Restrict panning to the UK',
            'default' => '1', 'col' => 3,
        ],
        'map_cluster_enabled' => [
            'tab' => 'map', 'type' => 'toggle', 'label' => 'Cluster nearby pins',
            'default' => '1', 'col' => 3,
        ],
        'map_legend_enabled' => [
            'tab' => 'map', 'type' => 'toggle', 'label' => 'Show the service colour key',
            'default' => '1', 'col' => 3,
            'help' => 'The panel over the map naming each service and the colour its sites are drawn in.',
        ],
        'map_service_palette' => [
            'tab' => 'map', 'type' => 'lines', 'label' => 'Service colours (one hex per line)',
            'default' => "#48C6C2\n#EC814D\n#5E6AE2\n#24B9DA\n#DA5D6F\n#99CB6A\n#4276CA\n#EABB53\n#4EBA86",
            'col' => 6,
            'help' => 'Assigned to services in the order they appear in the coverage list. Runs out? The list simply repeats.',
        ],
        'stats_enabled' => [
            'tab' => 'section', 'type' => 'toggle', 'label' => 'Show the statistics bar',
            'default' => '1', 'col' => 3,
            'help' => 'The dark scrolling bar under the map.',
        ],
        'stats_items' => [
            'tab' => 'section', 'type' => 'lines', 'label' => 'Statistics bar (one per line, "figure | wording")',
            'default' => "£5.2B | UK retail security market value\n20M | retail guard deployments annually\n12000+ | retail stores secured across Europe\n15000+ | licensed UK security professionals",
            'col' => 6,
            'help' => 'Split on the first "|": what is before it is emphasised, what follows is the description.',
        ],
        'map_land_color' => [
            'tab' => 'map', 'type' => 'text', 'label' => 'Map land colour',
            'default' => '#0f172a', 'col' => 3, 'max' => 9,
        ],
        'map_water_color' => [
            'tab' => 'map', 'type' => 'text', 'label' => 'Map water colour',
            'default' => '#0a1122', 'col' => 3, 'max' => 9,
        ],
        'map_road_color' => [
            'tab' => 'map', 'type' => 'text', 'label' => 'Map road colour',
            'default' => '#1e2a4a', 'col' => 3, 'max' => 9,
        ],
        'map_label_color' => [
            'tab' => 'map', 'type' => 'text', 'label' => 'Map label colour',
            'default' => '#8fa3c8', 'col' => 3, 'max' => 9,
        ],
        'pin_outer_color' => [
            'tab' => 'map', 'type' => 'text', 'label' => 'Pin outer ring',
            'default' => '#6CB9ED', 'col' => 3, 'max' => 9,
        ],
        'pin_inner_color' => [
            'tab' => 'map', 'type' => 'text', 'label' => 'Pin inner fill',
            'default' => '#2F95D9', 'col' => 3, 'max' => 9,
        ],
        'pin_active_color' => [
            'tab' => 'map', 'type' => 'text', 'label' => 'Selected pin fill',
            'default' => '#E31E24', 'col' => 3, 'max' => 9,
        ],
        'pin_text_color' => [
            'tab' => 'map', 'type' => 'text', 'label' => 'Pin count colour',
            'default' => '#FFFFFF', 'col' => 3, 'max' => 9,
        ],

        // ---------------------------------------------------- the search pin
        // The single pin dropped where a visitor's search lands, which they
        // can then drag. Separate from the clustered site pins above: those
        // are drawn in code from the colours, this one is a whole SVG so the
        // shape itself can be replaced without a developer.
        'search_pin_svg' => [
            'tab' => 'map', 'type' => 'textarea', 'label' => 'Search pin artwork (SVG)',
            'help' => 'Leave EMPTY to use the built-in pin — that is the normal setting, and the map is never without a pointer. '
                .'To replace it, paste a complete <svg>…</svg> tag here. Three rules, or the pin will sit in the wrong place: '
                .'(1) the artwork must point DOWNWARDS, with the tip at the bottom-centre of the canvas — that tip is what marks the location; '
                .'(2) set the width and height below to match the artwork exactly; '
                .'(3) keep it simple and high-contrast, because it is drawn at roughly 28x40 pixels on a dark navy map — fine detail disappears and a light outline stops it vanishing against the background. '
                .'Only <svg>, <path>, <circle>, <ellipse>, <rect>, <polygon>, <g> and their attributes are allowed; scripts and external images are stripped for security. '
                .'Preview your change on the home page before leaving this screen.',
            'default' => '', 'col' => 12, 'max' => 20000,
        ],
        'search_pin_width' => [
            'tab' => 'map', 'type' => 'number', 'label' => 'Search pin width (px)',
            'help' => 'Must match the artwork above. Ignored while the pin is the built-in one.',
            'default' => '28', 'col' => 3, 'min' => 8, 'max' => 128,
        ],
        'search_pin_height' => [
            'tab' => 'map', 'type' => 'number', 'label' => 'Search pin height (px)',
            'help' => 'Must match the artwork above. The pin is anchored to the bottom-centre of this box.',
            'default' => '40', 'col' => 3, 'min' => 8, 'max' => 128,
        ],

        // -------------------------------------------------------------- behaviour
        'section_enabled' => [
            'tab' => 'behaviour', 'type' => 'toggle', 'label' => 'Show the finder on the home page',
            'default' => '1', 'col' => 4,
        ],
        'map_enabled' => [
            'tab' => 'behaviour', 'type' => 'toggle', 'label' => 'Enable the interactive map',
            'help' => 'Off = results list only. Instantly stops all Dynamic Maps billing.',
            'default' => '1', 'col' => 4,
        ],
        'map_load_mode' => [
            'tab' => 'behaviour', 'type' => 'select', 'label' => 'Map load trigger',
            'options' => ['scroll' => 'When scrolled into view', 'click' => 'Only after the visitor clicks'],
            'help' => 'Click-to-activate is the cheapest: no map load unless a visitor asks for it.',
            'default' => 'scroll', 'col' => 4,
        ],
        'autocomplete_enabled' => [
            'tab' => 'behaviour', 'type' => 'toggle', 'label' => 'Enable Google address autocomplete',
            'help' => 'Off = postcode and area-name matching still work, with zero Places billing.',
            'default' => '1', 'col' => 4,
        ],
        'autocomplete_min_chars' => [
            'tab' => 'behaviour', 'type' => 'number', 'label' => 'Autocomplete minimum characters',
            'default' => '3', 'min' => 2, 'max' => 10, 'col' => 4,
        ],
        'autocomplete_debounce_ms' => [
            'tab' => 'behaviour', 'type' => 'number', 'label' => 'Autocomplete debounce (ms)',
            'default' => '400', 'min' => 100, 'max' => 2000, 'col' => 4,
        ],
        'max_results' => [
            'tab' => 'behaviour', 'type' => 'number', 'label' => 'Maximum result cards',
            'default' => '12', 'min' => 1, 'max' => 60, 'col' => 4,
        ],
        'nearest_area_km' => [
            'tab' => 'behaviour', 'type' => 'number', 'label' => 'Nearest-area radius (km)',
            'help' => 'Beyond this distance a searched place counts as uncovered.',
            'default' => '60', 'min' => 5, 'max' => 500, 'col' => 4,
        ],
        'search_logging_enabled' => [
            'tab' => 'behaviour', 'type' => 'toggle', 'label' => 'Log searches for analytics',
            'default' => '1', 'col' => 4,
        ],
        'search_retention_days' => [
            'tab' => 'behaviour', 'type' => 'number', 'label' => 'Search log retention (days)',
            'default' => '180', 'min' => 7, 'max' => 3650, 'col' => 4,
        ],

        // ----------------------------------------------------------------- import
        'import_service_map' => [
            'tab' => 'import', 'type' => 'keyvalue', 'label' => 'CSV service type → service slug',
            'help' => 'One per line as: CSV Services Type = service-slug. Types not listed import as inactive.',
            'default' => "Guarding Services = manned-guarding-services\nMobile Services = mobile-security-patrols-services",
            'col' => 6,
        ],
        'import_test_patterns' => [
            'tab' => 'import', 'type' => 'lines', 'label' => 'Site-name phrases treated as test rows',
            'help' => 'Matched as whole phrases, case-insensitive. Matching sites import as inactive so nothing is lost. Keep phrases specific — a bare word like "demo" would also flag "Demolition".',
            'default' => "test site\ntest mobile\ntest internal\ndemo home\nsample ocs\nannual holiday\nevent site\nprm",
            'col' => 6,
        ],
        'import_geocode_enabled' => [
            'tab' => 'import', 'type' => 'toggle', 'label' => 'Geocode postcodes during import',
            'default' => '1', 'col' => 4,
        ],
        'import_google_fallback' => [
            'tab' => 'import', 'type' => 'toggle', 'label' => 'Use Google when postcodes.io cannot resolve',
            'help' => 'Free UK data is tried first; this fallback handles the last few rows only.',
            'default' => '1', 'col' => 4,
        ],
        'import_auto_feature' => [
            'tab' => 'import', 'type' => 'toggle', 'label' => 'Auto-pick each area\'s featured service',
            'help' => 'Chooses the service with the most sites, and never overwrites a manual choice.',
            'default' => '1', 'col' => 4,
        ],

        // ------------------------------------------------------------------ areas
        'postcode_area_map' => [
            'tab' => 'areas', 'type' => 'keyvalue', 'label' => 'Postcode area → city (and region)',
            'help' => 'One per line as: PREFIX = City | Region. Repeat a city across prefixes to merge them into one map area (all London codes do this).',
            'default' => '', 'col' => 12, 'rows' => 18,
            'seed_from_config' => 'service_finder.area_map',
        ],

        // ------------------------------------------------------------------- spam
        'lead_honeypot_field' => [
            'tab' => 'spam', 'type' => 'text', 'label' => 'Honeypot field name',
            'help' => 'Must NOT be "company" — the quote form has a real company input.',
            'default' => 'website', 'col' => 4, 'max' => 40,
        ],
        'lead_min_seconds' => [
            'tab' => 'spam', 'type' => 'number', 'label' => 'Minimum seconds before submit',
            'default' => '2', 'min' => 0, 'max' => 60, 'col' => 4,
        ],
        'lead_block_score' => [
            'tab' => 'spam', 'type' => 'number', 'label' => 'Spam block score',
            'default' => '5', 'min' => 1, 'max' => 50, 'col' => 4,
        ],
        'lead_captcha_enabled' => [
            'tab' => 'spam', 'type' => 'toggle', 'label' => 'Require captcha on the quote form',
            'default' => '0', 'col' => 4,
        ],
        'lead_rate_limit' => [
            'tab' => 'spam', 'type' => 'number', 'label' => 'Quote submissions per minute per IP',
            'default' => '5', 'min' => 1, 'max' => 120, 'col' => 4,
        ],
        'search_rate_limit' => [
            'tab' => 'spam', 'type' => 'number', 'label' => 'Search logs per minute per IP',
            'default' => '30', 'min' => 1, 'max' => 600, 'col' => 4,
        ],
        'lead_blocked_keywords' => [
            'tab' => 'spam', 'type' => 'lines', 'label' => 'Blocked keywords',
            'help' => 'Submissions containing any of these are stored as spam and rejected.',
            'default' => '', 'col' => 6,
        ],

        // ---------------------------------------------------------- notifications
        'lead_notifications_enabled' => [
            'tab' => 'notifications', 'type' => 'toggle', 'label' => 'Email admins on a new quote request',
            'default' => '1', 'col' => 4,
        ],
        'lead_recipients' => [
            'tab' => 'notifications', 'type' => 'text', 'label' => 'Recipient override (comma separated)',
            'help' => 'Leave blank to use ADMIN_INQUIRY_RECIPIENTS from the environment.',
            'default' => '', 'col' => 8, 'max' => 500,
        ],
        'lead_subject_template' => [
            'tab' => 'notifications', 'type' => 'text', 'label' => 'Notification subject',
            'default' => 'New quote request — {service} in {area}',
            'tokens' => ['{service}', '{area}', '{name}'], 'col' => 12, 'max' => 190,
        ],
    ];

    /**
     * Every setting, resolved against its default.
     *
     * List and key/value fields are stored as plain newline text and parsed
     * on read, so SiteSetting::ARRAY_KEYS needs no finder entries.
     */
    public static function all(): array
    {
        $resolved = [];

        foreach (self::FIELDS as $key => $field) {
            $resolved[$key] = self::get($key);
        }

        return $resolved;
    }

    /** One setting, resolved against its default and cast by field type. */
    public static function get(string $key): mixed
    {
        $field = self::FIELDS[$key] ?? null;

        if (! $field) {
            return null;
        }

        $stored = SiteSetting::get(self::PREFIX.$key);

        if ($stored === null || $stored === '') {
            $stored = self::defaultFor($key);
        }

        return self::cast($stored, $field);
    }

    /** Raw stored string (or the default) — what the configurator form shows. */
    public static function raw(string $key): string
    {
        $stored = SiteSetting::get(self::PREFIX.$key);

        if ($stored === null || $stored === '') {
            return (string) self::defaultFor($key);
        }

        return is_array($stored) ? implode("\n", $stored) : (string) $stored;
    }

    /**
     * Defaults may be seeded from a config map (the postcode area table is
     * far too long to inline as a literal default).
     */
    protected static function defaultFor(string $key): string
    {
        $field = self::FIELDS[$key] ?? [];

        if (! empty($field['seed_from_config'])) {
            $map = (array) config($field['seed_from_config'], []);
            $lines = [];

            foreach ($map as $prefix => $city) {
                $lines[] = $prefix.' = '.$city;
            }

            return implode("\n", $lines);
        }

        return (string) ($field['default'] ?? '');
    }

    protected static function cast(mixed $value, array $field): mixed
    {
        return match ($field['type'] ?? 'text') {
            'toggle' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'number' => str_contains((string) $value, '.') ? (float) $value : (int) $value,
            'lines' => self::toLines($value),
            'keyvalue' => self::toKeyValue($value),
            default => is_array($value) ? $value : (string) $value,
        };
    }

    public static function toLines(mixed $value): array
    {
        if (is_array($value)) {
            $lines = $value;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
        }

        return array_values(array_filter(array_map(
            static fn ($line) => trim((string) $line),
            $lines
        ), static fn ($line) => $line !== ''));
    }

    /**
     * Parses "KEY = value" lines. Values may carry a " | " suffix (used by the
     * postcode table for "City | Region"), which is preserved verbatim so
     * callers can split it themselves.
     */
    public static function toKeyValue(mixed $value): array
    {
        $pairs = [];

        foreach (self::toLines($value) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$k, $v] = array_map('trim', explode('=', $line, 2));

            if ($k === '' || $v === '') {
                continue;
            }

            $pairs[$k] = $v;
        }

        return $pairs;
    }

    /**
     * Postcode prefix => ['city' => string, 'region' => ?string].
     * Falls back to the config map when the setting has been blanked.
     */
    public static function postcodeAreaMap(): array
    {
        $pairs = self::get('postcode_area_map');

        if (empty($pairs)) {
            $pairs = collect((array) config('service_finder.area_map', []))->all();
        }

        $map = [];

        foreach ($pairs as $prefix => $value) {
            $parts = array_map('trim', explode('|', (string) $value, 2));

            $map[strtoupper(trim((string) $prefix))] = [
                'city' => $parts[0] ?? '',
                'region' => ($parts[1] ?? '') !== '' ? $parts[1] : null,
            ];
        }

        return array_filter($map, static fn ($entry) => $entry['city'] !== '');
    }

    /** CSV "Services Type" (lowercased) => service slug. */
    public static function importServiceMap(): array
    {
        $pairs = self::get('import_service_map');

        if (empty($pairs)) {
            $pairs = (array) config('service_finder.import.service_type_map', []);
        }

        $map = [];

        foreach ($pairs as $type => $slug) {
            $map[strtolower(trim((string) $type))] = trim((string) $slug);
        }

        return $map;
    }

    /** Recipient list for lead notifications, admin override first. */
    public static function leadRecipients(): array
    {
        $override = (string) self::get('lead_recipients');

        $emails = $override !== ''
            ? preg_split('/[,;\s]+/', $override)
            : (array) config('inquiries.recipients', []);

        return array_values(array_filter(
            array_map(static fn ($email) => trim((string) $email), $emails ?: []),
            static fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }

    /** Replaces {tokens} in a template with the supplied values. */
    /**
     * Reduce pasted SVG to a safe subset before it is stored.
     *
     * This markup is authored in the admin and then rendered on every visitor's
     * map, so it is a stored-XSS surface: a <script>, an onload= handler or a
     * javascript: href pasted here would run in the visitor's browser. Rather
     * than blocklisting the dangerous cases — which is a losing game — only
     * known-safe drawing elements and attributes survive.
     *
     * Returns an empty string when the input is not usable SVG at all, which
     * the map reads as "use the bundled pin", so a bad paste degrades to the
     * default rather than to a blank marker.
     */
    public static function sanitiseSvg(?string $svg): string
    {
        $svg = trim((string) $svg);

        if ($svg === '') {
            return '';
        }

        $allowedTags = ['svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'polygon', 'polyline', 'line', 'defs', 'title', 'desc'];
        $allowedAttrs = [
            'viewbox', 'width', 'height', 'xmlns', 'fill', 'stroke', 'stroke-width', 'stroke-linecap',
            'stroke-linejoin', 'stroke-dasharray', 'opacity', 'fill-opacity', 'stroke-opacity',
            'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'points',
            'transform', 'role', 'aria-label', 'fill-rule', 'clip-rule',
        ];

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $document->documentElement || strtolower($document->documentElement->nodeName) !== 'svg') {
            return '';
        }

        $walk = static function (\DOMNode $node) use (&$walk, $allowedTags, $allowedAttrs): void {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof \DOMComment) {
                    continue;
                }

                if (! $child instanceof \DOMElement) {
                    continue;
                }

                if (! in_array(strtolower($child->nodeName), $allowedTags, true)) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                    $name = strtolower($attribute->nodeName);
                    $value = $attribute->nodeValue ?? '';

                    // Anything not on the list, any event handler, and any
                    // value that could resolve to a URL.
                    if (! in_array($name, $allowedAttrs, true)
                        || str_starts_with($name, 'on')
                        || preg_match('/(javascript:|data:text|url\s*\()/i', $value)) {
                        $child->removeAttribute($attribute->nodeName);
                    }
                }

                $walk($child);
            }
        };

        // The root's own attributes are filtered the same way as its children.
        foreach (iterator_to_array($document->documentElement->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowedAttrs, true) || str_starts_with($name, 'on')) {
                $document->documentElement->removeAttribute($attribute->nodeName);
            }
        }

        $walk($document->documentElement);

        return trim((string) $document->saveXML($document->documentElement));
    }

    public static function render(string $key, array $tokens = []): string
    {
        $template = (string) self::get($key);

        foreach ($tokens as $token => $value) {
            $template = str_replace('{'.$token.'}', (string) $value, $template);
        }

        return $template;
    }
}
