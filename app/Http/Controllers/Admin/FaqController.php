<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\GlobalCrudService;
use App\Support\AdminSelectLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        return $this->renderAdminIndex(Faq::query()->latest(), [
            'title' => 'FAQs',
            'routes' => [
                'create' => route('admin.faqs.create'),
                'show' => fn ($item) => route('admin.faqs.show', $item->id),
                'edit' => fn ($item) => route('admin.faqs.edit', $item->id),
                'delete' => fn ($item) => route('admin.faqs.destroy', $item->id),
            ],
            'columns' => [
                'id' => 'ID',
                'question' => 'Question',
                'status' => 'Status',
                'created_at' => 'Created',
            ],
            'search' => ['id', 'question', 'answer'],
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

        GlobalCrudService::create(Faq::class, [
            'attributes' => $validated,
        ]);

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ created');
    }

    public function edit(Faq $faq)
    {
        return view('admin.shared.crud', [
            'item' => $faq,
            'config' => $this->formConfig($faq),
        ]);
    }

    public function show(Faq $faq)
    {
        $config = $this->formConfig($faq);
        $config['title'] = 'View FAQ';
        $config['routes']['edit'] = route('admin.faqs.edit', $faq->id);

        return $this->renderAdminShow($faq, $config);
    }

    public function update(Request $request, Faq $faq)
    {
        $validated = $this->validateRequest($request);

        GlobalCrudService::update($faq, [
            'attributes' => $validated,
        ]);

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ updated');
    }

    public function destroy(Faq $faq)
    {
        GlobalCrudService::delete($faq);

        return back()->with('success', 'FAQ deleted');
    }

    /**
     * AJAX — Create a FAQ and return Select2-compatible {id, text}.
     * Used by the inline "Create & Attach" button on CRUD forms.
     */
    public function quickCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer'   => 'required|string|max:20000',
        ]);

        $faq = Faq::create([
            'question' => $validated['question'],
            'answer'   => $validated['answer'],
            'status'   => true,
        ]);

        return response()->json([
            'id'   => $faq->id,
            'text' => AdminSelectLabel::faq($faq),
        ]);
    }

    protected function validateRequest(Request $request): array
    {
        return $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'status' => 'required|boolean',
        ]);
    }

    protected function formConfig($item): array
    {
        return [
            'title' => $item ? 'Edit FAQ' : 'Add FAQ',
            'routes' => [
                'index' => route('admin.faqs.index'),
                'store' => route('admin.faqs.store'),
                'update' => $item ? route('admin.faqs.update', $item->id) : null,
                'edit' => $item ? route('admin.faqs.edit', $item->id) : null,
            ],
            'form' => [
                [['type' => 'textarea', 'label' => 'Question', 'name' => 'question', 'editor' => false, 'required' => true, 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Answer', 'name' => 'answer', 'editor' => false, 'required' => true, 'col' => 12]],
                [['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 12]],
            ],
        ];
    }
}
