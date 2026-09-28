<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminFileManagerService
{
    public function diskName(): string
    {
        return (string) config('admin_file_manager.disk', 'public');
    }

    public function quotaBytes(): int
    {
        return max(0, (int) config('admin_file_manager.quota_bytes', 5 * 1024 * 1024 * 1024));
    }

    public function maxUploadBytes(): int
    {
        return max(0, (int) config('admin_file_manager.max_upload_kb', 10240)) * 1024;
    }

    public function usedBytes(): int
    {
        $disk = Storage::disk($this->diskName());

        return collect($disk->allFiles())
            ->sum(fn (string $path) => (int) $disk->size($path));
    }

    public function remainingBytes(): int
    {
        return max(0, $this->quotaBytes() - $this->usedBytes());
    }

    public function normalizePath(?string $path): string
    {
        $path = str_replace('\\', '/', trim((string) $path));

        if ($path === '') {
            return '';
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' || str_contains($segment, "\0")) {
                throw ValidationException::withMessages([
                    'path' => 'Invalid file manager path.',
                ]);
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    public function sanitizeName(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name));

        if ($name === '' || str_contains($name, '/')) {
            throw ValidationException::withMessages([
                'name' => 'Invalid name provided.',
            ]);
        }

        $name = preg_replace('/[<>:"|?*\x00-\x1F]/', '-', $name) ?? '';
        $name = trim($name, ". \t\n\r\0\x0B");

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Name cannot be empty.',
            ]);
        }

        return $name;
    }

    public function list(string $path = '', bool $imagesOnly = false): array
    {
        $disk = Storage::disk($this->diskName());
        $path = $this->normalizePath($path);

        if ($path !== '' && ! $disk->exists($path) && ! $disk->directoryExists($path)) {
            return [
                'current_path' => $path,
                'breadcrumbs'  => [],
                'usage'        => [
                    'used_bytes'      => $this->usedBytes(),
                    'quota_bytes'     => $this->quotaBytes(),
                    'remaining_bytes' => $this->remainingBytes(),
                ],
                'directories' => [],
                'files'       => [],
            ];
        }

        $directories = collect($disk->directories($path))
            ->map(fn (string $directory) => [
                'type' => 'directory',
                'name' => basename($directory),
                'path' => $directory,
                'modified_at' => null,
                'size_bytes' => null,
                'mime_type' => null,
                'url' => null,
                'extension' => null,
                'width' => null,
                'height' => null,
                'selectable' => false,
            ]);

        $files = collect($disk->files($path))
            ->map(fn (string $file) => $this->metadata($file))
            ->filter(fn (array $file) => ! $imagesOnly || $this->isImageExtension($file['extension'] ?? ''))
            ->values();

        $breadcrumbs = [];
        $carry = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            $carry[] = $segment;
            $breadcrumbs[] = [
                'label' => $segment,
                'path' => implode('/', $carry),
            ];
        }

        return [
            'current_path' => $path,
            'breadcrumbs' => $breadcrumbs,
            'usage' => [
                'used_bytes' => $this->usedBytes(),
                'quota_bytes' => $this->quotaBytes(),
                'remaining_bytes' => $this->remainingBytes(),
            ],
            'directories' => $directories->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            'files' => $files->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
        ];
    }

    public function metadata(string $path): array
    {
        $disk = Storage::disk($this->diskName());
        $path = $this->normalizePath($path);

        if ($path === '' || ! $disk->exists($path)) {
            throw ValidationException::withMessages([
                'path' => 'File not found.',
            ]);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $absolutePath = $disk->path($path);
        $mimeType = $disk->mimeType($path) ?: null;
        $sizeBytes = (int) $disk->size($path);
        [$width, $height] = $this->imageDimensions($absolutePath);

        return [
            'type' => 'file',
            'name' => basename($path),
            'path' => $path,
            'disk' => $this->diskName(),
            'modified_at' => $disk->lastModified($path),
            'size_bytes' => $sizeBytes,
            'mime_type' => $mimeType,
            'url' => $disk->url($path),
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
            'selectable' => true,
        ];
    }

    public function createDirectory(string $path, string $name): string
    {
        $disk = Storage::disk($this->diskName());
        $path = $this->normalizePath($path);
        $name = $this->sanitizeName($name);
        $target = $this->normalizePath(trim($path.'/'.$name, '/'));

        if ($disk->exists($target) || $disk->directoryExists($target)) {
            throw ValidationException::withMessages([
                'name' => 'A file or folder with that name already exists.',
            ]);
        }

        $disk->makeDirectory($target);

        return $target;
    }

    public function rename(string $path, string $newName): string
    {
        $disk = Storage::disk($this->diskName());
        $path = $this->normalizePath($path);
        $newName = $this->sanitizeName($newName);

        if (! $disk->exists($path) && ! $disk->directoryExists($path)) {
            throw ValidationException::withMessages([
                'path' => 'Target not found.',
            ]);
        }

        $parent = trim(str_replace('\\', '/', dirname($path)), '.');
        $target = $this->normalizePath(trim(($parent !== '' ? $parent.'/' : '').$newName, '/'));

        if ($disk->exists($target) || $disk->directoryExists($target)) {
            throw ValidationException::withMessages([
                'name' => 'A file or folder with that name already exists.',
            ]);
        }

        $this->assertPathNotReferenced($path, 'That file or folder is in use and cannot be renamed.');
        $disk->move($path, $target);

        return $target;
    }

    public function delete(string $path): void
    {
        $disk = Storage::disk($this->diskName());
        $path = $this->normalizePath($path);

        if ($path === '') {
            throw ValidationException::withMessages([
                'path' => 'The file manager root cannot be deleted.',
            ]);
        }

        $this->assertPathNotReferenced($path, 'That file or folder is in use and cannot be deleted.');

        if ($disk->directoryExists($path)) {
            $disk->deleteDirectory($path);
            return;
        }

        if ($disk->exists($path)) {
            $disk->delete($path);
            return;
        }

        throw ValidationException::withMessages([
            'path' => 'Target not found.',
        ]);
    }

    public function storeUploadedFile(
        UploadedFile $file,
        string $directory,
        bool $imagesOnly = false,
        ?string $replacePath = null
    ): array {
        $this->assertUploadAllowed($file, $imagesOnly, $replacePath);

        $disk = Storage::disk($this->diskName());
        $directory = $this->normalizePath($directory);
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString().'-'.Str::slug($base ?: 'file');

        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $path = $file->storeAs($directory, $filename, $this->diskName());

        return $this->metadata($path);
    }

    public function assertManagedImagePath(string $path): array
    {
        $metadata = $this->metadata($path);

        if (! $this->isImageExtension($metadata['extension'] ?? '')) {
            throw ValidationException::withMessages([
                'path' => 'Only image files can be selected for this field.',
            ]);
        }

        return $metadata;
    }

    public function suggestedImageDirectory(Model $model, string $type): string
    {
        $entity = Str::plural(Str::snake(class_basename($model)));
        $modelType = $model->getAttribute('type');

        if (filled($modelType)) {
            $entity .= '/'.Str::snake((string) $modelType);
        }

        return trim('managed/'.$entity.'/'.$type, '/');
    }

    public function purgeImage(Image $image): void
    {
        $diskName = $image->disk ?: $this->diskName();
        $path = $image->path;
        $source = $image->source ?: 'upload';
        $id = $image->getKey();

        $image->delete();

        $this->deleteManagedFileIfUnreferenced($diskName, $path, $source, $id);
    }

    public function deleteManagedFileIfUnreferenced(
        ?string $diskName,
        ?string $path,
        ?string $source = 'upload',
        ?int $exceptImageId = null
    ): void {
        $diskName = $diskName ?: $this->diskName();
        $path = $path ? $this->normalizePath($path) : '';

        if ($path === '' || $source !== 'upload') {
            return;
        }

        $stillReferenced = Image::query()
            ->where('disk', $diskName)
            ->where('path', $path)
            ->when($exceptImageId, fn ($query) => $query->whereKeyNot($exceptImageId))
            ->exists();

        if ($stillReferenced) {
            return;
        }

        $disk = Storage::disk($diskName);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    protected function assertUploadAllowed(UploadedFile $file, bool $imagesOnly = false, ?string $replacePath = null): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = $imagesOnly
            ? (array) config('admin_file_manager.image_extensions', [])
            : (array) config('admin_file_manager.allowed_extensions', []);

        if ($extension === '' || ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'files' => 'That file type is not allowed.',
            ]);
        }

        $fileBytes = (int) $file->getSize();

        if ($fileBytes > $this->maxUploadBytes()) {
            throw ValidationException::withMessages([
                'files' => 'The selected file exceeds the upload size limit.',
            ]);
        }

        $usedBytes = $this->usedBytes();

        if ($replacePath) {
            $disk = Storage::disk($this->diskName());
            $replacePath = $this->normalizePath($replacePath);

            if ($disk->exists($replacePath)) {
                $usedBytes -= (int) $disk->size($replacePath);
            }
        }

        if (($usedBytes + $fileBytes) > $this->quotaBytes()) {
            throw ValidationException::withMessages([
                'files' => 'The file manager storage quota has been reached.',
            ]);
        }
    }

    protected function imageDimensions(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            return [null, null];
        }

        try {
            $dimensions = @getimagesize($absolutePath);

            if (! is_array($dimensions)) {
                return [null, null];
            }

            return [
                isset($dimensions[0]) ? (int) $dimensions[0] : null,
                isset($dimensions[1]) ? (int) $dimensions[1] : null,
            ];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    protected function isImageExtension(?string $extension): bool
    {
        return in_array(
            strtolower((string) $extension),
            (array) config('admin_file_manager.image_extensions', []),
            true
        );
    }

    protected function assertPathNotReferenced(string $path, string $message): void
    {
        $normalized = $this->normalizePath($path);
        $prefix = $normalized === '' ? '' : $normalized.'/';

        $imageReferenceExists = Image::query()
            ->where('disk', $this->diskName())
            ->where(function ($query) use ($normalized, $prefix) {
                $query->where('path', $normalized);

                if ($prefix !== '') {
                    $query->orWhere('path', 'like', $prefix.'%');
                }
            })
            ->exists();

        if ($imageReferenceExists) {
            throw ValidationException::withMessages([
                'path' => $message,
            ]);
        }

        $settingReferenceExists = \App\Models\SiteSetting::query()
            ->whereIn('key', ['logo', 'favicon'])
            ->where(function ($query) use ($normalized, $prefix) {
                $query->where('value', $normalized);

                if ($prefix !== '') {
                    $query->orWhere('value', 'like', $prefix.'%');
                }
            })
            ->exists();

        if ($settingReferenceExists) {
            throw ValidationException::withMessages([
                'path' => $message,
            ]);
        }
    }
}
