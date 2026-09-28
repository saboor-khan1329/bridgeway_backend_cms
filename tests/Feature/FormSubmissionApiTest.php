<?php

namespace Tests\Feature;

use App\Mail\InquiryReceivedMail;
use App\Models\Inquiry;
use App\Models\MailDispatch;
use App\Support\FrontendFormRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * POST /api/forms — every form the Next.js frontend submits.
 *
 * Run:
 *   php artisan test --filter FormSubmissionApiTest
 */
class FormSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Notifications and captcha need external services; spam detection is
        // enabled because several tests are about it.
        config([
            'inquiries.notifications.enabled' => false,
            'inquiries.captcha.enabled' => false,
            'mail_outbox.enabled' => false,
        ]);
    }

    /** A submission the quote form's rules accept. */
    private function validQuote(array $overrides = []): array
    {
        return array_merge([
            'fullName' => 'Jane Morrison',
            'email' => 'jane@example.com',
            'phone' => '+1 832 266 0227',
            'country' => 'United States',
            'websiteUrl' => 'https://example.com',
            'service' => 'Digital Marketing',
            'brandName' => 'Acme',
            'currency' => '$',
            'budget' => '1000 - 5000',
            'message' => 'Please send a quote for our storefront.',
            'subscriptions' => ['E-commerce Solutions'],
        ], $overrides);
    }

    private function submit(string $formName, array $payload)
    {
        return $this->postJson('/api/forms', [
            'formName' => $formName,
            'payload' => $payload,
        ]);
    }

    public function test_service_attachment_is_stored_privately_and_downloadable_only_by_admin(): void
    {
        Storage::fake('local');
        config(['inquiries.spam.enabled' => false]);
        $response = $this->post('/api/forms', ['formName' => 'serContactForm', 'payload' => [
            'name' => 'Jane Morrison', 'email' => 'jane@example.com', 'phone' => '+18322660227',
            'projectDetails' => 'A new storefront.',
            'attachment' => UploadedFile::fake()->create('brief.pdf', 12, 'application/pdf'),
        ]], ['Accept' => 'application/json'])->assertCreated();
        $inquiry = Inquiry::findOrFail($response->json('data.id'));
        $file = $inquiry->details['attachment'];
        Storage::disk('local')->assertExists($file['path']);
        $this->assertSame('brief.pdf', $file['name']);
        $this->get('/admin/inquiries/'.$inquiry->id.'/attachment')->assertRedirect('/login');
        $this->actingAsAdmin();
        $this->get('/admin/inquiries/'.$inquiry->id.'/attachment')->assertDownload('brief.pdf');
        $inquiry->delete();
        Storage::disk('local')->assertMissing($file['path']);
    }

    public function test_service_attachment_rejects_filenames_and_oversized_or_disallowed_files(): void
    {
        Storage::fake('local');
        foreach (['brief.pdf', UploadedFile::fake()->create('brief.pdf', 5121, 'application/pdf'),
            UploadedFile::fake()->create('script.php', 1, 'text/x-php')] as $file) {
            $this->post('/api/forms', ['formName' => 'serContactForm', 'payload' => [
                'name' => 'Jane Morrison', 'email' => 'jane@example.com', 'phone' => '+18322660227',
                'attachment' => $file,
            ]], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonStructure(['fieldErrors' => ['attachment']]);
        }
        $this->assertSame(0, Inquiry::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ─────────────────────────────────────────────────────────────
    // Successful submissions
    // ─────────────────────────────────────────────────────────────

    public function test_valid_quote_returns_201_and_is_stored(): void
    {
        $this->submit('contactHero', $this->validQuote())
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Your quote request has been sent successfully.',
            ])
            ->assertJsonStructure(['success', 'data' => ['id'], 'message']);

        $this->assertDatabaseHas('inquiries', [
            'email' => 'jane@example.com',
            'form_name' => 'contactHero',
            'status' => Inquiry::STATUS_NEW,
        ]);
    }

    public function test_mapped_fields_land_in_their_own_columns(): void
    {
        $this->submit('contactHero', $this->validQuote())->assertStatus(201);

        $inquiry = Inquiry::query()->firstOrFail();

        $this->assertSame('Jane Morrison', $inquiry->name);
        $this->assertSame('jane@example.com', $inquiry->email);
        $this->assertSame('+1 832 266 0227', $inquiry->phone);
        $this->assertSame('Please send a quote for our storefront.', $inquiry->message);
        $this->assertSame('Digital Marketing', $inquiry->type_of_service_required);
        $this->assertSame('Free quote request', $inquiry->subject);
    }

    public function test_form_specific_fields_are_kept_in_details(): void
    {
        $this->submit('contactHero', $this->validQuote())->assertStatus(201);

        $details = Inquiry::query()->firstOrFail()->details;

        $this->assertSame('United States', $details['country']);
        $this->assertSame('Acme', $details['brandName']);
        $this->assertSame('1000 - 5000', $details['budget']);
        $this->assertSame('$', $details['currency']);
        $this->assertSame('https://example.com', $details['websiteUrl']);
        $this->assertSame(['E-commerce Solutions'], $details['subscriptions']);
    }

    public function test_a_field_stored_in_a_column_is_not_repeated_in_details(): void
    {
        $this->submit('contactHero', $this->validQuote())->assertStatus(201);

        $details = Inquiry::query()->firstOrFail()->details;

        // Held in one place only, so the two can never disagree.
        foreach (['fullName', 'email', 'phone', 'message', 'service'] as $mapped) {
            $this->assertArrayNotHasKey($mapped, $details);
        }
    }

    public function test_newsletter_submission_needs_only_an_email(): void
    {
        $this->submit('heroNewsletter', ['email' => 'reader@example.com'])
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $inquiry = Inquiry::query()->firstOrFail();

        // The table requires a name and a message; neither is on this form, so
        // both are derived rather than being left to fail the insert.
        $this->assertSame('reader', $inquiry->name);
        $this->assertNotEmpty($inquiry->message);
        $this->assertSame('heroNewsletter', $inquiry->form_name);
    }

    /** @dataProvider formProvider */
    public function test_every_registered_form_can_be_submitted(string $formName, array $payload): void
    {
        $this->submit($formName, $payload)->assertStatus(201);

        $this->assertDatabaseHas('inquiries', ['form_name' => $formName]);
    }

    public static function formProvider(): array
    {
        return [
            'newsletter' => ['heroNewsletter', ['email' => 'reader@example.com']],
            'floating CTA' => ['floatingCtas', [
                'fullName' => 'Jane Morrison',
                'country' => 'United States',
                'phone' => '+1 832 266 0227',
                'email' => 'jane@example.com',
                'marketing' => 'SEO Optimization',
            ]],
            'service hero' => ['serviceHero', ['contactDetails' => 'jane@example.com']],
            'consultation' => ['serContactForm', [
                'name' => 'Jane Morrison',
                'email' => 'jane@example.com',
                'phone' => '+1 832 266 0227',
                'projectDetails' => 'A new storefront.',
            ]],
            'store audit' => ['sertHero', [
                'fullName' => 'Jane Morrison',
                'email' => 'jane@example.com',
                'phone' => '+1 832 266 0227',
                'products' => '50 - 100',
                'country' => 'United States',
                'storeUrl' => 'https://example.com',
                'budget' => '1000 - 5000',
                'expectations' => 'More sales, please.',
            ]],
        ];
    }

    public function test_a_single_detail_box_containing_an_email_fills_the_email_column(): void
    {
        $this->submit('serviceHero', ['contactDetails' => 'jane@example.com'])
            ->assertStatus(201);

        $inquiry = Inquiry::query()->firstOrFail();

        $this->assertSame('jane@example.com', $inquiry->email);
        $this->assertNull($inquiry->phone);
        // The raw text is still kept, so nothing the visitor typed is lost.
        $this->assertSame('jane@example.com', $inquiry->message);
    }

    public function test_a_single_detail_box_containing_a_phone_number_fills_the_phone_column(): void
    {
        $this->submit('serviceHero', ['contactDetails' => '+1 832 266 0227'])
            ->assertStatus(201);

        $inquiry = Inquiry::query()->firstOrFail();

        $this->assertSame('+1 832 266 0227', $inquiry->phone);
        $this->assertNull($inquiry->email);
    }

    public function test_a_single_detail_box_containing_free_text_is_still_accepted(): void
    {
        $this->submit('serviceHero', ['contactDetails' => 'call me about a website'])
            ->assertStatus(201);

        $inquiry = Inquiry::query()->firstOrFail();

        // Neither an email nor a number, but still a lead worth keeping.
        $this->assertNull($inquiry->email);
        $this->assertNull($inquiry->phone);
        $this->assertSame('call me about a website', $inquiry->message);
    }

    public function test_forms_that_ask_for_an_email_still_require_one(): void
    {
        // Relaxing the column must not relax the forms.
        foreach (['contactHero', 'heroNewsletter', 'floatingCtas', 'serContactForm', 'sertHero'] as $formName) {
            $this->assertStringContainsString(
                'required',
                FrontendFormRegistry::get($formName)['rules']['email'],
                "{$formName} no longer requires an email"
            );
        }
    }

    public function test_source_url_falls_back_to_the_referring_page(): void
    {
        $this->withHeader('Referer', 'https://bridgewaydigital.com/get-a-free-quote')
            ->postJson('/api/forms', [
                'formName' => 'contactHero',
                'payload' => $this->validQuote(),
            ])->assertStatus(201);

        $this->assertSame(
            'https://bridgewaydigital.com/get-a-free-quote',
            Inquiry::query()->firstOrFail()->source_url
        );
    }

    public function test_source_url_can_be_forwarded_by_the_frontend_proxy(): void
    {
        $this->postJson('/api/forms', [
            'formName' => 'contactHero',
            'payload' => $this->validQuote(),
            'sourceUrl' => 'https://bridgewaydigital.com/get-a-free-quote',
        ])->assertCreated();

        $this->assertSame(
            'https://bridgewaydigital.com/get-a-free-quote',
            Inquiry::query()->firstOrFail()->source_url
        );
    }

    public function test_every_clean_submission_is_visible_in_the_admin_inquiry_tab(): void
    {
        $this->submit('contactHero', $this->validQuote())->assertCreated();
        $inquiry = Inquiry::query()->firstOrFail();

        $this->actingAsAdmin();
        $this->get('/admin/inquiries')
            ->assertOk()
            ->assertSee('jane@example.com')
            ->assertSee('contactHero');

        $this->get('/admin/inquiries/'.$inquiry->id)
            ->assertOk()
            ->assertSee('contactHero')
            ->assertSee('Acme')
            ->assertSee('United States');
    }

    public function test_clean_submission_sends_an_admin_email_and_tracks_delivery(): void
    {
        Mail::fake();
        config([
            'inquiries.notifications.enabled' => true,
            'inquiries.recipients' => ['sales@bridgewaydigital.test'],
            'inquiries.spam.enabled' => false,
            'mail_outbox.enabled' => true,
            'mail_outbox.queue.enabled' => false,
        ]);

        $this->submit('contactHero', $this->validQuote())->assertCreated();

        Mail::assertSent(InquiryReceivedMail::class, function (InquiryReceivedMail $mail): bool {
            return $mail->hasTo('sales@bridgewaydigital.test')
                && str_contains($mail->render(), 'Acme')
                && str_contains($mail->render(), 'United States');
        });

        $this->assertDatabaseHas('mail_dispatches', [
            'type' => MailDispatch::TYPE_INQUIRY_RECEIVED,
            'status' => MailDispatch::STATUS_SENT,
            'attempts' => 1,
        ]);
    }

    public function test_phone_only_service_hero_inquiry_can_send_notification_without_reply_to(): void
    {
        Mail::fake();
        config([
            'inquiries.notifications.enabled' => true,
            'inquiries.recipients' => ['sales@bridgewaydigital.test'],
            'inquiries.spam.enabled' => false,
            'mail_outbox.enabled' => true,
            'mail_outbox.queue.enabled' => false,
        ]);

        $this->submit('serviceHero', ['contactDetails' => '+1 832 266 0227'])
            ->assertCreated();

        Mail::assertSent(InquiryReceivedMail::class, function (InquiryReceivedMail $mail): bool {
            return $mail->hasTo('sales@bridgewaydigital.test')
                && $mail->envelope()->replyTo === [];
        });
        $this->assertDatabaseHas('mail_dispatches', ['status' => MailDispatch::STATUS_SENT]);
    }

    // ─────────────────────────────────────────────────────────────
    // Validation failures
    // ─────────────────────────────────────────────────────────────

    public function test_invalid_email_returns_422_with_a_field_error(): void
    {
        $this->submit('contactHero', $this->validQuote(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['success', 'message', 'fieldErrors' => ['email']]);

        $this->assertDatabaseCount('inquiries', 0);
    }

    /** @dataProvider invalidPhoneProvider */
    public function test_phone_validation_matches_the_frontend(string $phone): void
    {
        $this->submit('contactHero', $this->validQuote(['phone' => $phone]))
            ->assertUnprocessable()
            ->assertJsonStructure(['fieldErrors' => ['phone']]);
    }

    public static function invalidPhoneProvider(): array
    {
        return [
            'too few digits' => ['123456'],
            'too many digits' => ['1234567890123456'],
            'letters' => ['+1 832 CALL-NOW'],
        ];
    }

    public function test_url_and_text_limits_match_the_frontend(): void
    {
        $this->submit('contactHero', $this->validQuote(['websiteUrl' => 'not a url']))
            ->assertUnprocessable()
            ->assertJsonStructure(['fieldErrors' => ['websiteUrl']]);

        $this->submit('contactHero', $this->validQuote(['message' => 'short']))
            ->assertUnprocessable()
            ->assertJsonStructure(['fieldErrors' => ['message']]);

        $this->submit('sertHero', [
            'fullName' => 'Jane Morrison',
            'email' => 'jane@example.com',
            'phone' => '+1 832 266 0227',
            'products' => '50 - 100',
            'country' => 'United States',
            'storeUrl' => 'javascript:alert(1)',
            'budget' => '1000 - 5000',
        ])->assertUnprocessable()->assertJsonStructure(['fieldErrors' => ['storeUrl']]);
    }

    public function test_field_errors_are_keyed_by_the_frontend_field_name(): void
    {
        $response = $this->submit('contactHero', $this->validQuote([
            'email' => 'nope',
            'fullName' => 'J',
            'brandName' => '',
        ]));

        $fieldErrors = $response->json('fieldErrors');

        // react-hook-form's setError() is called with these keys, so they must
        // be the form's own names — not Laravel's "payload.fullName".
        $this->assertArrayHasKey('email', $fieldErrors);
        $this->assertArrayHasKey('fullName', $fieldErrors);
        $this->assertArrayHasKey('brandName', $fieldErrors);
    }

    public function test_validation_messages_do_not_leak_the_payload_prefix(): void
    {
        $response = $this->submit('contactHero', $this->validQuote(['fullName' => 'J']));

        // This string is shown to the person filling in the form.
        $this->assertStringNotContainsString('payload.', $response->json('fieldErrors.fullName'));
        $this->assertStringContainsString('full name', $response->json('fieldErrors.fullName'));
    }

    public function test_placeholder_budget_option_is_rejected(): void
    {
        $this->submit('contactHero', $this->validQuote(['budget' => 'None']))
            ->assertStatus(422)
            ->assertJsonStructure(['fieldErrors' => ['budget']]);
    }

    public function test_missing_required_fields_return_422(): void
    {
        $this->submit('contactHero', ['email' => 'jane@example.com'])
            ->assertStatus(422)
            ->assertJsonStructure(['fieldErrors' => ['fullName', 'phone', 'country', 'service']]);
    }

    public function test_unknown_form_name_returns_422(): void
    {
        $this->submit('notARealForm', ['email' => 'jane@example.com'])
            ->assertStatus(422)
            ->assertJsonStructure(['fieldErrors' => ['formName']]);

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_missing_form_name_returns_422(): void
    {
        $this->postJson('/api/forms', ['payload' => []])
            ->assertStatus(422)
            ->assertJsonStructure(['fieldErrors' => ['formName']]);
    }

    public function test_missing_payload_returns_422(): void
    {
        $this->postJson('/api/forms', ['formName' => 'contactHero'])
            ->assertStatus(422)
            ->assertJsonStructure(['fieldErrors' => ['payload']]);
    }

    public function test_error_responses_use_the_same_envelope_as_field_errors(): void
    {
        // A bad envelope and a bad field both answer in one shape, so the
        // frontend has a single error format to read.
        $badEnvelope = $this->postJson('/api/forms', ['formName' => 'nope', 'payload' => []]);
        $badField = $this->submit('contactHero', $this->validQuote(['email' => 'nope']));

        foreach ([$badEnvelope, $badField] as $response) {
            $response->assertStatus(422)
                ->assertJsonStructure(['success', 'message', 'fieldErrors']);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Spam handling
    // ─────────────────────────────────────────────────────────────

    public function test_blocked_keyword_is_stored_as_spam_and_not_confirmed(): void
    {
        config(['inquiries.spam.blocked_keywords' => ['casino']]);

        $this->submit('contactHero', $this->validQuote([
            'message' => 'Visit my casino site for cheap traffic.',
        ]))
            ->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['fieldErrors']);

        // Kept for an admin to review rather than discarded, but never notified.
        $this->assertDatabaseHas('inquiries', [
            'email' => 'jane@example.com',
            'status' => Inquiry::STATUS_SPAM,
        ]);
    }

    public function test_spam_field_errors_are_reported_against_a_visible_field(): void
    {
        config(['inquiries.spam.blocked_keywords' => ['casino']]);

        $response = $this->submit('contactHero', $this->validQuote([
            'message' => 'casino links',
        ]));

        // The guard reports against the inquiry column; the user sees the form
        // field. For this form both are called "message".
        $this->assertArrayHasKey('message', $response->json('fieldErrors'));
    }

    public function test_honeypot_submission_is_marked_spam(): void
    {
        config([
            'inquiries.spam.honeypot_field' => 'company',
            'inquiries.spam.block_score' => 5,
        ]);

        $this->postJson('/api/forms', [
            'formName' => 'contactHero',
            'payload' => $this->validQuote(),
            'company' => 'a bot filled this in',
        ])->assertStatus(422);

        $this->assertDatabaseHas('inquiries', ['status' => Inquiry::STATUS_SPAM]);
    }

    // ─────────────────────────────────────────────────────────────
    // The registry itself
    // ─────────────────────────────────────────────────────────────

    public function test_every_registered_form_declares_rules_and_a_success_message(): void
    {
        foreach (FrontendFormRegistry::names() as $formName) {
            $form = FrontendFormRegistry::get($formName);

            $this->assertNotEmpty($form['rules'], "{$formName} has no rules");
            $this->assertNotEmpty($form['success'], "{$formName} has no success message");
            $this->assertNotEmpty($form['subject'], "{$formName} has no subject");
        }
    }

    public function test_every_mapped_field_is_one_the_form_validates(): void
    {
        foreach (FrontendFormRegistry::names() as $formName) {
            $form = FrontendFormRegistry::get($formName);

            foreach ($form['map'] as $column => $field) {
                // A map entry naming a field the form does not collect would
                // silently store null forever.
                $this->assertArrayHasKey(
                    $field,
                    $form['rules'],
                    "{$formName} maps {$column} to unvalidated field {$field}"
                );
            }
        }
    }

    public function test_every_mapped_column_is_fillable_on_the_inquiry_model(): void
    {
        $fillable = (new Inquiry)->getFillable();

        foreach (FrontendFormRegistry::names() as $formName) {
            foreach (FrontendFormRegistry::get($formName)['map'] as $column => $field) {
                $this->assertContains(
                    $column,
                    $fillable,
                    "{$formName} maps to non-fillable column {$column}"
                );
            }
        }
    }
}
