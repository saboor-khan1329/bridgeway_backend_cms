<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Services\GlobalCrudService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RedirectController extends Controller
{
    public function index()
    {
        return $this->renderAdminIndex(Redirect::query()->with('sourceable')->latest(), [
            'title' => 'Redirects',
            'routes' => [
                'create' => route('admin.redirects.create'),
                'show'   => fn ($i) => route('admin.redirects.show', $i->id),
                'edit'   => fn ($i) => route('admin.redirects.edit', $i->id),
                'delete' => fn ($i) => route('admin.redirects.destroy', $i->id),
            ],
            'columns' => [
                'id' => 'ID',
                'from_url' => 'From',
                'to_url' => 'To',
                'status_code' => 'Code',
                'is_auto_generated' => 'Auto',
                'status' => 'Status',
            ],
            'search' => ['id', 'from_url', 'to_url'],
        ]);
    }

    public function create()
    {
        return view('admin.shared.crud', [
            'item' => null,
            'config' => $this->formConfig(null),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        GlobalCrudService::create(Redirect::class, ['attributes' => $validated]);

        return redirect()->route('admin.redirects.index')->with('success', 'Redirect created.');
    }

    public function edit(Redirect $redirect)
    {
        return view('admin.shared.crud', [
            'item' => $redirect,
            'config' => $this->formConfig($redirect),
        ]);
    }

    public function show(Redirect $redirect)
    {
        $config = $this->formConfig($redirect);
        $config['title'] = 'View Redirect';
        $config['routes']['edit'] = route('admin.redirects.edit', $redirect->id);

        return $this->renderAdminShow($redirect, $config);
    }

    public function update(Request $request, Redirect $redirect)
    {
        $validated = $this->validateRequest($request, $redirect);
        GlobalCrudService::update($redirect, ['attributes' => $validated]);

        return redirect()->route('admin.redirects.index')->with('success', 'Redirect updated.');
    }

    public function destroy(Redirect $redirect)
    {
        GlobalCrudService::delete($redirect);

        return back()->with('success', 'Redirect deleted.');
    }

    protected function validateRequest(Request $request, ?Redirect $redirect = null): array
    {
        $validated = $request->validate([
            'from_url' => [
                'required',
                'string',
                'max:500',
                'regex:/^\//',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $redirect) {
                    $fromUrl = trim((string) $value);

                    if (! $request->boolean('status')) {
                        return;
                    }

                    $conflict = Redirect::query()
                        ->where('from_url', $fromUrl)
                        ->active()
                        ->when($redirect, fn ($query) => $query->where('id', '!=', $redirect->id))
                        ->exists();

                    if ($conflict) {
                        $fail('An active redirect already uses this source URL.');
                    }
                },
            ],
            'to_url' => [
                Rule::requiredIf((int) $request->input('status_code') !== 410),
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if (filled($value) && trim((string) $value) === trim((string) $request->input('from_url'))) {
                        $fail('Redirect source and destination must be different.');
                    }
                },
            ],
            'status_code' => 'required|in:301,302,410',
            'status' => 'required|boolean',
        ]);

        $validated['from_url'] = trim((string) $validated['from_url']);
        $validated['to_url'] = filled($validated['to_url'] ?? null)
            ? trim((string) $validated['to_url'])
            : null;

        return $validated;
    }

    protected function formConfig($item): array
    {
        return [
            'title' => $item ? 'Edit Redirect' : 'Add Redirect',
            'routes' => [
                'index'  => route('admin.redirects.index'),
                'store'  => route('admin.redirects.store'),
                'update' => $item ? route('admin.redirects.update', $item->id) : null,
                'edit' => $item ? route('admin.redirects.edit', $item->id) : null,
            ],
            'form' => [
                [['type' => 'text', 'label' => 'From URL (must start with /)', 'name' => 'from_url', 'required' => true, 'col' => 12]],
                [['type' => 'text', 'label' => 'To URL (leave blank for 410 Gone)', 'name' => 'to_url', 'col' => 12]],
                [
                    ['type' => 'select', 'label' => 'Status Code', 'name' => 'status_code', 'options' => [
                        '301' => '301 Permanent',
                        '302' => '302 Temporary',
                        '410' => '410 Gone',
                    ], 'required' => true, 'col' => 6],
                    ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 6],
                ],
            ],
        ];
    }
}
