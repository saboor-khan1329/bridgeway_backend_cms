{{--
    Presentation fixes scoped to the Service Finder admin screens.

    Loaded once from the module nav partial, so it applies everywhere in the
    module and nowhere else. Kept as a pushed style block rather than a build
    asset to match how the rest of this admin is put together.
--}}
@push('head')
<style>
    /* ---------------------------------------------------------- module nav */
    .sf-admin-nav {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .sf-admin-nav .nav { flex-wrap: nowrap; min-width: max-content; }
    .sf-admin-nav .nav-link { white-space: nowrap; }

    @media (min-width: 992px) {
        .sf-admin-nav .nav { flex-wrap: wrap; min-width: 0; }
    }

    /* ------------------------------------------------------ health notices */
    /*
        Bootstrap's solid-fill alerts shout equally loudly whatever they say,
        which makes a routine "all clear" look like an incident. These read as
        a status list instead: a tinted row with a coloured spine, severity
        carried by that spine rather than by a block of saturated colour.
    */
    .sf-health .alert {
        border: 0;
        border-left: 3px solid #adb5bd;
        border-radius: 6px;
        background: #f4f6f8;
        color: #1f2d3d;
        gap: 12px;
    }
    .sf-health .alert-danger  { background: #fdecee; border-left-color: #dc3545; }
    .sf-health .alert-warning { background: #fdf7e7; border-left-color: #e0a800; }
    .sf-health .alert-info    { background: #eef6fb; border-left-color: #17a2b8; }
    .sf-health .alert-success { background: #e9f7ef; border-left-color: #28a745; }

    /* Readable on the tint, and unambiguously a control. */
    .sf-health .alert .btn {
        background: #fff;
        border-color: rgba(0, 0, 0, 0.12);
        color: #1f2d3d;
        font-weight: 600;
        white-space: nowrap;
        box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    }
    .sf-health .alert .btn:hover {
        background: #1f2d3d;
        border-color: #1f2d3d;
        color: #fff;
    }

    @media (max-width: 575px) {
        .sf-health .alert {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 8px;
        }
    }

    /* ------------------------------------------------- page meta / headings */
    .sf-page-meta {
        text-align: right;
        line-height: 1.4;
    }
    @media (max-width: 767px) {
        .sf-page-meta { text-align: left; }
    }

    /* ------------------------------------------------------ coverage editor */
    .coverage-row {
        background: #fff;
        border-color: #e3e6ea !important;
    }
    .coverage-row + .coverage-row { margin-top: 14px; }

    /* The control cluster must wrap cleanly instead of squashing the title. */
    .coverage-row .d-flex.flex-wrap { row-gap: 8px; }
    .coverage-row .btn { white-space: nowrap; }
    .coverage-row textarea { resize: vertical; min-height: 62px; }

    /* Select2 inside a card needs to match Bootstrap's small controls. */
    .coverage-row .select2-container { width: 100% !important; }
    .coverage-row .select2-container--default .select2-selection--multiple {
        border-color: #ced4da;
        min-height: 62px;
    }

    @media (max-width: 767px) {
        .coverage-row .d-flex.flex-wrap > strong { flex: 1 0 100%; }
    }

    /* ------------------------------------------------------------- listings */
    /* Long tables scroll inside their card rather than widening the page. */
    .admin-index-table { min-width: 720px; }
    .card-body.table-responsive { overflow-x: auto; }

    .sf-bulk-bar {
        position: sticky;
        top: 0;
        z-index: 5;
    }
    @media (max-width: 767px) {
        .sf-bulk-bar { position: static; }
    }

    /* --------------------------------------------------------- configurator */
    .sf-settings .nav-tabs { flex-wrap: wrap; row-gap: 2px; }
    .sf-settings .form-text { font-size: 12px; line-height: 1.45; }

    /*
        Every toggle on a tab, as one full-width list — not the 2-up grid used
        for text/select fields below it. Two switches side by side only look
        aligned when their help text happens to be the same length; the
        moment one wraps and its neighbour doesn't, that pair reads as
        scattered even though each row is technically on the same line. A
        single column with the switch pinned to a fixed right-hand edge
        cannot drift out of line at any text length or screen width.
    */
    .sf-settings .sf-toggle-list {
        border: 1px solid #e3e6ea;
        border-radius: 6px;
        overflow: hidden;
    }
    .sf-settings .sf-toggle-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        padding: 14px 16px;
        background: #fff;
    }
    .sf-settings .sf-toggle-row + .sf-toggle-row { border-top: 1px solid #eef0f2; }
    .sf-settings .sf-toggle-text { flex: 1 1 auto; min-width: 0; }
    .sf-settings .sf-toggle-switch {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        padding-left: 2.5em; /* Bootstrap's switch reserves this via a negative
                                 margin on the input; restoring it here keeps
                                 the switch itself clear of the divider. */
    }
    @media (max-width: 575px) {
        .sf-settings .sf-toggle-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        .sf-settings .sf-toggle-switch { padding-left: 1.5em; }
    }
    .sf-settings textarea.font-monospace { font-size: 13px; line-height: 1.5; }
    .sf-settings .form-control-color {
        width: 46px;
        padding: 4px;
        flex: 0 0 auto;
        cursor: pointer;
    }
    /*
        This was `position: sticky; bottom: 0`, meant to keep Save reachable
        on the longer tabs. Sticky measures itself against the whole page's
        scroll, not "how much of this card is left to see" — on any tab
        taller than one screen (most of them), that pulled the footer up over
        the tail end of its own card's fields immediately, at scroll position
        zero, rather than only once a visitor actually scrolled that far. A
        plain, in-flow footer cannot overlap anything above it; reaching Save
        costs one scroll, which is the safer trade.
    */
    .sf-settings .card-footer {
        background: #f8f9fa;
        border-top: 1px solid #e3e6ea;
    }
    @media (max-width: 767px) {
        .sf-settings .card-footer {
            display: flex;
            flex-direction: column-reverse;
            gap: 8px;
        }
        .sf-settings .card-footer .btn { width: 100%; }
    }

    /* ----------------------------------------------------------- API usage */
    .sf-usage-note { font-size: 13px; line-height: 1.5; }

    .sf-usage-chart-wrap { width: 100%; }
    .sf-usage-chart { width: 100%; height: auto; display: block; }

    .sf-usage-legend {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #495057;
    }
    .sf-usage-legend i {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 2px;
    }

    /* -------------------------------------------------------------- filters */
    .card .row.g-2 > [class*="col-"] { min-width: 0; }
    @media (max-width: 575px) {
        .card .row.g-2 > .col-md-auto { width: 100%; }
        .card .row.g-2 > .col-md-auto .btn { width: 100%; margin-bottom: 4px; }
    }

    /* ----------------------------------------------------------- stat cards */
    @media (max-width: 575px) {
        .small-box .h3, .card .h3 { font-size: 1.5rem; }
    }

    /* ---------------------------------------------------- placement summary */
    .sf-placement-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 999px;
        border: 1px solid transparent;
        font-size: 13px;
        line-height: 1.2;
        white-space: nowrap;
    }
    .sf-placement-pill strong { font-weight: 600; }
    .sf-placement-pill.is-live {
        background: #e8f6ee;
        border-color: #b7e2c8;
        color: #1e7e46;
    }
    .sf-placement-pill.is-off {
        background: #f4f5f7;
        border-color: #e0e3e7;
        color: #6c757d;
    }
    @media (max-width: 575px) {
        .sf-placements .card-body { flex-direction: column; align-items: stretch; }
        .sf-placement-pill { justify-content: space-between; }
    }

    /* -------------------------------------------------------- touch targets */
    /*
        Desktop keeps its dense rows — an operator scanning 900 sites wants
        rows, not padding — but btn-xs at 24px is below the point where a
        pointer lands reliably, so it gets a small floor everywhere.
    */
    .content-wrapper .btn-xs {
        min-height: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* Tablets and phones are touch, so the same controls become thumb targets. */
    @media (max-width: 991px) {
        .content-wrapper .btn-xs,
        .content-wrapper .btn-sm {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .content-wrapper .table .btn-xs,
        .content-wrapper .table .btn-sm {
            min-height: 32px;
        }
    }
</style>
@endpush
