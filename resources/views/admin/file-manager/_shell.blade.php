@php
    $mode = $mode ?? 'page';
    $selectMode = (bool) ($selectMode ?? false);
    $imagesOnly = (bool) ($imagesOnly ?? false);
@endphp

<div class="js-file-manager-shell"
    data-mode="{{ $mode }}"
    data-select-mode="{{ $selectMode ? '1' : '0' }}"
    data-images-only="{{ $imagesOnly ? '1' : '0' }}"
    data-items-url="{{ route('admin.file-manager.items') }}"
    data-upload-url="{{ route('admin.file-manager.upload') }}"
    data-create-folder-url="{{ route('admin.file-manager.folders.store') }}"
    data-rename-url="{{ route('admin.file-manager.rename') }}"
    data-delete-url="{{ route('admin.file-manager.destroy') }}">
    <div class="alert d-none mb-3" data-file-manager-alert role="alert"></div>

    <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 mb-3">
        <div class="flex-grow-1">
            <div class="small text-muted text-uppercase">Storage Usage</div>
            <div class="progress mt-2" style="height: 8px;">
                <div class="progress-bar bg-primary" role="progressbar" data-file-manager-usage-bar style="width: 0%"></div>
            </div>
            <div class="small text-muted mt-2" data-file-manager-usage-text>Loading usage…</div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small fw-semibold mb-1">Create Folder</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" data-file-manager-folder-name placeholder="New folder name">
                    <button type="button" class="btn btn-outline-primary" data-file-manager-create-folder>Create</button>
                </div>
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">Upload</label>
                <input type="file" class="form-control form-control-sm" data-file-manager-upload multiple>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-file-manager-refresh>
                    <i class="fas fa-rotate"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <div class="mb-2 small text-muted" data-file-manager-current-path>Current folder: /</div>
    <div class="mb-3 d-flex flex-wrap gap-2" data-file-manager-breadcrumbs></div>

    <div class="table-responsive border rounded">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 260px;">Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th class="text-end" style="min-width: 260px;">Actions</th>
                </tr>
            </thead>
            <tbody data-file-manager-list>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Loading…</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
