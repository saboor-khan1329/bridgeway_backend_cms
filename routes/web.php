<?php

use App\Http\Controllers\Admin\AjaxSearchController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\OnboardingFormController;
use App\Http\Controllers\Admin\OnboardingSubmissionController;
use App\Http\Controllers\Admin\AmazonServiceController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\DevelopmentServiceController;
use App\Http\Controllers\Admin\ExcelManagementController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FileManagerController;
use App\Http\Controllers\Admin\FormManagementController;
use App\Http\Controllers\Admin\ReusableSectionController;
use App\Http\Controllers\Admin\FinderAreaController;
use App\Http\Controllers\Admin\FinderCoverageController;
use App\Http\Controllers\Admin\FinderDashboardController;
use App\Http\Controllers\Admin\FinderImportController;
use App\Http\Controllers\Admin\FinderLeadController;
use App\Http\Controllers\Admin\FinderSearchController;
use App\Http\Controllers\Admin\FinderSettingController;
use App\Http\Controllers\Admin\FinderSiteController;
use App\Http\Controllers\Admin\FinderUsageController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\NavigationMenuController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\ServiceContentPageController;
use App\Http\Controllers\Admin\SeoConfiguratorController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\SlugController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'index'])->name('login');
    Route::post('login', [AuthController::class, 'authenticate'])
        ->middleware('throttle:login')
        ->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('logout', [AuthController::class, 'logout']);
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('categories/{type}')->name('categories.')->whereIn('type', ['service', 'location', 'blog'])->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('create', [CategoryController::class, 'create'])->name('create');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::get('{category}', [CategoryController::class, 'show'])->name('show');
            Route::get('{category}/edit', [CategoryController::class, 'edit'])->name('edit');
            Route::put('{category}', [CategoryController::class, 'update'])->name('update');
            Route::delete('{category}', [CategoryController::class, 'destroy'])->name('destroy');
        });

        Route::resource('amazon-services', AmazonServiceController::class)
            ->parameters(['amazon-services' => 'contentPage'])
            ->except('show')
            ->names('amazon-services');

        Route::resource('development-services', DevelopmentServiceController::class)
            ->parameters(['development-services' => 'servicePage'])
            ->except('show')
            ->names('development-services');

        Route::resource('reusable-sections', ReusableSectionController::class)
            ->parameters(['reusable-sections' => 'contentPage'])
            ->except('show')
            ->names('reusable-sections');

        // Forms Management
        Route::get('forms', [FormManagementController::class, 'index'])->name('forms.index');
        Route::post('forms/settings', [FormManagementController::class, 'updateEmailSettings'])->name('forms.email-settings');
        Route::get('forms/{inquiry}', [FormManagementController::class, 'show'])->name('forms.show');
        Route::put('forms/{inquiry}/status', [FormManagementController::class, 'updateStatus'])->name('forms.status');
        Route::patch('forms/{inquiry}/status', [FormManagementController::class, 'updateStatus'])->name('forms.status.patch');
        Route::get('forms/{inquiry}/attachment', [FormManagementController::class, 'attachment'])->name('forms.attachment');
        Route::delete('forms/{inquiry}', [FormManagementController::class, 'destroy'])->name('forms.destroy');

        Route::resource('service-pages', ServiceContentPageController::class)
            ->parameters(['service-pages' => 'servicePage'])
            ->except('show')
            ->names('service-pages');
        Route::resource('content-pages', ContentPageController::class)
            ->parameters(['content-pages' => 'contentPage'])
            ->except('show')
            ->names('content-pages');
        Route::resource('sector-pages', ServiceController::class)
            ->parameters(['sector-pages' => 'service'])
            ->names('sector-pages');
        Route::resource('services', ServiceController::class)->names('services');
        Route::resource('locations', LocationController::class);

        /*
        | Service Finder — the home-page coverage map and its quote funnel.
        | Everything the tool needs is editable here: areas and their pins,
        | which services each area advertises, the imported site data behind
        | the counts, the leads it produces, and every copy string, threshold
        | and cost control in the configurator.
        */
        Route::get('service-finder', [FinderDashboardController::class, 'index'])->name('finder.dashboard');
        Route::post('service-finder/rebuild', [FinderDashboardController::class, 'rebuild'])->name('finder.rebuild');
        Route::post('service-finder/clear-cache', [FinderDashboardController::class, 'clearCache'])->name('finder.clear-cache');

        Route::resource('finder-areas', FinderAreaController::class)->except(['show']);
        Route::post('finder-areas/{finderArea}/recount', [FinderAreaController::class, 'recount'])->name('finder-areas.recount');
        Route::post('finder-areas/{finderArea}/geocode', [FinderAreaController::class, 'geocode'])->name('finder-areas.geocode');
        Route::get('finder-areas/{finderArea}/coverage', [FinderCoverageController::class, 'edit'])->name('finder-areas.coverage.edit');
        Route::put('finder-areas/{finderArea}/coverage', [FinderCoverageController::class, 'update'])->name('finder-areas.coverage.update');

        Route::get('finder-coverage/roll-out', [FinderCoverageController::class, 'bulkEdit'])->name('finder-coverage.bulk');
        Route::put('finder-coverage/roll-out', [FinderCoverageController::class, 'bulkUpdate'])->name('finder-coverage.bulk.update');

        Route::resource('finder-sites', FinderSiteController::class)->only(['index', 'edit', 'update', 'destroy']);
        Route::post('finder-sites/bulk', [FinderSiteController::class, 'bulk'])->name('finder-sites.bulk');

        Route::get('finder-import', [FinderImportController::class, 'edit'])->name('finder-import.edit');
        Route::post('finder-import', [FinderImportController::class, 'run'])->name('finder-import.run');

        Route::get('finder-leads/export', [FinderLeadController::class, 'export'])->name('finder-leads.export');
        Route::resource('finder-leads', FinderLeadController::class)->only(['index', 'show', 'destroy']);
        Route::patch('finder-leads/{finderLead}/status', [FinderLeadController::class, 'updateStatus'])->name('finder-leads.status');
        Route::post('finder-leads/{finderLead}/resend', [FinderLeadController::class, 'resend'])->name('finder-leads.resend');

        Route::get('finder-searches', [FinderSearchController::class, 'index'])->name('finder-searches.index');
        Route::post('finder-searches/prune', [FinderSearchController::class, 'prune'])->name('finder-searches.prune');

        Route::get('finder-usage', [FinderUsageController::class, 'index'])->name('finder-usage.index');

        Route::get('finder-settings', [FinderSettingController::class, 'edit'])->name('finder-settings.edit');
        Route::put('finder-settings', [FinderSettingController::class, 'update'])->name('finder-settings.update');
        Route::post('finder-settings/reset', [FinderSettingController::class, 'reset'])->name('finder-settings.reset');
        Route::resource('blogs', BlogController::class);
        Route::resource('pages', PageController::class);
        Route::resource('faqs', FaqController::class);
        Route::post('faqs/quick-create', [FaqController::class, 'quickCreate'])->name('faqs.quick-create');
        Route::resource('testimonials', TestimonialController::class);

        // Page Sections — the four admin-authored components on a service,
        // sector or location page. Its own sidebar entry lists every page that
        // can carry them and which are filled in; each record's edit form also
        // links straight to its own. {ownerType} is "service", "sector" or
        // "location" — the first two are both Services, kept apart only so the
        // editor can send an operator back where they came from.
        Route::resource('users', UserController::class);
        Route::resource('redirects', RedirectController::class);

        Route::get('excel', [ExcelManagementController::class, 'index'])->name('excel.index');
        Route::post('excel/import', [ExcelManagementController::class, 'import'])->name('excel.import');
        Route::post('excel/export', [ExcelManagementController::class, 'export'])->name('excel.export');
        Route::get('excel/templates/{resource}', [ExcelManagementController::class, 'template'])->name('excel.template');
        Route::get('excel/imports/{import}', [ExcelManagementController::class, 'showImport'])->name('excel.imports.show');
        Route::post('excel/imports/{import}/retry', [ExcelManagementController::class, 'retryImport'])->name('excel.imports.retry');
        Route::get('excel/imports/{import}/errors', [ExcelManagementController::class, 'downloadImportErrors'])->name('excel.imports.errors');
        Route::get('excel/exports/{export}', [ExcelManagementController::class, 'showExport'])->name('excel.exports.show');
        Route::post('excel/exports/{export}/retry', [ExcelManagementController::class, 'retryExport'])->name('excel.exports.retry');
        Route::get('excel/exports/{export}/download', [ExcelManagementController::class, 'download'])->name('excel.download');

        Route::get('file-manager', [FileManagerController::class, 'index'])->name('file-manager.index');
        Route::get('file-manager/items', [FileManagerController::class, 'items'])->name('file-manager.items');
        Route::post('file-manager/upload', [FileManagerController::class, 'upload'])->name('file-manager.upload');
        Route::post('file-manager/folders', [FileManagerController::class, 'storeFolder'])->name('file-manager.folders.store');
        Route::patch('file-manager/rename', [FileManagerController::class, 'rename'])->name('file-manager.rename');
        Route::delete('file-manager/delete', [FileManagerController::class, 'destroy'])->name('file-manager.destroy');

        Route::get('slugs', [SlugController::class, 'index'])->name('slugs.index');

        Route::get('inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
        Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
        Route::get('inquiries/{inquiry}/attachment', [InquiryController::class, 'attachment'])->name('inquiries.attachment');
        Route::patch('inquiries/{inquiry}/status', [InquiryController::class, 'updateStatus'])->name('inquiries.status');
        Route::delete('inquiries/{inquiry}', [InquiryController::class, 'destroy'])->name('inquiries.destroy');

        Route::get('settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SiteSettingController::class, 'update'])->name('settings.update');
        Route::get('seo/configurator', [SeoConfiguratorController::class, 'edit'])->name('seo.configurator.edit');
        Route::put('seo/configurator', [SeoConfiguratorController::class, 'update'])->name('seo.configurator.update');

        // Navigation management
        Route::prefix('navigation')->name('navigation.')->group(function () {
            Route::get('/', [NavigationMenuController::class, 'index'])->name('index');
            Route::get('/{location}', [NavigationMenuController::class, 'show'])->name('show');
            Route::put('/{location}/settings', [NavigationMenuController::class, 'updateSettings'])->name('settings');
            Route::post('/{location}/reorder', [NavigationMenuController::class, 'reorder'])->name('reorder');
            Route::get('/{location}/items/create', [NavigationMenuController::class, 'createItem'])->name('items.create');
            Route::post('/{location}/items', [NavigationMenuController::class, 'storeItem'])->name('items.store');
            Route::get('/{location}/items/{item}/edit', [NavigationMenuController::class, 'editItem'])->name('items.edit');
            Route::put('/{location}/items/{item}', [NavigationMenuController::class, 'updateItem'])->name('items.update');
            Route::delete('/{location}/items/{item}', [NavigationMenuController::class, 'destroyItem'])->name('items.destroy');
        });

        Route::get('ajax/search/{resource}', AjaxSearchController::class)->name('ajax.search');

        // System health
        Route::get('system/health',      [SystemHealthController::class, 'index'])->name('system.health');
        Route::get('system/health/json', [SystemHealthController::class, 'json'])->name('system.health.json');

        // Onboarding
        Route::prefix('onboarding')->name('onboarding.')->group(function () {

            Route::prefix('forms')->name('forms.')->group(function () {
                Route::get('/',                          [OnboardingFormController::class, 'index'])->name('index');
                Route::get('/create',                    [OnboardingFormController::class, 'create'])->name('create');
                Route::post('/',                         [OnboardingFormController::class, 'store'])->name('store');
                Route::get('/{form}/edit',               [OnboardingFormController::class, 'edit'])->name('edit');
                Route::put('/{form}',                    [OnboardingFormController::class, 'update'])->name('update');
                Route::post('/{form}/activate',          [OnboardingFormController::class, 'activate'])->name('activate');
                Route::delete('/{form}',                 [OnboardingFormController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('submissions')->name('submissions.')->group(function () {
                Route::get('/',                          [OnboardingSubmissionController::class, 'index'])->name('index');
                Route::get('/{submission}',              [OnboardingSubmissionController::class, 'show'])->name('show');
                Route::patch('/{submission}/status',     [OnboardingSubmissionController::class, 'updateStatus'])->name('status');
                Route::get('/{submission}/pdf',          [OnboardingSubmissionController::class, 'downloadPdf'])->name('pdf');
                Route::get('/{submission}/files/{file}', [OnboardingSubmissionController::class, 'downloadFile'])->name('file');
                Route::delete('/{submission}',           [OnboardingSubmissionController::class, 'destroy'])->name('destroy');
            });
        });
    });
});
