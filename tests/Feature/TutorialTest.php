<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\MatchReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_practice_without_creating_a_room_or_receiving_rewards(): void
    {
        $this->withoutVite();

        $this->get(route('tutorial'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tutorial')
                ->missing('code')
                ->missing('state')
                ->where('auth.user', null));

        $this->assertDatabaseCount(GameRoom::class, 0);
        $this->assertDatabaseCount(GameMatch::class, 0);
        $this->assertDatabaseCount(MatchReward::class, 0);
    }

    public function test_tutorial_is_available_before_email_verification(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('tutorial'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tutorial'));

        $this->assertDatabaseCount(GameRoom::class, 0);
        $this->assertDatabaseCount(MatchReward::class, 0);
    }
}
