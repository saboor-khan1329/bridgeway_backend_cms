<div class="modal fade" id="adminFileManagerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Asset Manager</h5>
                    <div class="small text-muted">Browse, upload, organize, and select files from `storage/app/public`.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @include('admin.file-manager._shell', [
                    'mode' => 'modal',
                    'selectMode' => true,
                    'imagesOnly' => true,
                ])
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        function formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let value = Number(bytes || 0);
            let unit = 0;

            while (value >= 1024 && unit < units.length - 1) {
                value /= 1024;
                unit += 1;
            }

            return `${value.toFixed(value >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
        }

        function formatTimestamp(value) {
            if (!value) {
                return '—';
            }

            try {
                return new Date(value * 1000).toLocaleString();
            } catch (error) {
                return '—';
            }
        }

        class AdminFileManagerShell {
            constructor(root) {
                this.root = root;
                this.selectMode = root.dataset.selectMode === '1';
                this.imagesOnly = root.dataset.imagesOnly === '1';
                this.state = {
                    path: '',
                    pickerContext: null,
                };
                this.csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                this.endpoints = {
                    items: root.dataset.itemsUrl,
                    upload: root.dataset.uploadUrl,
                    createFolder: root.dataset.createFolderUrl,
                    rename: root.dataset.renameUrl,
                    destroy: root.dataset.deleteUrl,
                };

                this.elements = {
                    alert: root.querySelector('[data-file-manager-alert]'),
                    usageBar: root.querySelector('[data-file-manager-usage-bar]'),
                    usageText: root.querySelector('[data-file-manager-usage-text]'),
                    currentPath: root.querySelector('[data-file-manager-current-path]'),
                    breadcrumbs: root.querySelector('[data-file-manager-breadcrumbs]'),
                    list: root.querySelector('[data-file-manager-list]'),
                    folderName: root.querySelector('[data-file-manager-folder-name]'),
                    upload: root.querySelector('[data-file-manager-upload]'),
                    createFolder: root.querySelector('[data-file-manager-create-folder]'),
                    refresh: root.querySelector('[data-file-manager-refresh]'),
                };

                this.bindEvents();
                this.setUploadAccept();
                this.load('');
            }

            bindEvents() {
                this.elements.refresh?.addEventListener('click', () => this.load(this.state.path));

                this.elements.createFolder?.addEventListener('click', async () => {
                    try {
                        const name = this.elements.folderName?.value?.trim() || '';

                        if (!name) {
                            this.showAlert('Please enter a folder name.', 'warning');
                            return;
                        }

                        await this.request(this.endpoints.createFolder, 'POST', {
                            path: this.state.path,
                            name,
                        });

                        this.elements.folderName.value = '';
                        this.showAlert('Folder created successfully.', 'success');
                        this.load(this.state.path);
                    } catch (error) {
                        this.showAlert(error.message || 'Unable to create folder.', 'danger');
                    }
                });

                this.elements.upload?.addEventListener('change', async (event) => {
                    try {
                        const files = Array.from(event.target.files || []);

                        if (files.length === 0) {
                            return;
                        }

                        const formData = new FormData();
                        formData.append('path', this.state.path);
                        formData.append('images_only', this.imagesOnly ? '1' : '0');

                        files.forEach(file => formData.append('files[]', file));

                        await this.request(this.endpoints.upload, 'POST', formData, true);

                        event.target.value = '';
                        this.showAlert('Files uploaded successfully.', 'success');
                        this.load(this.state.path);
                    } catch (error) {
                        this.showAlert(error.message || 'Unable to upload files.', 'danger');
                    }
                });
            }

            async openPicker(context) {
                this.state.pickerContext = context || null;
                this.imagesOnly = true;
                this.root.dataset.imagesOnly = '1';
                this.setUploadAccept();

                await this.load(context?.directory || context?.path || '');
            }

            async load(path) {
                this.state.path = path || '';

                if (this.elements.list) {
                    this.elements.list.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>';
                }

                const query = new URLSearchParams({
                    path: this.state.path,
                    images_only: this.imagesOnly ? '1' : '0',
                });

                try {
                    const response = await fetch(`${this.endpoints.items}?${query.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        this.showAlert(data.message || 'Unable to load files.', 'danger');
                        return;
                    }

                    this.render(data);
                } catch (error) {
                    this.showAlert(error.message || 'Unable to load files.', 'danger');
                }
            }

            render(data) {
                this.renderUsage(data.usage || {});
                this.renderCurrentPath(data.current_path || '');
                this.renderBreadcrumbs(data.current_path || '', data.breadcrumbs || []);
                this.renderRows([...(data.directories || []), ...(data.files || [])]);
            }

            renderUsage(usage) {
                const used = Number(usage.used_bytes || 0);
                const quota = Math.max(Number(usage.quota_bytes || 0), 1);
                const pct = Math.min(100, Math.round((used / quota) * 100));

                if (this.elements.usageBar) {
                    this.elements.usageBar.style.width = `${pct}%`;
                    this.elements.usageBar.className = `progress-bar ${pct >= 90 ? 'bg-danger' : pct >= 70 ? 'bg-warning' : 'bg-primary'}`;
                }

                if (this.elements.usageText) {
                    this.elements.usageText.textContent = `${formatBytes(used)} used of ${formatBytes(quota)} (${pct}%)`;
                }
            }

            renderCurrentPath(path) {
                if (this.elements.currentPath) {
                    this.elements.currentPath.textContent = `Current folder: /${path}`;
                }
            }

            renderBreadcrumbs(path, breadcrumbs) {
                if (!this.elements.breadcrumbs) {
                    return;
                }

                const segments = [
                    `<button type="button" class="btn btn-outline-secondary btn-sm" data-fm-open-path="">Root</button>`,
                ];

                breadcrumbs.forEach(crumb => {
                    segments.push(
                        `<button type="button" class="btn btn-outline-secondary btn-sm" data-fm-open-path="${this.escapeAttr(crumb.path)}">${this.escapeHtml(crumb.label)}</button>`
                    );
                });

                this.elements.breadcrumbs.innerHTML = segments.join('');
                this.elements.breadcrumbs.querySelectorAll('[data-fm-open-path]').forEach(button => {
                    button.addEventListener('click', () => this.load(button.dataset.fmOpenPath || ''));
                });
            }

            renderRows(items) {
                if (!this.elements.list) {
                    return;
                }

                if (items.length === 0) {
                    this.elements.list.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No files or folders found.</td></tr>';
                    return;
                }

                this.elements.list.innerHTML = items.map(item => {
                    const isDirectory = item.type === 'directory';
                    const canSelect = this.selectMode && !isDirectory && (!this.imagesOnly || item.selectable);
                    const isImage = !isDirectory && (item.width || ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif'].includes((item.extension || '').toLowerCase()));

                    return `
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    ${isImage && item.url ? `
                                        <a href="${this.escapeAttr(item.url)}" target="_blank" class="me-2 flex-shrink-0" title="Click to view full image">
                                            <img src="${this.escapeAttr(item.url)}" alt="${this.escapeAttr(item.name)}"
                                                style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6; background: #f8f9fa;" />
                                        </a>
                                    ` : (isDirectory ? `
                                        <div class="me-2 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; background: rgba(255,118,58,0.1); color: #FF763A !important; border-radius: 6px;">
                                            <i class="fas fa-folder fa-lg" style="color: #FF763A;"></i>
                                        </div>
                                    ` : `
                                        <div class="me-2 text-muted d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px; background: #f1f3f5; border-radius: 6px;">
                                            <i class="fas fa-file"></i>
                                        </div>
                                    `)}
                                    <div class="text-truncate">
                                        <div class="fw-semibold text-truncate" title="${this.escapeAttr(item.name)}">${this.escapeHtml(item.name)}</div>
                                        <div class="small text-muted text-break">/${this.escapeHtml(item.path)}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                ${isDirectory ? '<span class="badge bg-secondary">Folder</span>' : `<span class="badge bg-light text-dark">${this.escapeHtml(item.extension || 'file')}</span>`}
                                ${item.width && item.height ? `<div class="small text-muted mt-1"><i class="fas fa-vector-square me-1"></i>${item.width}×${item.height}</div>` : ''}
                            </td>
                            <td>${isDirectory ? '—' : formatBytes(item.size_bytes || 0)}</td>
                            <td>${formatTimestamp(item.modified_at)}</td>
                            <td class="text-end text-nowrap">
                                ${isDirectory
                                    ? `<button type="button" class="btn btn-outline-primary btn-sm" data-fm-open-path="${this.escapeAttr(item.path)}"><i class="fas fa-folder-open me-1"></i>Open</button>`
                                    : `<a href="${this.escapeAttr(item.url || '#')}" target="_blank" rel="noopener" class="btn btn-outline-info btn-sm"><i class="fas fa-eye me-1"></i>Preview</a>`
                                }
                                ${!isDirectory ? `<button type="button" class="btn btn-outline-secondary btn-sm" data-fm-copy-path="${this.escapeAttr(item.path)}"><i class="fas fa-link me-1"></i>Copy</button>` : ''}
                                ${canSelect ? `<button type="button" class="btn btn-success btn-sm font-weight-bold" data-fm-select-path="${this.escapeAttr(item.path)}" data-fm-select-name="${this.escapeAttr(item.name)}" data-fm-select-url="${this.escapeAttr(item.url || '')}" data-fm-select-disk="${this.escapeAttr(item.disk || 'public')}" data-fm-select-extension="${this.escapeAttr(item.extension || '')}" data-fm-select-width="${this.escapeAttr(item.width || '')}" data-fm-select-height="${this.escapeAttr(item.height || '')}" data-fm-select-size="${this.escapeAttr(item.size_bytes || '')}" data-fm-select-mime="${this.escapeAttr(item.mime_type || '')}"><i class="fas fa-check me-1"></i>Use Image</button>` : ''}
                                <button type="button" class="btn btn-outline-warning btn-sm" data-fm-rename-path="${this.escapeAttr(item.path)}" data-fm-rename-name="${this.escapeAttr(item.name)}"><i class="fas fa-edit"></i></button>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-fm-delete-path="${this.escapeAttr(item.path)}"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `;
                }).join('');

                this.elements.list.querySelectorAll('[data-fm-open-path]').forEach(button => {
                    button.addEventListener('click', () => this.load(button.dataset.fmOpenPath || ''));
                });

                this.elements.list.querySelectorAll('[data-fm-copy-path]').forEach(button => {
                    button.addEventListener('click', async () => {
                        await navigator.clipboard.writeText(button.dataset.fmCopyPath || '');
                        this.showAlert('Path copied to clipboard.', 'success');
                    });
                });

                this.elements.list.querySelectorAll('[data-fm-rename-path]').forEach(button => {
                    button.addEventListener('click', async () => {
                        try {
                            const nextName = window.prompt('Enter a new name', button.dataset.fmRenameName || '');

                            if (!nextName) {
                                return;
                            }

                            await this.request(this.endpoints.rename, 'PATCH', {
                                path: button.dataset.fmRenamePath || '',
                                name: nextName,
                            });

                            this.showAlert('Item renamed successfully.', 'success');
                            this.load(this.state.path);
                        } catch (error) {
                            this.showAlert(error.message || 'Unable to rename item.', 'danger');
                        }
                    });
                });

                this.elements.list.querySelectorAll('[data-fm-delete-path]').forEach(button => {
                    button.addEventListener('click', async () => {
                        try {
                            if (!window.confirm('Delete this file or folder?')) {
                                return;
                            }

                            await this.request(this.endpoints.destroy, 'DELETE', {
                                path: button.dataset.fmDeletePath || '',
                            });

                            this.showAlert('Item deleted successfully.', 'success');
                            this.load(this.state.path);
                        } catch (error) {
                            this.showAlert(error.message || 'Unable to delete item.', 'danger');
                        }
                    });
                });

                this.elements.list.querySelectorAll('[data-fm-select-path]').forEach(button => {
                    button.addEventListener('click', () => {
                        const file = {
                            name: button.dataset.fmSelectName || '',
                            path: button.dataset.fmSelectPath || '',
                            url: button.dataset.fmSelectUrl || '',
                            disk: button.dataset.fmSelectDisk || 'public',
                            extension: button.dataset.fmSelectExtension || '',
                            width: button.dataset.fmSelectWidth ? parseInt(button.dataset.fmSelectWidth, 10) : null,
                            height: button.dataset.fmSelectHeight ? parseInt(button.dataset.fmSelectHeight, 10) : null,
                            size_bytes: button.dataset.fmSelectSize || null,
                            mime_type: button.dataset.fmSelectMime || null,
                        };
                        const callback = window.__adminFileManagerPickerCallback;

                        if (typeof callback === 'function') {
                            callback(file);
                        }
                    });
                });
            }

            async request(url, method, payload, isFormData = false) {
                const headers = {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                };

                let body = payload;

                if (!isFormData) {
                    headers['Content-Type'] = 'application/json';
                    body = JSON.stringify(payload);
                }

                const response = await fetch(url, {
                    method,
                    headers,
                    body,
                });

                const data = await response.json();

                if (!response.ok) {
                    const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                    throw new Error(firstError || data.message || 'Request failed.');
                }

                return data;
            }

            setUploadAccept() {
                if (!this.elements.upload) {
                    return;
                }

                if (this.imagesOnly) {
                    this.elements.upload.setAttribute('accept', 'image/*');
                    return;
                }

                this.elements.upload.removeAttribute('accept');
            }

            showAlert(message, tone) {
                if (!this.elements.alert) {
                    return;
                }

                this.elements.alert.className = `alert alert-${tone}`;
                this.elements.alert.textContent = message;
                this.elements.alert.classList.remove('d-none');

                window.clearTimeout(this.alertTimer);
                this.alertTimer = window.setTimeout(() => {
                    this.elements.alert.classList.add('d-none');
                }, 3500);
            }

            escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            escapeAttr(value) {
                return this.escapeHtml(value);
            }
        }

        const shells = Array.from(document.querySelectorAll('.js-file-manager-shell')).map(root => new AdminFileManagerShell(root));
        const modalShell = shells.find(shell => shell.root.dataset.mode === 'modal');
        const modalElement = document.getElementById('adminFileManagerModal');
        const modalInstance = modalElement ? new bootstrap.Modal(modalElement) : null;

        window.openAdminFileManagerPicker = function (context) {
            if (!modalShell || !modalInstance) {
                return;
            }

            window.__adminFileManagerPickerCallback = function (file) {
                if (typeof context?.onSelect === 'function') {
                    context.onSelect(file);
                }

                modalInstance.hide();
            };

            modalShell.openPicker(context || {});
            modalInstance.show();
        };
    })();
</script>
@endpush
