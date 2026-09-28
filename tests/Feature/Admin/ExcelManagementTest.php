<?php

namespace Tests\Feature\Admin;

use App\Models\ExcelExport;
use App\Models\ExcelImport;
use App\Models\Category;
use App\Models\Service;
use App\Services\ExcelManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcelManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_search_returns_only_active_records(): void
    {
        $this->actingAsAdmin();

        Service::factory()->create(['title' => 'Active Patrol', 'status' => true]);
        Service::factory()->create(['title' => 'Inactive Patrol', 'status' => false]);

        $response = $this->getJson(route('admin.ajax.search', ['services', 'q' => 'Patrol']));

        $response->assertOk();
        $labels = collect($response->json('results'))->pluck('text')->all();

        $this->assertContains('Active Patrol', $labels);
        $this->assertNotContains('Inactive Patrol', $labels);
    }

    public function test_excel_import_validation_errors_do_not_write_any_rows(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        Storage::put('excel/imports/invalid.csv', "id,title,status\n1,,1\n");

        $import = ExcelImport::query()->create([
            'resource' => 'services',
            'original_filename' => 'invalid.csv',
            'stored_path' => 'excel/imports/invalid.csv',
            'lookup_field' => 'title',
            'mode' => 'update_or_create',
            'status' => 'pending',
        ]);

        app(ExcelManagementService::class)->runImport($import);

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertSame(1, $import->failed_rows);
        $this->assertDatabaseMissing('services', ['id' => 1]);
    }

    public function test_excel_export_applies_active_scope_and_stores_file_metadata(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');

        Service::factory()->create(['title' => 'Export Active', 'status' => true]);
        Service::factory()->create(['title' => 'Export Inactive', 'status' => false]);

        $export = ExcelExport::query()->create([
            'resource' => 'services',
            'filename' => 'services.xlsx',
            'status' => 'pending',
            'columns' => ['id', 'title', 'status'],
            'filters' => [],
        ]);

        app(ExcelManagementService::class)->runExport($export);

        $export->refresh();
        $this->assertSame('completed', $export->status);
        $this->assertSame(1, $export->exported_rows);
        Storage::assertExists($export->stored_path);
    }

    public function test_excel_import_dry_run_validates_without_writing_rows(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        Storage::put('excel/imports/valid.csv', "title,status\nDry Run Service,1\n");

        $import = ExcelImport::query()->create([
            'resource' => 'services',
            'original_filename' => 'valid.csv',
            'stored_path' => 'excel/imports/valid.csv',
            'lookup_field' => 'title',
            'mode' => 'update_or_create',
            'dry_run' => true,
            'status' => 'pending',
        ]);

        app(ExcelManagementService::class)->runImport($import);

        $import->refresh();
        $this->assertSame('validated', $import->status);
        $this->assertDatabaseMissing('services', ['title' => 'Dry Run Service']);
    }

    public function test_excel_relation_import_syncs_related_records_transactionally(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');

        $service = Service::factory()->create(['title' => 'Relation Target', 'status' => true]);
        $category = Category::factory()->service()->create(['status' => true]);
        Storage::put('excel/imports/relations.csv', "title,category_id\nRelation Target,{$category->id}\n");

        $import = ExcelImport::query()->create([
            'resource' => 'services',
            'original_filename' => 'relations.csv',
            'stored_path' => 'excel/imports/relations.csv',
            'lookup_field' => 'title',
            'mode' => 'update_only',
            'status' => 'pending',
            'metadata' => ['operation' => 'relations'],
        ]);

        app(ExcelManagementService::class)->runImport($import);

        $import->refresh();
        $this->assertSame('completed', $import->status);
        $this->assertTrue($service->fresh()->categories()->whereKey($category->id)->exists());
    }

    public function test_excel_relation_import_reports_missing_related_ids_without_syncing(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');

        $service = Service::factory()->create(['title' => 'Relation Invalid', 'status' => true]);
        Storage::put('excel/imports/bad-relations.csv', "title,category_id\nRelation Invalid,999999\n");

        $import = ExcelImport::query()->create([
            'resource' => 'services',
            'original_filename' => 'bad-relations.csv',
            'stored_path' => 'excel/imports/bad-relations.csv',
            'lookup_field' => 'title',
            'mode' => 'update_only',
            'status' => 'pending',
            'metadata' => ['operation' => 'relations'],
        ]);

        app(ExcelManagementService::class)->runImport($import);

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertSame(1, $import->failed_rows);
        $this->assertSame(0, $service->fresh()->categories()->count());
    }
}
