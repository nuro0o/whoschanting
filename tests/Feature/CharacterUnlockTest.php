<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\AccountProgression;
use App\Game\MatchEngine;
use App\Models\GameRoom;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CharacterUnlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([RoomUpdated::class]);
    }

    private function profile(User $user, array $attributes): PlayerProfile
    {
        app(AccountProgression::class)->rememberCharacter($user->id, 'mariner');
        $profile = PlayerProfile::findOrFail($user->id);
        $profile->update($attributes);

        return $profile->refresh();
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('A locked character was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('character', $exception->errors());
        }
    }

    public function test_catalog_keeps_the_original_cast_and_explains_four_locked_characters(): void
    {
        $progression = app(AccountProgression::class);
        $user = User::factory()->create();
        $starters = $progression->starterCharacterIds();
        $this->assertCount(16, $starters);
        $this->assertSame('mariner', $starters[0]);
        $this->assertSame('stranger', $starters[15]);
        foreach ([null, $user->id] as $account) {
            $catalog = collect($progression->characterCatalog($account));
            $this->assertCount(23, $catalog);
            $this->assertSame($starters, $catalog->where('unlocked', true)->pluck('id')->values()->all());
            foreach (array_keys(config('progression.character_unlocks')) as $id) {
                $this->assertFalse($catalog->firstWhere('id', $id)['unlocked']);
                $this->assertNotEmpty($catalog->firstWhere('id', $id)['requirement']);
            }
        }
        $this->actingAs($user)->get('/progression')->assertInertia(fn (Assert $page) => $page
            ->has('characters', 23)->where('characters.16.unlocked', false)->where('progression.characters.16.unlocked', false));
    }

    public function test_locked_choices_are_rejected_by_all_http_entry_points(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create();
        $room = app(MatchEngine::class)->create('host', 'Host');
        $this->actingAs($user);
        foreach (array_keys(config('progression.character_unlocks')) as $id) {
            $this->postJson('/rooms', ['name' => 'Player', 'character' => $id])->assertUnprocessable()->assertJsonValidationErrors('character');
            $this->postJson('/rooms/join', ['code' => $room->code, 'name' => 'Player', 'character' => $id])->assertUnprocessable()->assertJsonValidationErrors('character');
            $this->postJson('/account/customization', ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'character' => $id])
                ->assertUnprocessable()->assertJsonValidationErrors('character');
        }
        $this->assertDatabaseCount('player_profiles', 0);
        $code = $this->postJson('/rooms', ['name' => 'Player'])->assertCreated()->json('code');
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'phase_id' => 1, 'character' => 'tidecaller'])
            ->assertUnprocessable()->assertJsonValidationErrors('character');
    }

    public function test_engine_enforces_unlocks_for_creation_join_recovery_actions_and_remembering(): void
    {
        $engine = app(MatchEngine::class);
        $progression = app(AccountProgression::class);
        $user = User::factory()->create();
        $this->rejected(fn () => $engine->create('guest', 'Guest', 'tidecaller'));
        $this->rejected(fn () => $engine->create('user', 'Player', 'tidecaller', accountId: $user->id));
        $room = $engine->create('host', 'Host');
        $this->rejected(fn () => $engine->join($room->code, 'user', 'Player', 'tidecaller', $user->id));
        $engine->join($room->code, 'user', 'Player', 'mariner', $user->id);
        $this->rejected(fn () => $engine->join($room->code, 'recovered', 'Player', 'tidecaller', $user->id));
        $this->rejected(fn () => $engine->access($room->code, 'user', ['type' => 'character', 'phase_id' => 1, 'character' => 'tidecaller'], $user->id));
        $this->rejected(fn () => $progression->rememberCharacter($user->id, 'tidecaller'));
        $this->assertSame('mariner', $engine->access($room->code, 'user', accountId: $user->id)['me']['character']);
    }

    public function test_exact_level_and_achievement_thresholds_unlock_without_restricting_starters(): void
    {
        $progression = app(AccountProgression::class);
        $user = User::factory()->create();
        $profile = $this->profile($user, ['xp' => 249]);
        $this->assertFalse($progression->canUseCharacter($user->id, 'tidecaller'));
        $profile->update(['xp' => 250]);
        $this->assertTrue($progression->canUseCharacter($user->id, 'tidecaller'));
        $this->assertFalse($progression->canUseCharacter($user->id, 'cartographer'));
        $profile->update(['xp' => 749]);
        $this->assertFalse($progression->canUseCharacter($user->id, 'cartographer'));
        $profile->update(['xp' => 750, 'achievements' => ['many_faces' => now()->toISOString(), 'veteran' => now()->toISOString()]]);
        foreach (config('progression.character_unlocks') as $id => $unlock) {
            $this->assertSame(! ($unlock['seasonal'] ?? false), $progression->canUseCharacter($user->id, $id));
        }
        $user->forceFill(['email_verified_at' => null])->save();
        $this->assertFalse($progression->canUseCharacter($user->id, 'tidecaller'));
    }

    public function test_earned_character_can_be_equipped_created_changed_and_recovered(): void
    {
        $user = User::factory()->create();
        $this->profile($user, ['xp' => 750]);
        $this->actingAs($user)->postJson('/account/customization', ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'character' => 'tidecaller'])
            ->assertOk()->assertJsonPath('progression.profile.equipped.character', 'tidecaller');
        $code = $this->postJson('/rooms', ['name' => 'Player'])->assertCreated()->json('code');
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.character', 'tidecaller');
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'phase_id' => 1, 'character' => 'cartographer'])->assertOk()->assertJsonPath('me.character', 'cartographer');
        $this->flushSession();
        $this->actingAs($user)->postJson('/rooms/join', ['code' => $code, 'name' => 'Player'])->assertOk();
        $this->getJson('/rooms/'.$code.'/state')->assertJsonPath('me.character', 'cartographer');
        $this->assertCount(1, GameRoom::where('code', $code)->firstOrFail()->state['players']);
    }

    public function test_stale_locked_preferences_fall_back_without_blocking_room_creation(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile($user, []);
        $profile->update(['customization' => [...$profile->customization, 'character' => 'tidecaller']]);
        $progression = app(AccountProgression::class);
        $this->assertNull($progression->preferredCharacter($user->id));
        $this->assertNull($progression->view($user->id)['profile']['equipped']['character']);
        $code = $this->actingAs($user)->withSession(['chanting.characters.'.$user->id => 'maskmaker'])
            ->postJson('/rooms', ['name' => 'Player'])->assertCreated()->json('code');
        $view = $this->getJson('/rooms/'.$code.'/state')->assertOk()->json();
        $this->assertContains($view['me']['character'], $progression->starterCharacterIds());
    }

    public function test_guest_random_and_legacy_fallbacks_stay_in_the_original_ordered_pool(): void
    {
        $engine = app(MatchEngine::class);
        $starters = app(AccountProgression::class)->starterCharacterIds();
        for ($i = 0; $i < 12; $i++) {
            $room = $engine->create('guest-'.$i, 'Guest');
            $state = $room->state;
            $seatId = array_key_first($state['players']);
            $this->assertContains($state['players'][$seatId]['character'], $starters);
            unset($state['players'][$seatId]['character']);
            $room->update(['state' => $state]);
            $expected = $starters[hexdec(substr(hash('sha256', $seatId), 0, 6)) % 16];
            $this->assertSame($expected, $engine->access($room->code, 'guest-'.$i)['me']['character']);
        }
    }
}
