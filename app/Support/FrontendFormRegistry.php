<?php

namespace App\Support;

/**
 * The forms the Next.js frontend can submit, and how each maps onto an Inquiry.
 *
 * The frontend has six forms with different field sets, but they are all the
 * same thing to the business: somebody asking to be contacted. So they all
 * become Inquiry rows and go through the existing spam guard, mail dispatch and
 * admin queue, rather than each growing its own table and its own pipeline.
 *
 * Per form:
 *  - `rules`   — Laravel validation, keyed by the frontend's own field names,
 *                so a 422 can be returned against the field the user is
 *                actually looking at. Mirrors the zod schemas in
 *                `frontend/src/schemas/formSchemas.js`; the two are matched by
 *                FormSubmissionApiTest.
 *  - `map`     — inquiry column => frontend field. Anything not named here is
 *                kept verbatim in `inquiries.details`.
 *  - `subject` — what an admin sees in the inquiry list.
 *  - `success` — the confirmation the user sees. Kept here rather than only in
 *                the frontend so the message survives a direct API call.
 *
 * Names are the frontend's camelCase, deliberately: these describe an incoming
 * payload, and renaming the keys here would only invite the two to drift.
 */
class FrontendFormRegistry
{
    /** Shared field rules, so one definition of "a valid email" serves them all. */
    private const EMAIL = 'required|email|max:191';

    private const PHONE = 'required|string|max:64|regex:/^(?=(?:\D*\d){7,15}\D*$)[0-9+\-() .]+$/';

    private const NAME = 'required|string|min:2|max:100';

    public const FORMS = [
        'contactHero' => [
            'subject' => 'Free quote request',
            'success' => 'Your quote request has been sent successfully.',
            'rules' => [
                'fullName' => self::NAME,
                'email' => self::EMAIL,
                'phone' => self::PHONE,
                'country' => 'required|string|max:100',
                'websiteUrl' => 'nullable|string|max:500|url:http,https',
                'service' => 'required|string|max:191',
                'brandName' => 'required|string|max:100',
                'currency' => 'required|string|max:8',
                // "None" is the placeholder option, so it is not a choice.
                'budget' => 'required|string|max:64|not_in:None',
                'message' => 'nullable|string|min:10|max:1000',
                'subscriptions' => 'nullable|array|max:20',
                'subscriptions.*' => 'string|max:191',
            ],
            'map' => [
                'name' => 'fullName',
                'email' => 'email',
                'phone' => 'phone',
                'message' => 'message',
                'type_of_service_required' => 'service',
            ],
        ],

        'heroNewsletter' => [
            'subject' => 'Newsletter signup',
            'success' => "Thank you! We'll be in touch soon.",
            'rules' => [
                'email' => self::EMAIL,
            ],
            'map' => [
                'email' => 'email',
            ],
        ],

        'floatingCtas' => [
            'subject' => 'Callback request',
            'success' => 'Thanks! Your request was submitted successfully.',
            'rules' => [
                'fullName' => self::NAME,
                'country' => 'required|string|max:100',
                'phone' => self::PHONE,
                'email' => self::EMAIL,
                'marketing' => 'required|string|max:191',
            ],
            'map' => [
                'name' => 'fullName',
                'email' => 'email',
                'phone' => 'phone',
                'type_of_service_required' => 'marketing',
            ],
        ],

        'serviceHero' => [
            'subject' => 'Service page enquiry',
            'success' => 'Your request has been submitted successfully.',
            'rules' => [
                // One free-text box: the user types either an email or a phone
                // number, so it is stored as the message and left unparsed.
                'contactDetails' => 'required|string|min:3|max:200',
            ],
            'map' => [
                'message' => 'contactDetails',
            ],
        ],

        'serContactForm' => [
            'subject' => 'Consultation booking',
            'success' => 'Your consultation booking request was submitted successfully.',
            'rules' => [
                'name' => self::NAME,
                'email' => self::EMAIL,
                'phone' => self::PHONE,
                'projectDetails' => 'nullable|string|max:1000',
                'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,webp,pdf,doc,docx',
            ],
            'map' => [
                'name' => 'name',
                'email' => 'email',
                'phone' => 'phone',
                'message' => 'projectDetails',
            ],
        ],

        'sertHero' => [
            'subject' => 'Store audit request',
            'success' => 'Your audit request has been sent successfully.',
            'rules' => [
                'fullName' => self::NAME,
                'email' => self::EMAIL,
                'phone' => self::PHONE,
                'products' => 'required|string|max:64',
                'country' => 'required|string|max:100',
                'storeUrl' => 'nullable|string|max:500|url:http,https',
                'budget' => 'required|string|max:64|not_in:None',
                'expectations' => 'nullable|string|min:10|max:1000',
            ],
            'map' => [
                'name' => 'fullName',
                'email' => 'email',
                'phone' => 'phone',
                'message' => 'expectations',
            ],
        ],
    ];

    public static function has(?string $formName): bool
    {
        return $formName !== null && array_key_exists($formName, self::FORMS);
    }

    public static function get(string $formName): ?array
    {
        return self::FORMS[$formName] ?? null;
    }

    public static function names(): array
    {
        return array_keys(self::FORMS);
    }

    /**
     * Validation rules, namespaced under `payload.` so the messages come back
     * keyed by the field names the frontend submitted.
     */
    public static function rulesFor(string $formName): array
    {
        $rules = [];

        foreach (self::FORMS[$formName]['rules'] ?? [] as $field => $rule) {
            $rules["payload.{$field}"] = $rule;
        }

        return $rules;
    }

    /**
     * Readable names for the `payload.*` rule keys.
     *
     * Without these, a failure reads "The payload.full name field is
     * required." — and that string is shown to the person filling the form,
     * beside the input, so the plumbing must not show through.
     */
    public static function attributesFor(string $formName): array
    {
        $attributes = [];

        foreach (array_keys(self::FORMS[$formName]['rules'] ?? []) as $field) {
            $name = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', (string) $field);
            $attributes["payload.{$field}"] = strtolower(str_replace('.*', '', (string) $name));
        }

        return $attributes;
    }
}
