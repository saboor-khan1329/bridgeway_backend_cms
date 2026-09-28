<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class InquirySpamGuard
{
    public function inspect(Request $request, array $payload): array
    {
        $score = 0;
        $reasons = [];
        $errors = [];
        $captchaPassed = false;

        if ((bool) config('inquiries.captcha.enabled', false)) {
            $captcha = $this->verifyCaptcha($request);
            $captchaPassed = $captcha['passed'];

            if (! $captcha['passed']) {
                $score += 5;
                $reasons[] = $captcha['reason'];
                $errors[$this->captchaTokenField()] = $captcha['message'];
            }
        }

        if ($this->spamDetectionEnabled()) {
            $spam = $this->scoreSpamSignals($request, $payload);
            $score += $spam['score'];
            $reasons = array_merge($reasons, $spam['reasons']);
            $errors = array_merge($errors, $spam['errors'] ?? []);
        }

        if ($score >= $this->spamBlockScore()) {
            $errors['message'] ??= 'Your message could not be submitted. Please review the form and try again.';
        }

        return [
            'passed' => $errors === [],
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'errors' => $errors,
            'captcha_passed' => $captchaPassed,
        ];
    }

    protected function scoreSpamSignals(Request $request, array $payload): array
    {
        $score = 0;
        $reasons = [];
        $errors = [];

        $honeypotField = trim($this->honeypotField());
        if ($honeypotField !== '' && filled($request->input($honeypotField))) {
            $score += 10;
            $reasons[] = 'honeypot';
        }

        $startedAt = $request->input('form_started_at');
        $minimumSeconds = (int) config('inquiries.spam.minimum_seconds', 2);
        if ($startedAt && $minimumSeconds > 0) {
            $timestamp = is_numeric($startedAt) ? (int) $startedAt : strtotime((string) $startedAt);
            if ($timestamp && now()->timestamp - $timestamp < $minimumSeconds) {
                $score += 3;
                $reasons[] = 'submitted_too_fast';
            }
        }

        $content = implode(' ', [
            (string) ($payload['name'] ?? ''),
            (string) ($payload['email'] ?? ''),
            (string) ($payload['phone'] ?? ''),
            (string) ($payload['subject'] ?? ''),
            (string) ($payload['type_of_service_required'] ?? ''),
            (string) ($payload['message'] ?? ''),
        ]);
        $linkCount = preg_match_all('/https?:\/\/|www\./i', $content);
        if ($linkCount > (int) config('inquiries.spam.max_links', 3)) {
            $score += 4;
            $reasons[] = 'too_many_links';
        }

        foreach ($this->blockedKeywords() as $keyword) {
            if ($keyword !== '' && stripos($content, (string) $keyword) !== false) {
                $score += 5;
                $reasons[] = 'blocked_keyword';
                $errors['message'] = 'Please provide appropriate details before submitting.';
                break;
            }
        }

        foreach ($this->fieldBlockRules() as $field => $blockedValues) {
            $value = (string) data_get($payload, $field, '');
            if ($value === '') {
                continue;
            }

            foreach ($blockedValues as $blockedValue) {
                if ($blockedValue !== '' && stripos($value, $blockedValue) !== false) {
                    $score += 5;
                    $reasons[] = "blocked_field:{$field}";
                    $errors[$field] = 'Please enter proper and appropriate details.';
                    break;
                }
            }
        }

        return ['score' => $score, 'reasons' => $reasons, 'errors' => $errors];
    }

    /**
     * Name of the decoy field bots fill in. Extracted as a hook so guards
     * for other forms (e.g. the Service Finder quote popup, which has a
     * REAL "company" field) can use a different decoy without duplicating
     * the rest of the spam stack.
     */
    protected function honeypotField(): string
    {
        return (string) config('inquiries.spam.honeypot_field', 'company');
    }

    protected function spamDetectionEnabled(): bool
    {
        return $this->booleanSetting(
            'inquiry_spam_detection_enabled',
            (bool) config('inquiries.spam.enabled', true)
        );
    }

    protected function spamBlockScore(): int
    {
        $score = SiteSetting::get('inquiry_spam_block_score');

        if ($score === null || $score === '') {
            $score = config('inquiries.spam.block_score', 5);
        }

        return max(1, (int) $score);
    }

    protected function blockedKeywords(): array
    {
        $configured = (array) config('inquiries.spam.blocked_keywords', []);
        $setting = SiteSetting::get('inquiry_blocked_keywords', '');
        $adminKeywords = preg_split('/[\r\n,]+/', (string) $setting) ?: [];

        return collect(array_merge($configured, $adminKeywords))
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique(fn ($keyword) => strtolower($keyword))
            ->values()
            ->all();
    }

    protected function fieldBlockRules(): array
    {
        $setting = (string) SiteSetting::get('inquiry_field_block_rules', '');
        $rules = [];

        foreach (preg_split('/\r\n|\r|\n/', $setting) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$field, $values] = array_map('trim', explode('=', $line, 2));
            if ($field === '') {
                continue;
            }

            $rules[$field] = collect(explode(',', $values))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique(fn ($value) => strtolower($value))
                ->values()
                ->all();
        }

        return $rules;
    }

    protected function booleanSetting(string $key, bool $default): bool
    {
        $value = SiteSetting::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    protected function verifyCaptcha(Request $request): array
    {
        $secret = (string) config('inquiries.captcha.secret', '');
        $token = (string) $request->input($this->captchaTokenField(), '');

        if ($token === '') {
            return $this->captchaFailure('captcha_missing', 'Captcha verification is required.');
        }

        if ($secret === '') {
            return $this->captchaFailure('captcha_not_configured', 'Captcha verification is not configured.');
        }

        try {
            $response = Http::asForm()
                ->timeout(max(1, (int) config('inquiries.captcha.timeout', 5)))
                ->post($this->captchaEndpoint(), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ])
                ->json();
        } catch (Throwable $exception) {
            report($exception);

            if ((bool) config('inquiries.captcha.fail_open', false)) {
                return ['passed' => true, 'reason' => 'captcha_unavailable', 'message' => ''];
            }

            return $this->captchaFailure('captcha_unavailable', 'Captcha verification is unavailable. Please try again.');
        }

        if (! (bool) data_get($response, 'success', false)) {
            return $this->captchaFailure('captcha_failed', 'Captcha verification failed. Please try again.');
        }

        if ($this->captchaDriver() === 'recaptcha' || $this->captchaDriver() === 'recaptcha_v3') {
            $score = data_get($response, 'score');
            if ($score !== null && (float) $score < (float) config('inquiries.captcha.recaptcha_min_score', 0.5)) {
                return $this->captchaFailure('captcha_low_score', 'Captcha verification failed. Please try again.');
            }
        }

        return ['passed' => true, 'reason' => 'captcha_passed', 'message' => ''];
    }

    protected function captchaFailure(string $reason, string $message): array
    {
        return ['passed' => false, 'reason' => $reason, 'message' => $message];
    }

    protected function captchaEndpoint(): string
    {
        return match ($this->captchaDriver()) {
            'recaptcha', 'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
            default => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        };
    }

    protected function captchaDriver(): string
    {
        return strtolower((string) config('inquiries.captcha.driver', 'turnstile'));
    }

    protected function captchaTokenField(): string
    {
        return (string) config('inquiries.captcha.token_field', 'captcha_token');
    }
}
