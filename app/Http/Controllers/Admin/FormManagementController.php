<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\MailDispatch;
use App\Models\SiteSetting;
use App\Support\FrontendFormRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FormManagementController extends Controller
{
    /** Metadata for all forms active across the site. */
    public const FORMS_META = [
        'contactHero' => [
            'name' => 'Free Quote Request',
            'key' => 'contactHero',
            'location' => '/get-a-free-quote',
            'description' => 'Comprehensive project quote form with budget, services, currency and subscriptions.',
            'icon' => 'fas fa-file-invoice-dollar',
            'badge' => 'primary',
        ],
        'sertHero' => [
            'name' => 'Amazon Store Audit',
            'key' => 'sertHero',
            'location' => 'Amazon Service Pages (Hero)',
            'description' => 'Amazon seller store audit request with product count, store URL and expectations.',
            'icon' => 'fab fa-amazon',
            'badge' => 'warning',
        ],
        'serContactForm' => [
            'name' => 'Project Consultation',
            'key' => 'serContactForm',
            'location' => 'Development & Service Pages (Contact Section)',
            'description' => 'Consultation booking form with project details and file attachment uploads.',
            'icon' => 'fas fa-comments',
            'badge' => 'success',
        ],
        'serviceHero' => [
            'name' => 'Quick Service Connect',
            'key' => 'serviceHero',
            'location' => 'Service Pages (Hero Header)',
            'description' => 'Quick single-input contact details field for immediate client callback.',
            'icon' => 'fas fa-bolt',
            'badge' => 'info',
        ],
        'floatingCtas' => [
            'name' => 'Floating Callback Request',
            'key' => 'floatingCtas',
            'location' => 'Site-wide Floating CTA Drawer',
            'description' => 'Quick floating modal inquiry with marketing service selection.',
            'icon' => 'fas fa-phone-volume',
            'badge' => 'secondary',
        ],
        'heroNewsletter' => [
            'name' => 'Newsletter Signup',
            'key' => 'heroNewsletter',
            'location' => 'Home & Landing Pages (Hero)',
            'description' => 'Email subscription signup for marketing updates and newsletters.',
            'icon' => 'fas fa-envelope-open-text',
            'badge' => 'dark',
        ],
    ];

    public function index(Request $request)
    {
        $selectedForm = $request->query('form');
        $selectedStatus = $request->query('status');
        $search = trim((string) $request->query('q', ''));

        $query = Inquiry::query()->latest();

        if ($selectedForm && array_key_exists($selectedForm, self::FORMS_META)) {
            $query->where('form_name', $selectedForm);
        }

        if ($selectedStatus && in_array($selectedStatus, Inquiry::STATUSES, true)) {
            $query->where('status', $selectedStatus);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('type_of_service_required', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $submissions = $query->paginate(25)->withQueryString();

        // Count totals per form
        $countsByForm = Inquiry::query()
            ->whereNotNull('form_name')
            ->selectRaw('form_name, count(*) as total')
            ->groupBy('form_name')
            ->pluck('total', 'form_name')
            ->toArray();

        // Read email notification settings
        $emailSettings = [];
        foreach (array_keys(self::FORMS_META) as $formKey) {
            $emailSettings[$formKey] = SiteSetting::where('key', 'form_email_'.$formKey)->value('value') ?? '';
        }
        $emailSettings['default'] = SiteSetting::where('key', 'form_notification_emails')->value('value') ?? env('ADMIN_INQUIRY_RECIPIENTS', '');

        $totalSubmissions = Inquiry::count();
        $newSubmissions = Inquiry::new()->count();
        $spamCount = Inquiry::where('status', Inquiry::STATUS_SPAM)->count();

        return view('admin.forms.index', [
            'title' => 'Forms Management',
            'formsMeta' => self::FORMS_META,
            'submissions' => $submissions,
            'countsByForm' => $countsByForm,
            'emailSettings' => $emailSettings,
            'selectedForm' => $selectedForm,
            'selectedStatus' => $selectedStatus,
            'search' => $search,
            'totalSubmissions' => $totalSubmissions,
            'newSubmissions' => $newSubmissions,
            'spamCount' => $spamCount,
        ]);
    }

    public function updateEmailSettings(Request $request)
    {
        $validated = $request->validate([
            'default_email' => ['nullable', 'string', 'max:500'],
            'form_emails' => ['nullable', 'array'],
            'form_emails.*' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($validated['default_email'])) {
            SiteSetting::updateOrCreate(
                ['key' => 'form_notification_emails'],
                ['value' => trim((string) $validated['default_email'])]
            );
        }

        foreach ($validated['form_emails'] ?? [] as $formKey => $emails) {
            if (array_key_exists($formKey, self::FORMS_META)) {
                SiteSetting::updateOrCreate(
                    ['key' => 'form_email_'.$formKey],
                    ['value' => trim((string) $emails)]
                );
            }
        }

        return back()->with('success', 'Form notification email settings updated successfully.');
    }

    public function show(Inquiry $inquiry)
    {
        $inquiry->markAsRead();

        return view('admin.inquiries.show', [
            'title' => 'Form Submission #'.$inquiry->id,
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

        return back()->with('success', 'Submission status updated.');
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

        return redirect()->route('admin.forms.index')->with('success', 'Form submission deleted.');
    }
}
