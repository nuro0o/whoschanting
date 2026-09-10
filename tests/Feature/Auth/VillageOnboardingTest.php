<?php

namespace Tests\Feature\Auth;

use App\Game\MatchEngine;
use App\Jobs\SendWelcomeEmail;
use App\Mail\WelcomeToVillage;
use App\Models\User;
use App\Notifications\VerifyVillageEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class VillageOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_unverified_accounts_cannot_bypass_verification_through_game_endpoints(): void
    {
        $user = User::factory()->unverified()->create();
        $room = app(MatchEngine::class)->create('onboarding-seat', 'Neighbor');
        $this->actingAs($user)->withSession(['chanting.identity' => 'onboarding-seat']);
        $this->get('/')->assertRedirect(route('verification.notice'));
        $this->get('/rooms/'.$room->code)->assertRedirect(route('verification.notice'));
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->get('/settings/profile')->assertOk();
        $this->get('/email/verify')->assertOk();
        $this->getJson('/rooms/'.$room->code.'/state')->assertForbidden();
        $this->postJson('/rooms', ['name' => 'New neighbor', 'character' => 'baker'])->assertForbidden();
        $this->postJson('/rooms/join', ['name' => 'Joined neighbor', 'code' => $room->code])->assertForbidden();
        $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'ready', 'phase_id' => 1])->assertForbidden();
        $this->postJson('/rooms/'.$room->code.'/broadcast-auth', [])->assertForbidden();
        $this->assertFalse($room->fresh()->state['players'][$room->state['host_id']]['ready']);
    }

    public function test_unverified_login_leads_to_verification_and_logout_restores_guest_play(): void
    {
        $user = User::factory()->unverified()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertRedirect(route('verification.notice'));
        $this->post('/logout')->assertRedirect('/');
        $this->get('/')->assertOk();
        $this->postJson('/rooms', ['name' => 'Guest neighbor'])->assertCreated();
    }

    public function test_signed_confirmation_unlocks_accounts_and_queues_welcome_only_on_verification(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($url)->assertRedirect('/?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Queue::assertPushed(SendWelcomeEmail::class, fn (SendWelcomeEmail $job): bool => $job->userId === $user->id && $job->afterCommit === true);
        $this->get($url)->assertRedirect('/?verified=1');
        Queue::assertPushed(SendWelcomeEmail::class, 1);
        $this->postJson('/rooms', ['name' => 'Verified neighbor', 'character' => 'baker'])->assertCreated();
    }

    public function test_verification_links_expire_and_cannot_be_tampered_with(): void
    {
        Queue::fake();
        $user = User::factory()->unverified()->create();
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $valid = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($expired)->assertForbidden();
        $this->get($valid.'&tampered=1')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Queue::assertNotPushed(SendWelcomeEmail::class);
    }

    public function test_email_change_requires_fresh_verification_and_old_link_is_invalid(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $oldUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->patch('/settings/profile', ['name' => $user->name, 'email' => 'changed@example.com'])->assertRedirect('/settings/profile');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyVillageEmail::class, fn (VerifyVillageEmail $notification): bool => $notification->email === 'changed@example.com');
        $this->get($oldUrl)->assertForbidden();
        $this->postJson('/rooms', ['name' => 'Neighbor'])->assertForbidden();
    }

    public function test_verification_notification_has_signed_url_and_skips_outdated_queued_messages(): void
    {
        $user = User::factory()->unverified()->create();
        $notification = new VerifyVillageEmail($user->email);
        $mail = $notification->toMail($user);
        $this->assertTrue(URL::hasValidSignature(Request::create($mail->viewData['verificationUrl'])));
        $this->assertSame(['emails.verify', 'emails.verify-text'], $mail->view);
        $this->assertTrue($notification->afterCommit);
        $this->assertTrue($notification->shouldSend($user, 'mail'));
        $user->email = 'changed@example.com';
        $this->assertFalse($notification->shouldSend($user, 'mail'));
        $user->email = $notification->email;
        $user->markEmailAsVerified();
        $this->assertFalse($notification->shouldSend($user, 'mail'));
    }

    public function test_welcome_delivery_is_not_repeated_and_requires_verified_email(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $job = new SendWelcomeEmail($user->id);
        $job->handle();
        Mail::assertNothingSent();
        $this->assertNull($user->fresh()->welcome_email_sent_at);
        $user->markEmailAsVerified();
        $job->handle();
        $job->handle();
        Mail::assertSent(WelcomeToVillage::class, fn (WelcomeToVillage $mail): bool => $mail->hasTo($user->email) && $mail->name === $user->name);
        Mail::assertSentCount(1);
        $this->assertNotNull($user->fresh()->welcome_email_sent_at);
        $user->forceFill(['email' => 'changed@example.com', 'email_verified_at' => null])->save();
        $user->markEmailAsVerified();
        $job->handle();
        Mail::assertSentCount(1);
    }

    public function test_failed_welcome_delivery_can_be_retried(): void
    {
        $user = User::factory()->create();
        Mail::shouldReceive('to')->once()->with($user->email)->andThrow(new \RuntimeException('Transport unavailable'));
        try {
            (new SendWelcomeEmail($user->id))->handle();
            $this->fail('Mail failure should be retried by the queue.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Transport unavailable', $error->getMessage());
        }
        $this->assertNull($user->fresh()->welcome_email_sent_at);
    }

    public function test_verification_resends_are_rate_limited(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->unverified()->create());
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('verification.send'))->assertRedirect();
        }
        $this->post(route('verification.send'))->assertTooManyRequests();
    }

    public function test_emails_render_with_inline_artwork_plain_text_and_safe_names(): void
    {
        config(['mail.default' => 'array']);
        $user = User::factory()->unverified()->create(['name' => '<script>alert(1)</script>']);
        $notification = new VerifyVillageEmail($user->email);
        $url = $notification->toMail($user)->viewData['verificationUrl'];
        $user->notifyNow($notification);
        Mail::to($user->email)->send(new WelcomeToVillage($user->name));
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $sent) {
            $email = $sent->getOriginalMessage();
            $this->assertInstanceOf(Email::class, $email);
            $this->assertStringContainsString('cid:', $email->getHtmlBody());
            $this->assertStringContainsString('&lt;script&gt;', $email->getHtmlBody());
            $this->assertStringNotContainsString('<script>', $email->getHtmlBody());
            $this->assertNotEmpty($email->getTextBody());
            $this->assertCount(1, $email->getAttachments());
            $this->assertSame('inline', $email->getAttachments()[0]->getDisposition());
        }
        $verify = $messages[0]->getOriginalMessage();
        $this->assertSame('verify-email-ferryman.png', $verify->getAttachments()[0]->getFilename());
        $this->assertStringContainsString('offers you a hand aboard his boat', $verify->getHtmlBody());
        $this->assertStringContainsString($url, $verify->getTextBody());
        $this->assertStringContainsString('Verify my email', $verify->getHtmlBody());
        $welcome = $messages[1]->getOriginalMessage();
        $this->assertSame('welcome-email-ferryman.png', $welcome->getAttachments()[0]->getFilename());
        $this->assertStringContainsString('rows you toward the lantern-lit village', $welcome->getHtmlBody());
        $this->assertStringContainsString('Welcome to Who', $welcome->getSubject());
        $this->assertStringContainsString(route('home'), $welcome->getTextBody());
    }
}
