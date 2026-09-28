<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_manager_lists_files_and_usage(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        Storage::disk('public')->put('managed/pages/banner_desktop/example.png', 'abc');

        $response = $this->getJson(route('admin.file-manager.items', [
            'path' => 'managed/pages/banner_desktop',
            'images_only' => 1,
        ]));

        $response->assertOk();
        $response->assertJsonPath('current_path', 'managed/pages/banner_desktop');
        $response->assertJsonCount(1, 'files');
        $response->assertJsonPath('files.0.path', 'managed/pages/banner_desktop/example.png');
    }

    public function test_file_manager_enforces_quota_during_upload(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        config([
            'admin_file_manager.quota_bytes' => 1024,
            'admin_file_manager.max_upload_kb' => 10240,
        ]);

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post(route('admin.file-manager.upload'), [
                'path' => 'managed/tmp',
                'images_only' => 1,
                'files' => [
                    UploadedFile::fake()->create('large.png', 3, 'image/png'),
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('files');
    }

    public function test_file_manager_cannot_delete_a_referenced_asset(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        Storage::disk('public')->put('managed/pages/banner_desktop/used.png', 'image-bytes');

        $page = Page::create([
            'page_title' => 'Referenced Page',
            'status' => true,
        ]);

        $page->images()->create([
            'image_type' => 'banner_desktop',
            'path' => 'managed/pages/banner_desktop/used.png',
            'disk' => 'public',
            'alt' => 'Used image',
            'source' => 'selection',
        ]);

        $response = $this->deleteJson(route('admin.file-manager.destroy'), [
            'path' => 'managed/pages/banner_desktop/used.png',
        ]);

        $response->assertStatus(422);
        Storage::disk('public')->assertExists('managed/pages/banner_desktop/used.png');
    }
}
