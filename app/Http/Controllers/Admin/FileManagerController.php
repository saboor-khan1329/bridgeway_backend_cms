<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminFileManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileManagerController extends Controller
{
    public function __construct(private readonly AdminFileManagerService $fileManager)
    {
    }

    public function index()
    {
        return view('admin.file-manager.index', [
            'title' => 'File Manager',
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        return response()->json(
            $this->fileManager->list(
                (string) $request->query('path', ''),
                $request->boolean('images_only')
            )
        );
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:500',
            'images_only' => 'nullable|boolean',
            'files' => 'required|array|min:1',
            'files.*' => 'required|file',
        ]);

        $results = collect($request->file('files', []))
            ->map(fn ($file) => $this->fileManager->storeUploadedFile(
                $file,
                (string) ($validated['path'] ?? ''),
                (bool) ($validated['images_only'] ?? false)
            ))
            ->values()
            ->all();

        return response()->json([
            'message' => 'Files uploaded successfully.',
            'files' => $results,
            'usage' => [
                'used_bytes' => $this->fileManager->usedBytes(),
                'quota_bytes' => $this->fileManager->quotaBytes(),
                'remaining_bytes' => $this->fileManager->remainingBytes(),
            ],
        ]);
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:500',
            'name' => 'required|string|max:191',
        ]);

        return response()->json([
            'message' => 'Folder created successfully.',
            'path' => $this->fileManager->createDirectory(
                (string) ($validated['path'] ?? ''),
                (string) $validated['name']
            ),
        ]);
    }

    public function rename(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:500',
            'name' => 'required|string|max:191',
        ]);

        return response()->json([
            'message' => 'Item renamed successfully.',
            'path' => $this->fileManager->rename(
                (string) $validated['path'],
                (string) $validated['name']
            ),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:500',
        ]);

        $this->fileManager->delete((string) $validated['path']);

        return response()->json([
            'message' => 'Item deleted successfully.',
        ]);
    }
}
