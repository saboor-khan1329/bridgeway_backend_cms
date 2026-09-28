<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GlobalCrudService;
use App\Support\ImageField;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $this->ensureRootAdmin();

        return $this->renderAdminIndex(
            User::query()->latest(),
            [
                'title' => 'Users',
                'routes' => [
                    'create' => route('admin.users.create'),
                    'show' => fn ($item) => route('admin.users.show', $item->id),
                    'edit' => fn ($item) => route('admin.users.edit', $item->id),
                    'delete' => fn ($item) => route('admin.users.destroy', $item->id),
                ],
                'columns' => [
                    'id' => 'ID',
                    'name' => 'Name',
                    'email' => 'Email',
                    'account_type' => 'Account Type',
                    'status' => 'Status',
                    'created_at' => 'Created',
                ],
                'search' => ['id', 'name', 'display_name', 'email'],
            ]
        );
    }

    public function create()
    {
        $this->ensureRootAdmin();

        return view('admin.shared.crud', [
            'item' => null,
            'config' => $this->formConfig(null),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureRootAdmin();

        $validated = $this->validateRequest($request);
        GlobalCrudService::create(User::class, $this->payload($validated, $request));

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function show(User $user)
    {
        $this->ensureRootAdmin();

        $item = $user->load('images');
        $config = $this->formConfig($item);
        $config['title'] = 'View User';
        $config['routes']['edit'] = route('admin.users.edit', $item->id);

        return $this->renderAdminShow($item, $config);
    }

    public function edit(User $user)
    {
        $this->ensureRootAdmin();

        return view('admin.shared.crud', [
            'item' => $user->load('images'),
            'config' => $this->formConfig($user),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureRootAdmin();

        $validated = $this->validateRequest($request, $user);
        GlobalCrudService::update($user, $this->payload($validated, $request, $user));

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        $this->ensureRootAdmin();

        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isRootAdmin()) {
            return back()->with('error', 'Root administrators cannot be deleted.');
        }

        GlobalCrudService::delete($user);

        return back()->with('success', 'User deleted.');
    }

    protected function payload(array $validated, Request $request, ?User $user = null): array
    {
        $attributes = [
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'email' => $validated['email'],
            'status' => (bool) $validated['status'],
        ];

        if (! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        if ($request->user()?->isRootAdmin()) {
            $attributes['is_root'] = (bool) ($validated['is_root'] ?? false);
        } elseif (! $user) {
            $attributes['is_root'] = false;
        }

        return [
            'attributes' => $attributes,
            'images' => [
                'avatar' => $request->avatar,
            ],
        ];
    }

    protected function formConfig(?User $item): array
    {
        return [
            'title' => $item ? 'Edit User' : 'Add User',
            'routes' => [
                'index' => route('admin.users.index'),
                'store' => route('admin.users.store'),
                'update' => $item ? route('admin.users.update', $item->id) : null,
                'edit' => $item ? route('admin.users.edit', $item->id) : null,
            ],
            'form' => [
                [
                    ['type' => 'text', 'label' => 'Name', 'name' => 'name', 'required' => true, 'col' => 6],
                    ['type' => 'text', 'label' => 'Display Name', 'name' => 'display_name', 'col' => 6],
                ],
                [
                    ['type' => 'text', 'label' => 'Email', 'name' => 'email', 'required' => true, 'col' => 6, 'attr' => ['type' => 'email']],
                    ['type' => 'text', 'label' => 'Password', 'name' => 'password', 'required' => ! $item, 'col' => 6, 'value' => '', 'attr' => ['type' => 'password', 'autocomplete' => 'new-password']],
                ],
                [
                    ['type' => 'text', 'label' => 'Job Title', 'name' => 'job_title', 'col' => 6],
                    ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 6],
                ],
                $this->accountFields(),
                [['type' => 'textarea', 'label' => 'Author Bio', 'name' => 'bio', 'editor' => false, 'col' => 12]],
            ],
        ];
    }

    protected function validateRequest(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:191',
            'display_name' => 'nullable|string|max:191',
            'job_title' => 'nullable|string|max:191',
            'bio' => 'nullable|string',
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:191'],
            'status' => 'required|boolean',
            'is_root' => 'nullable|boolean',
            ...ImageField::singleRules('avatar', 2048),
        ]);
    }

    protected function accountFields(): array
    {
        $fields = [];

        if (request()->user()?->isRootAdmin()) {
            $fields[] = [
                'type' => 'select',
                'label' => 'Account Type',
                'name' => 'is_root',
                'options' => ['0' => 'Admin', '1' => 'Super Admin'],
                'col' => 6,
            ];
        }

        $fields[] = [
            'type' => 'image',
            'label' => 'Profile Image',
            'name' => 'avatar',
            'image_key' => 'avatar',
            'directory' => 'managed/users/avatar',
            'col' => $fields === [] ? 12 : 6,
        ];

        return $fields;
    }

    protected function ensureRootAdmin(): void
    {
        abort_unless(request()->user()?->isRootAdmin(), 403);
    }
}
