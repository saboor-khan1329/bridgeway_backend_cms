@php
    $authUser = auth()->user();
    $appName = config('app.name', 'Bridgeway Digital CMS');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | {{ $appName }}</title>

    <link rel="icon" type="image/webp" href="{{ asset('images/favicon-32x32.webp') }}">
    <link rel="shortcut icon" href="{{ asset('images/favicon-32x32.webp') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon-32x32.webp') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend+Deca:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

    <style>
        :root {
            --bd-primary: #FF763A;
            --bd-primary-hover: #e66228;
            --bd-primary-light: rgba(255, 118, 58, 0.12);
            --bd-dark: #121d28;
            --bd-dark-card: #182432;
            --bd-bg: #F6F9FC;
            --bd-text: #213343;
            --bd-border: #E2E8F0;
        }

        body {
            font-family: 'Lexend Deca', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            color: var(--bd-text);
            background-color: var(--bd-bg);
        }

        .main-header {
            background-color: #ffffff !important;
            border-bottom: 1px solid var(--bd-border) !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .main-sidebar {
            background-color: var(--bd-dark) !important;
            box-shadow: 2px 0 10px rgba(0,0,0,0.08) !important;
        }

        .brand-link {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 0.65rem 0.8rem !important;
            min-height: 57px;
        }

        .brand-link:hover {
            opacity: 0.95;
        }

        .content-wrapper {
            background-color: var(--bd-bg) !important;
            padding: 1.5rem !important;
        }

        /* Sidebar Navigation */
        .nav-sidebar .nav-header {
            color: #8c9ba5 !important;
            font-size: 0.72rem !important;
            letter-spacing: 0.8px;
            font-weight: 700;
            padding: 1rem 1rem 0.4rem 1rem !important;
        }

        .nav-sidebar .nav-item .nav-link {
            color: #d1d8de !important;
            border-radius: 6px;
            margin: 2px 8px;
            padding: 0.55rem 0.9rem;
            transition: all 0.18s ease;
            font-size: 0.88rem;
        }

        .nav-sidebar .nav-item .nav-link i.nav-icon {
            width: 1.4rem;
            margin-right: 0.5rem;
            color: #9cb0be;
            transition: color 0.18s ease;
        }

        .nav-sidebar .nav-item .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.06) !important;
            color: #ffffff !important;
        }

        .nav-sidebar .nav-item .nav-link:hover i.nav-icon {
            color: var(--bd-primary) !important;
        }

        .nav-sidebar .nav-item .nav-link.active {
            background-color: var(--bd-primary-light) !important;
            color: var(--bd-primary) !important;
            font-weight: 600 !important;
            border-left: 3px solid var(--bd-primary) !important;
        }

        .nav-sidebar .nav-item .nav-link.active i.nav-icon {
            color: var(--bd-primary) !important;
        }

        /* Cards & Containers */
        .card {
            border: 1px solid var(--bd-border) !important;
            border-radius: 10px !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03) !important;
            background: #ffffff;
            overflow: hidden;
        }

        .card-header {
            background-color: #ffffff !important;
            border-bottom: 1px solid var(--bd-border) !important;
            padding: 0.9rem 1.25rem;
        }

        .card-footer {
            background-color: #ffffff !important;
            border-top: 1px solid var(--bd-border) !important;
            padding: 0.9rem 1.25rem;
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--bd-primary) !important;
            border-color: var(--bd-primary) !important;
            color: #ffffff !important;
            font-weight: 500;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(255, 118, 58, 0.25);
            transition: all 0.18s ease;
        }

        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--bd-primary-hover) !important;
            border-color: var(--bd-primary-hover) !important;
            box-shadow: 0 3px 8px rgba(255, 118, 58, 0.35);
        }

        .btn-outline-primary {
            color: var(--bd-primary) !important;
            border-color: var(--bd-primary) !important;
            border-radius: 6px;
        }

        .btn-outline-primary:hover {
            background-color: var(--bd-primary) !important;
            color: #ffffff !important;
        }

        /* Pills and Badges */
        .badge {
            font-weight: 600;
            padding: 0.35em 0.65em;
            border-radius: 50rem;
        }

        .select2-container { width: 100% !important; }
        .select2-container .select2-selection--single,
        .select2-container .select2-selection--multiple {
            min-height: calc(2.25rem + 2px);
            border: 1px solid #ced4da; border-radius: .375rem; background: #fff;
        }
        .image-preview img { max-width: 180px; max-height: 120px; border-radius: 6px; border: 1px solid #e5e7eb; }
        .stat-card { transition: transform .15s ease, box-shadow .15s ease; border-radius: 10px; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.06); }
        textarea.js-rich-editor { min-height: 280px; }
    </style>
    @stack('vendor-scripts')
    @stack('head')
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{ route('admin.dashboard') }}" class="nav-link font-weight-medium">Dashboard</a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}" target="_blank" class="nav-link text-muted">
                    <i class="fas fa-external-link-alt fa-xs me-1"></i> Visit Website
                </a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item d-flex align-items-center me-3">
                <span class="badge bg-light text-dark border px-2 py-1">
                    <i class="fas fa-user-circle me-1 text-primary" style="color: #FF763A !important;"></i> {{ $authUser?->name }}
                </span>
            </li>
            <li class="nav-item">
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger" type="submit" style="border-radius: 6px;">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </button>
                </form>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('admin.dashboard') }}" class="brand-link text-center d-flex align-items-center justify-content-center">
            <img src="{{ asset('images/white-logo.svg') }}" alt="Bridgeway Digital" style="max-height: 25px; width: auto;" onerror="this.onerror=null; this.style.display='none'; document.getElementById('brand-fallback').style.display='inline';">
            <span id="brand-fallback" class="brand-text font-weight-bold" style="display:none; color: #fff; font-size: 0.95rem; letter-spacing: 0.3px;">{{ $appName }}</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    @php
                        $isActive = fn (string ...$names) => collect($names)->contains(fn ($n) => request()->routeIs($n)) ? 'active' : '';
                        $excelOpen = request()->routeIs('admin.excel.*') ? 'menu-open' : '';
                    @endphp

                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ $isActive('admin.dashboard') }}">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    <li class="nav-header">SERVICES & SECTIONS</li>
                    <li class="nav-item">
                        <a href="{{ route('admin.amazon-services.index') }}" class="nav-link {{ $isActive('admin.amazon-services.*') }}">
                            <i class="nav-icon fab fa-amazon"></i>
                            <p>Amazon Services</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.development-services.index') }}" class="nav-link {{ $isActive('admin.development-services.*', 'admin.service-pages.*') }}">
                            <i class="nav-icon fas fa-laptop-code"></i>
                            <p>Development Services <span class="d-none">Service Pages</span></p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.reusable-sections.index') }}" class="nav-link {{ $isActive('admin.reusable-sections.*') }}">
                            <i class="nav-icon fas fa-layer-group"></i>
                            <p>Reusable Sections</p>
                        </a>
                    </li>

                    <li class="nav-header">CONTENT & MEDIA</li>
                    <li class="nav-item">
                        <a href="{{ route('admin.content-pages.index') }}" class="nav-link {{ $isActive('admin.content-pages.*') }}">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>Content Pages</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('admin.categories.index', 'blog') }}" class="nav-link {{ request()->is('admin/categories/blog*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-sitemap"></i>
                            <p>Blog Categories</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.blogs.index') }}" class="nav-link {{ $isActive('admin.blogs.*') }}">
                            <i class="nav-icon fas fa-blog"></i>
                            <p>Blogs</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="{{ route('admin.navigation.index') }}" class="nav-link {{ $isActive('admin.navigation.*') }}">
                            <i class="nav-icon fas fa-compass"></i>
                            <p>Navigation</p>
                        </a>
                    </li>

                    <li class="nav-header">FORMS & LEADS</li>
                    <li class="nav-item">
                        <a href="{{ route('admin.forms.index') }}" class="nav-link {{ $isActive('admin.forms.*') }}">
                            <i class="nav-icon fas fa-clipboard-list"></i>
                            <p>Forms Management</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.inquiries.index') }}" class="nav-link {{ $isActive('admin.inquiries.*') }}">
                            <i class="nav-icon fas fa-inbox"></i>
                            <p>Inquiries</p>
                        </a>
                    </li>

                    {{-- Hidden entirely when the environment kill switch is off. --}}
                    @if (config('service_finder.enabled', true))
                    @php
                        $finderRoutes = [
                            'admin.finder.dashboard', 'admin.finder-areas.*', 'admin.finder-coverage.*',
                            'admin.finder-sites.*', 'admin.finder-import.*', 'admin.finder-leads.*',
                            'admin.finder-searches.*', 'admin.finder-settings.*',
                        ];
                        $finderOpen = request()->routeIs($finderRoutes) ? 'menu-open' : '';
                        $newFinderLeads = \Illuminate\Support\Facades\Schema::hasTable('finder_leads')
                            ? \App\Models\FinderLead::query()->new()->count()
                            : 0;
                    @endphp
                    <li class="nav-item {{ $finderOpen }}">
                        <a href="#" class="nav-link {{ $isActive(...$finderRoutes) }}">
                            <i class="nav-icon fas fa-map-location-dot"></i>
                            <p>
                                Service Finder
                                @if ($newFinderLeads > 0)
                                    <span class="badge badge-danger right">{{ $newFinderLeads }}</span>
                                @endif
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('admin.finder.dashboard') }}" class="nav-link {{ $isActive('admin.finder.dashboard') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Overview</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.finder-areas.index') }}" class="nav-link {{ $isActive('admin.finder-areas.*') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Coverage Areas</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.finder-coverage.bulk') }}" class="nav-link {{ $isActive('admin.finder-coverage.*') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Roll Out a Service</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.finder-sites.index') }}" class="nav-link {{ $isActive('admin.finder-sites.*') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Sites</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.finder-import.edit') }}" class="nav-link {{ $isActive('admin.finder-import.*') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Import Sites</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.finder-leads.index') }}" class="nav-link {{ $isActive('admin.finder-leads.*') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>
                                        Quote Requests
                                        @if ($newFinderLeads > 0)
                                            <span class="badge badge-danger right">{{ $newFinderLeads }}</span>
                                        @endif
                                    </p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.finder-searches.index') }}" class="nav-link {{ $isActive('admin.finder-searches.*') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Search Analytics</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.finder-settings.edit') }}" class="nav-link {{ $isActive('admin.finder-settings.*') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Configurator</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @endif

                    <li class="nav-header">SEO & VISIBILITY</li>
                    <li class="nav-item">
                        <a href="{{ route('admin.seo.configurator.edit') }}" class="nav-link {{ $isActive('admin.seo.*') }}">
                            <i class="nav-icon fas fa-search"></i>
                            <p>SEO Configurator</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.redirects.index') }}" class="nav-link {{ $isActive('admin.redirects.*') }}">
                            <i class="nav-icon fas fa-route"></i>
                            <p>Redirects 301/302/410</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.slugs.index') }}" class="nav-link {{ $isActive('admin.slugs.*') }}">
                            <i class="nav-icon fas fa-link"></i>
                            <p>Slugs</p>
                        </a>
                    </li>

                    <li class="nav-header">SYSTEM</li>
                    <li class="nav-item {{ $excelOpen }}">
                        <a href="#" class="nav-link {{ $isActive('admin.excel.*') }}">
                            <i class="nav-icon fas fa-file-excel"></i>
                            <p>
                                Excel Management
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('admin.excel.index') }}#excel-dashboard" class="nav-link {{ $isActive('admin.excel.index') }}">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Dashboard</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.excel.index') }}#excel-imports" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Imports</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('admin.excel.index') }}#excel-exports" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Exports</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @if ($authUser?->isRootAdmin())
                    <li class="nav-item">
                        <a href="{{ route('admin.users.index') }}" class="nav-link {{ $isActive('admin.users.*') }}">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Users</p>
                        </a>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a href="{{ route('admin.file-manager.index') }}" class="nav-link {{ $isActive('admin.file-manager.*') }}">
                            <i class="nav-icon fas fa-folder-open"></i>
                            <p>File Manager</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.system.health') }}" class="nav-link {{ $isActive('admin.system.health') }}">
                            <i class="nav-icon fas fa-heartbeat"></i>
                            <p>System Health</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.settings.edit') }}" class="nav-link {{ $isActive('admin.settings.*') }}">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Site Settings</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper p-3">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-left: 4px solid #28a745 !important;">
                <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert" style="border-left: 4px solid #dc3545 !important;">
                <i class="fas fa-exclamation-circle me-1"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <footer class="main-footer text-center small py-3" style="border-top: 1px solid var(--bd-border); background: #ffffff;">
        <span class="text-muted"><strong>Bridgeway Digital CMS</strong> &copy; {{ date('Y') }} &bull; All rights reserved.</span>
    </footer>
</div>

@include('admin.file-manager.modal')

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/select2.full.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.$ && $.fn.select2) {
            $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
            $('.select2-ajax').each(function () {
                const $el = $(this);
                let ajaxParams = {};
                try {
                    ajaxParams = JSON.parse($el.attr('data-ajax-params') || '{}') || {};
                } catch (e) {
                    ajaxParams = {};
                }
                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    minimumInputLength: 0,
                    ajax: {
                        url: $el.data('ajax-url'),
                        dataType: 'json',
                        delay: 200,
                        data: params => ({ ...ajaxParams, q: params.term || '', limit: 25 }),
                        processResults: data => ({ results: data.results || [] }),
                        cache: true,
                    },
                });
            });
        }

        document.querySelectorAll('[data-confirm-submit]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                if (!confirm('Are you sure you want to ' + (btn.dataset.confirmSubmit || 'continue') + '?')) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    });
</script>

@stack('textareajs')
@stack('imagejs')
@stack('scripts')
</body>
</html>
