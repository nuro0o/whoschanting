<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_unverified_accounts_must_verify_before_visiting_the_dashboard(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_dashboard_shows_the_current_accounts_character_and_security_summary_without_secrets(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $other = User::factory()->create();
        DB::table('passkeys')->insert([
            ['user_id' => $user->id, 'name' => 'My passkey', 'credential_id' => 'own-key', 'credential' => '{}'],
            ['user_id' => $other->id, 'name' => 'Other passkey', 'credential_id' => 'other-key', 'credential' => '{}'],
        ]);

        $this->actingAs($user)
            ->withSession(['chanting.characters.'.$user->id => 'ferryman'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('characters', config('game.characters'))
                ->where('preferredCharacter', 'ferryman')
                ->where('rules.min_players', config('game.min_players'))
                ->where('rules.max_players', config('game.max_players'))
                ->where('twoFactorEnabled', true)
                ->where('passkeyCount', 1)
                ->where('canManageTwoFactor', true)
                ->where('canManagePasskeys', true)
                ->where('auth.user.id', $user->id)
                ->missing('auth.user.password')
                ->missing('auth.user.two_factor_secret')
                ->missing('auth.user.two_factor_recovery_codes')
                ->missing('passkeys'),
            );
    }

    public function test_dashboard_does_not_use_another_accounts_remembered_character(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['chanting.characters.'.$other->id => 'trickster'])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('preferredCharacter', null)
                ->where('twoFactorEnabled', false)
                ->where('passkeyCount', 0),
            );
    }

    public function test_dashboard_respects_disabled_optional_security_features(): void
    {
        config(['fortify.features' => [Features::emailVerification()]]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('canManageTwoFactor', false)
                ->where('canManagePasskeys', false)
                ->where('passkeyCount', 0),
            );
    }
}
