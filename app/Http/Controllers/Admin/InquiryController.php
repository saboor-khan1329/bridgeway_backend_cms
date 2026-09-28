<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\MailDispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InquiryController extends Controller
{
    public function index()
    {
        return $this->renderAdminIndex(Inquiry::query()->latest(), [
            'title' => 'Inquiries',
            'routes' => [
                'show' => fn ($i) => route('admin.inquiries.show', $i->id),
                'delete' => fn ($i) => route('admin.inquiries.destroy', $i->id),
            ],
            'columns' => [
                'id' => 'ID',
                'form_name' => 'Form',
                'name' => 'Name',
                'email' => 'Email',
                'type_of_service_required' => 'Type of Service Required',
                'subject' => 'Subject',
                'spam_score' => 'Spam',
                'status' => 'Status',
                'created_at' => 'Received',
            ],
            'search' => ['id', 'form_name', 'name', 'email', 'type_of_service_required', 'subject', 'message'],
        ]);
    }

    public function show(Inquiry $inquiry)
    {
        $inquiry->markAsRead();

        return view('admin.inquiries.show', [
            'title' => 'Inquiry #'.$inquiry->id,
            'inquiry' => $inquiry,
            'mailDispatches' => MailDispatch::query()
                ->forInquiry($inquiry)
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function updateStatus(Request $request, Inquiry $inquiry)
    {
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', Inquiry::STATUSES),
        ]);

        $inquiry->update($validated);

        return back()->with('success', 'Status updated.');
    }

    public function attachment(Inquiry $inquiry)
    {
        $file = data_get($inquiry->details, 'attachment');
        abort_unless(is_array($file) && str_starts_with($file['path'] ?? '', 'inquiry-attachments/'), 404);
        abort_unless(Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], basename($file['name']), [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Inquiry $inquiry)
    {
        $inquiry->delete();

        return redirect()->route('admin.inquiries.index')->with('success', 'Inquiry deleted.');
    }
}
