<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSubmission;
use App\Models\OnboardingSubmissionFile;
use App\Services\OnboardingPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OnboardingSubmissionController extends Controller
{
    public function __construct(private readonly OnboardingPdfService $pdfService)
    {
    }

    public function index(Request $request)
    {
        $query = OnboardingSubmission::query()
            ->with('formConfig')
            ->latest('submitted_at');

        if ($search = trim((string) $request->input('q', ''))) {
            $search = substr($search, 0, 100);
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('applicant_name', 'like', "%{$search}%")
                  ->orWhere('applicant_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $submissions = $query->paginate($perPage);

        return view('admin.onboarding.submissions.index', [
            'title'       => 'Onboarding Submissions',
            'submissions' => $submissions,
            'statuses'    => OnboardingSubmission::STATUSES,
            'currentStatus' => $request->input('status', ''),
        ]);
    }

    public function show(OnboardingSubmission $submission)
    {
        $submission->load(['formConfig', 'files']);

        return view('admin.onboarding.submissions.show', [
            'title'      => 'Submission: ' . $submission->reference_number,
            'submission' => $submission,
            'statuses'   => OnboardingSubmission::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, OnboardingSubmission $submission)
    {
        $validated = $request->validate([
            'status'      => ['required', 'string', 'in:' . implode(',', array_keys(OnboardingSubmission::STATUSES))],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission->update([
            'status'      => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $submission->admin_notes,
        ]);

        return back()->with('success', 'Status updated to ' . OnboardingSubmission::STATUSES[$validated['status']]);
    }

    public function downloadPdf(OnboardingSubmission $submission)
    {
        $submission->load('files');
        $pdf = $this->pdfService->generate($submission);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="submission-' . $submission->reference_number . '.pdf"',
        ]);
    }

    public function downloadFile(OnboardingSubmission $submission, OnboardingSubmissionFile $file)
    {
        abort_unless($file->submission_id === $submission->id, 403);

        if (! Storage::disk('local')->exists($file->file_path)) {
            abort(404, 'File not found in storage.');
        }

        return Storage::disk('local')->download(
            $file->file_path,
            $file->original_name
        );
    }

    public function destroy(OnboardingSubmission $submission)
    {
        $submission->load('files');

        foreach ($submission->files as $file) {
            if (Storage::disk('local')->exists($file->file_path)) {
                Storage::disk('local')->delete($file->file_path);
            }
        }

        $dir = "onboarding/{$submission->id}";
        if (Storage::disk('local')->exists($dir)) {
            Storage::disk('local')->deleteDirectory($dir);
        }

        $submission->delete();

        return redirect()
            ->route('admin.onboarding.submissions.index')
            ->with('success', 'Submission deleted and associated files removed.');
    }
}
