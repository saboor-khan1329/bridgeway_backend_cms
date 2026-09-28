<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InquiryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable notifications by default so tests don't need mail config
        config([
            'inquiries.notifications.enabled' => false,
            'inquiries.captcha.enabled' => false,
            'mail_outbox.enabled' => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Valid submission
    // ─────────────────────────────────────────────────────────────

    public function test_valid_inquiry_returns_201_and_is_stored(): void
    {
        $response = $this->postJson('/api/inquiries', [
            'name' => 'John Smith',
            'email' => 'john@example.com',
            'phone' => '+44 20 1234 5678',
            'subject' => 'Project inquiry',
            'type_of_service_required' => 'Amazon Marketing Services',
            'message' => 'I would like to discuss your security services.',
            'source_url' => 'https://bridgewaydigital.com/get-a-free-quote',
            'form_started_at' => now()->subSeconds(10)->timestamp,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Thank you. We will contact you shortly.',
            ]);

        $this->assertDatabaseHas('inquiries', [
            'email' => 'john@example.com',
            'status' => Inquiry::STATUS_NEW,
        ]);
    }

    public function test_valid_inquiry_saves_type_of_service_required(): void
    {
        $this->postJson('/api/inquiries', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'type_of_service_required' => 'Web Development and Amazon Marketing',
            'message' => 'Please send more information about your digital services.',
        ])->assertStatus(201);

        $inquiry = Inquiry::query()->where('email', 'jane@example.com')->first();
        $this->assertNotNull($inquiry);
        $this->assertSame('Web Development and Amazon Marketing', $inquiry->type_of_service_required);
    }

    public function test_valid_inquiry_without_optional_fields(): void
    {
        $response = $this->postJson('/api/inquiries', [
            'name' => 'Alex User',
            'email' => 'alex@example.com',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'Please contact me about this service.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('inquiries', 1);
    }

    // ─────────────────────────────────────────────────────────────
    // Validation errors
    // ─────────────────────────────────────────────────────────────

    public function test_missing_required_fields_returns_422(): void
    {
        $response = $this->postJson('/api/inquiries', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'message', 'type_of_service_required']);
    }

    public function test_invalid_email_returns_422(): void
    {
        $response = $this->postJson('/api/inquiries', [
            'name' => 'Test',
            'email' => 'not-an-email',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'Some message.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_message_too_long_returns_422(): void
    {
        $response = $this->postJson('/api/inquiries', [
            'name' => 'Test',
            'email' => 'alex@example.com',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => str_repeat('a', 5001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_type_of_service_required_must_be_a_string(): void
    {
        $response = $this->postJson('/api/inquiries', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'message' => 'Valid message.',
            'type_of_service_required' => ['not-a-string'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type_of_service_required']);
    }

    // ─────────────────────────────────────────────────────────────
    // Spam handling — silently saved, fake 201, NOT emailed
    // ─────────────────────────────────────────────────────────────

    public function test_spam_keyword_inquiry_is_saved_as_spam_and_returns_422(): void
    {
        config(['inquiries.spam.enabled' => true]);

        SiteSetting::put('inquiry_spam_detection_enabled', '1', 'inquiries');
        SiteSetting::put('inquiry_spam_block_score', '5', 'inquiries');
        SiteSetting::put('inquiry_blocked_keywords', "090078601\ncasino bonus", 'inquiries');

        $response = $this->postJson('/api/inquiries', [
            'name' => 'Spam Sender',
            'email' => 'spam@example.com',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'Call me on 090078601 for a casino bonus.',
            'form_started_at' => now()->subSeconds(15)->timestamp,
        ]);

        // Returns fake success — bots cannot detect they were blocked
        $response->assertStatus(422)->assertJson(['success' => false]);

        // Stored in DB as spam
        $this->assertDatabaseHas('inquiries', [
            'email' => 'spam@example.com',
            'status' => Inquiry::STATUS_SPAM,
        ]);
    }

    public function test_honeypot_filled_triggers_spam_block(): void
    {
        config([
            'inquiries.spam.enabled' => true,
            'inquiries.spam.honeypot_field' => 'company',
            'inquiries.spam.block_score' => 5,
        ]);

        $response = $this->postJson('/api/inquiries', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'I am a bot.',
            'company' => 'BotCorp',  // honeypot filled
            'form_started_at' => now()->subSeconds(5)->timestamp,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);

        $this->assertDatabaseHas('inquiries', [
            'email' => 'bot@example.com',
            'status' => Inquiry::STATUS_SPAM,
        ]);
    }

    public function test_field_specific_spam_rule_blocks_matching_value(): void
    {
        config(['inquiries.spam.enabled' => true]);

        SiteSetting::put('inquiry_spam_detection_enabled', '1', 'inquiries');
        SiteSetting::put('inquiry_spam_block_score', '5', 'inquiries');
        SiteSetting::put('inquiry_field_block_rules', 'phone=090078601,123456', 'inquiries');

        $response = $this->postJson('/api/inquiries', [
            'name' => 'Bad Phone',
            'email' => 'badphone@example.com',
            'phone' => '090078601',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'Please call me.',
            'form_started_at' => now()->subSeconds(15)->timestamp,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['errors' => ['phone']]);

        $this->assertDatabaseHas('inquiries', [
            'email' => 'badphone@example.com',
            'status' => Inquiry::STATUS_SPAM,
        ]);
    }

    public function test_form_submitted_too_fast_adds_spam_score(): void
    {
        config([
            'inquiries.spam.enabled' => true,
            'inquiries.spam.minimum_seconds' => 5,
            // block_score > 3 so fast-submit alone won't block (score=3 < block_score default 5)
            'inquiries.spam.block_score' => 10,
        ]);

        // Submitted instantly (0 seconds after form load)
        $response = $this->postJson('/api/inquiries', [
            'name' => 'Fast Bot',
            'email' => 'fast@example.com',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'Very quick submission.',
            'form_started_at' => now()->timestamp,  // submitted right now
        ]);

        // Score 3 < block_score 10 → passes through (saved as STATUS_NEW, not spam)
        $response->assertStatus(201);

        $inquiry = Inquiry::query()->where('email', 'fast@example.com')->first();
        $this->assertNotNull($inquiry);
        $this->assertGreaterThan(0, $inquiry->spam_score);
    }

    public function test_spam_inquiry_does_not_trigger_mail_dispatch(): void
    {
        Queue::fake();

        config([
            'inquiries.notifications.enabled' => true,
            'inquiries.spam.enabled' => true,
            'mail_outbox.enabled' => true,
            'mail_outbox.queue.enabled' => true,
        ]);

        SiteSetting::put('inquiry_spam_detection_enabled', '1', 'inquiries');
        SiteSetting::put('inquiry_spam_block_score', '5', 'inquiries');
        SiteSetting::put('inquiry_blocked_keywords', 'blockedword', 'inquiries');

        $this->postJson('/api/inquiries', [
            'name' => 'Spammer',
            'email' => 'spammer@example.com',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'This contains a blockedword here.',
            'form_started_at' => now()->subSeconds(15)->timestamp,
        ]);

        Queue::assertNothingPushed();
    }

    // ─────────────────────────────────────────────────────────────
    // Stores meta fields
    // ─────────────────────────────────────────────────────────────

    public function test_inquiry_stores_ip_and_user_agent(): void
    {
        $this->postJson('/api/inquiries', [
            'name' => 'Metadata Test',
            'email' => 'meta@example.com',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'Testing metadata storage.',
        ], ['User-Agent' => 'TestBrowser/1.0']);

        $inquiry = Inquiry::query()->where('email', 'meta@example.com')->firstOrFail();

        $this->assertNotNull($inquiry->ip);
        $this->assertStringContainsString('TestBrowser', $inquiry->user_agent);
    }

    public function test_inquiry_stores_source_url(): void
    {
        $this->postJson('/api/inquiries', [
            'name' => 'Source URL Test',
            'email' => 'source@example.com',
            'type_of_service_required' => 'Digital Marketing Services',
            'message' => 'Testing source_url storage.',
            'source_url' => 'https://bridgewaydigital.com/get-a-free-quote',
        ]);

        $this->assertDatabaseHas('inquiries', [
            'email' => 'source@example.com',
            'source_url' => 'https://bridgewaydigital.com/get-a-free-quote',
        ]);
    }
}

