<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\AccountProgression;
use App\Game\MatchEngine;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CharacterCustomizerTest extends TestCase
{
    use RefreshDatabase;

    private array $outfit = ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'storm', 'background' => 'harbor', 'character' => 'mariner', 'creator' => null];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([RoomUpdated::class]);
    }

    public function test_new_accounts_can_customize_and_keep_their_look_across_sessions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/account/customization', $this->outfit)->assertOk()
            ->assertJsonPath('progression.profile.equipped.background', 'harbor')
            ->assertJsonPath('progression.profile.equipped.accent', 'storm');
        $this->flushSession();
        $this->actingAs($user)->get('/progression')->assertInertia(fn (Assert $page) => $page
            ->where('progression.profile.equipped', $this->outfit)
            ->has('progression.cosmetics.backgrounds', 4));
        $code = $this->postJson('/rooms', ['name' => 'Mara'])->assertCreated()->json('code');
        $this->getJson('/rooms/'.$code.'/state')->assertOk()
            ->assertJsonPath('me.character', 'mariner')
            ->assertJsonPath('me.customization.background', 'harbor')
            ->assertJsonPath('players.0.customization.accent', 'storm');
    }

    public function test_legacy_profiles_and_requests_preserve_saved_customization(): void
    {
        $user = User::factory()->create();
        $progression = app(AccountProgression::class);
        $progression->rememberCharacter($user->id, 'archivist');
        $legacy = ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'character' => 'archivist'];
        PlayerProfile::findOrFail($user->id)->update(['customization' => $legacy]);
        $this->assertEquals([...$legacy, 'background' => 'plain', 'creator' => null], $progression->view($user->id)['profile']['equipped']);
        $this->assertSame('plain', $progression->appearance($user->id)['background']);
        $this->actingAs($user)->postJson('/account/customization', $legacy)->assertOk()
            ->assertJsonPath('progression.profile.equipped.background', 'plain');
        $this->postJson('/account/customization', [...$legacy, 'background' => 'harbor'])->assertOk();
        $this->postJson('/account/customization', [...$legacy, 'accent' => 'clay'])->assertOk()
            ->assertJsonPath('progression.profile.equipped.background', 'harbor');
        $progression->rememberCharacter($user->id, 'mariner');
        $this->assertSame('harbor', $progression->appearance($user->id)['background']);
    }

    public function test_invalid_and_locked_backgrounds_cannot_change_the_saved_look(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/account/customization', $this->outfit)->assertOk();
        foreach (['dusk', 'candlelight', 'invented', 'url(https://example.test/a.png)', null, ['harbor']] as $invalid) {
            $this->postJson('/account/customization', [...$this->outfit, 'accent' => 'clay', 'background' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('background');
            $this->assertSame($this->outfit, PlayerProfile::findOrFail($user->id)->customization);
        }
        $this->actingAs(User::factory()->unverified()->create())
            ->postJson('/account/customization', $this->outfit)->assertForbidden();
    }

    public function test_backgrounds_unlock_at_lifetime_level_boundaries(): void
    {
        $user = User::factory()->create();
        $progression = app(AccountProgression::class);
        $progression->rememberCharacter($user->id, 'mariner');
        $profile = PlayerProfile::findOrFail($user->id);
        foreach ([249 => ['plain', 'harbor'], 250 => ['plain', 'harbor', 'dusk'], 749 => ['plain', 'harbor', 'dusk'], 750 => ['plain', 'harbor', 'dusk', 'candlelight']] as $xp => $unlocked) {
            $profile->update(['xp' => $xp]);
            $catalog = $progression->view($user->id)['cosmetics']['backgrounds'];
            $this->assertSame($unlocked, array_column(array_filter($catalog, fn (array $item): bool => $item['unlocked']), 'id'));
            $this->actingAs($user)->postJson('/account/customization', [...$this->outfit, 'background' => end($unlocked)])->assertOk();
        }
        $this->travelTo(now()->addMonths(4));
        $this->assertSame('candlelight', $progression->view($user->id)['profile']['equipped']['background']);
        $this->assertTrue(collect($progression->view($user->id)['cosmetics']['backgrounds'])->firstWhere('id', 'candlelight')['unlocked']);
    }

    public function test_a_match_snapshots_the_saved_look_and_keeps_it_during_play(): void
    {
        $user = User::factory()->create();
        $progression = app(AccountProgression::class);
        $engine = app(MatchEngine::class);
        $progression->customize($user->id, $this->outfit);
        $room = $engine->create('host', 'Mara', setup: ['mode' => 'custom', 'roles' => ['townsperson' => 2, 'veilweaver' => 1]], accountId: $user->id);
        $engine->join($room->code, 'guest-1', 'Guest one');
        $engine->join($room->code, 'guest-2', 'Guest two');
        $act = fn (string $identity, ?int $account, string $type) => $engine->access($room->code, $identity,
            ['phase_id' => $room->fresh()->state['phase_id'], 'type' => $type], $account);
        $progression->customize($user->id, [...$this->outfit, 'accent' => 'clay']);
        $act('host', $user->id, 'ready');
        $act('guest-1', null, 'ready');
        $act('guest-2', null, 'ready');
        $started = $act('host', $user->id, 'start');
        $this->assertSame('clay', $started['me']['customization']['accent']);
        $this->assertSame('harbor', $started['me']['customization']['background']);
        $progression->customize($user->id, [...$this->outfit, 'background' => 'plain']);
        $engine->join($room->code, 'new-device', 'Mara', accountId: $user->id);
        $during = $engine->access($room->code, 'new-device', accountId: $user->id);
        $this->assertSame('harbor', $during['me']['customization']['background']);
        $this->assertSame('clay', $during['me']['customization']['accent']);
        $guest = $engine->access($room->code, 'guest-1');
        $this->assertNull($guest['me']['customization']);
    }
}
