<?php

namespace Tests\Feature;

use App\Mail\WithdrawalAcknowledgment;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\LegalDocuments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        Mail::fake();
        Notification::fake();
        Http::preventStrayRequests();
    }

    private function withdrawal(): array
    {
        return ['request_id' => (string) Str::uuid(), 'name' => 'Mara', 'email' => 'mara@example.test',
            'order_reference' => 'cs_test_example', 'message' => null, 'confirmation' => true, 'website' => ''];
    }

    public function test_all_documents_are_public_for_guests_and_unverified_accounts(): void
    {
        foreach (['contact', 'privacy', 'terms', 'refunds'] as $document) {
            $response = $this->get('/'.$document)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Legal')->where('document.id', $document)
                ->where('legal.support_email', 'whoschanting.support@geniousverse.app')
                ->where('legal.version', config('legal.version')));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->actingAs(User::factory()->unverified()->create());
        foreach (['contact', 'privacy', 'terms', 'refunds'] as $document) {
            $this->get('/'.$document)->assertOk();
        }
    }

    public function test_actual_operator_details_are_exposed_without_inventing_missing_fields(): void
    {
        config(['legal.operator_name' => null, 'legal.business_address' => null, 'legal.registration_number' => null, 'legal.minimum_age' => null]);
        $this->assertNull(app(LegalDocuments::class)->details()['operator_name']);
        config(['legal.operator_name' => 'Example Operator', 'legal.business_address' => 'Example Address',
            'legal.registration_number' => '123', 'legal.minimum_age' => '16']);
        $this->get('/terms')->assertInertia(fn (Assert $page) => $page->where('legal.operator_name', 'Example Operator')
            ->where('legal.minimum_age', 16)->where('legal.business_address', 'Example Address'));
    }

    public function test_registration_requires_active_terms_and_records_server_time_and_version(): void
    {
        $input = ['name' => 'New Resident', 'email' => 'new@example.test', 'password' => 'password', 'password_confirmation' => 'password'];
        $this->postJson('/register', $input)->assertUnprocessable()->assertJsonValidationErrors(['terms', 'terms_version']);
        $this->postJson('/register', [...$input, 'terms' => false, 'terms_version' => config('legal.version')])
            ->assertUnprocessable()->assertJsonValidationErrors('terms');
        $this->postJson('/register', [...$input, 'terms' => true, 'terms_version' => 'old'])
            ->assertUnprocessable()->assertJsonValidationErrors('terms_version');
        $this->assertDatabaseCount('users', 0);
        $this->post('/register', [...$input, 'terms' => true, 'terms_version' => config('legal.version'), 'terms_accepted_at' => '1990-01-01'])
            ->assertRedirect();
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame(config('legal.version'), $user->getAttribute('terms_version'));
        $this->assertTrue($user->getAttribute('terms_accepted_at')->isToday());
    }

    public function test_checkout_requires_explicit_current_terms_before_contacting_stripe(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack'])->assertUnprocessable()->assertJsonValidationErrors('terms');
        $this->postJson('/account/store/checkout', ['bundle_id' => 'founders-pack', 'terms' => true, 'terms_version' => 'old'])
            ->assertUnprocessable()->assertJsonValidationErrors('terms_version');
        Http::assertNothingSent();
        $this->assertDatabaseCount('paid_orders', 0);
    }

    public function test_guest_can_submit_without_reason_or_login_and_retry_has_one_receipt(): void
    {
        $input = $this->withdrawal();
        $first = $this->postJson('/refunds', $input)->assertCreated()->assertJsonPath('reference', $input['request_id']);
        $this->postJson('/refunds', $input)->assertCreated()->assertExactJson($first->json());
        $this->assertDatabaseCount('withdrawal_requests', 1);
        Mail::assertQueued(WithdrawalAcknowledgment::class, 2);
        Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($mail): bool => $mail->forSupport && $mail->hasTo('whoschanting.support@geniousverse.app'));
        Mail::assertQueued(WithdrawalAcknowledgment::class, fn ($mail): bool => ! $mail->forSupport && $mail->hasTo('mara@example.test')
            && $mail->details['reference'] === $input['request_id'] && $mail->details['received_at'] === $first->json('received_at'));
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $input['request_id'],
            'declaration' => 'I notify you that I withdraw from the purchase identified above.', 'message' => null]);
        $this->assertDatabaseCount('paid_orders', 0);
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $this->assertSame($first->json(), session('withdrawal_receipt'));
        $this->get('/refunds')->assertInertia(fn (Assert $page) => $page->where('supportRequest', $first->json()));
        $this->get('/contact')->assertInertia(fn (Assert $page) => $page->where('supportRequest', null));
        $this->flushSession();
        $this->get('/refunds')->assertInertia(fn (Assert $page) => $page->where('supportRequest', null));
    }

    public function test_confirmation_honeypot_and_input_limits_are_enforced_before_sending(): void
    {
        $input = $this->withdrawal();
        $this->postJson('/refunds', [...$input, 'confirmation' => false])->assertUnprocessable()->assertJsonValidationErrors('confirmation');
        $this->postJson('/refunds', [...$input, 'website' => 'bot.example'])->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->postJson('/refunds', [...$input, 'message' => str_repeat('x', 2001), 'email' => 'invalid', 'request_id' => 'not-a-uuid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['message', 'email', 'request_id']);
        Mail::assertNothingQueued();
        $this->assertDatabaseCount('withdrawal_requests', 0);
    }

    public function test_reusing_request_id_with_different_details_cannot_change_record(): void
    {
        $input = $this->withdrawal();
        $this->postJson('/refunds', $input)->assertCreated();
        $this->postJson('/refunds', [...$input, 'email' => 'other@example.test'])->assertConflict();
        $this->assertDatabaseHas('withdrawal_requests', ['id' => $input['request_id'], 'email' => 'mara@example.test']);
        Mail::assertQueued(WithdrawalAcknowledgment::class, 2);
    }

    public function test_real_database_queue_and_marker_commit_once_without_sending_email(): void
    {
        Mail::swap(new MailManager($this->app));
        $input = $this->withdrawal();
        $this->postJson('/refunds', $input)->assertCreated();
        $this->postJson('/refunds', $input)->assertCreated();
        $this->assertDatabaseCount('jobs', 2);
        $this->assertNotNull(WithdrawalRequest::findOrFail($input['request_id'])->getAttribute('acknowledgment_queued_at'));
    }

    public function test_retention_prunes_old_requests_but_keeps_recent_ones(): void
    {
        $this->postJson('/refunds', $this->withdrawal())->assertCreated();
        $old = WithdrawalRequest::firstOrFail();
        $old->forceFill(['created_at' => now()->subDays(366)])->save();
        $this->postJson('/refunds', $this->withdrawal())->assertCreated();
        $this->artisan('support:prune')->assertSuccessful();
        $this->assertDatabaseCount('withdrawal_requests', 1);
        $this->assertDatabaseMissing('withdrawal_requests', ['id' => $old->id]);
    }
}
