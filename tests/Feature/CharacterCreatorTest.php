<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\AccountProgression;
use App\Game\CharacterCreator;
use App\Game\MatchEngine;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CharacterCreatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['character_creator.enabled' => true]);
        $this->withoutVite();
        Event::fake([RoomUpdated::class]);
    }

    private function look(array $recipe = []): array
    {
        return ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'background' => 'plain', 'character' => 'custom',
            'creator' => [...config('character_creator.default'), ...$recipe]];
    }

    public function test_mirror_catalog_and_a_new_character_persist_into_rooms_and_lobby_selection(): void
    {
        $user = User::factory()->create();
        $look = $this->look(['body_type' => 'type2', 'pose' => 'three_quarter', 'face' => 'keen', 'hair' => 'braid', 'hat' => 'widebrim', 'outfit' => 'scholar', 'skin' => 'deep', 'outfit_color' => 'wine']);
        $this->actingAs($user)->get('/progression')->assertInertia(fn (Assert $page) => $page
            ->where('progression.profile.equipped.creator', null)
            ->has('progression.creator.options.face', 4)->has('progression.creator.options.hat', 6)
            ->has('progression.creator.options.body_type', 2)->has('progression.creator.options.pose', 3));
        $this->postJson('/account/customization', $look)->assertOk()->assertJsonPath('progression.profile.equipped.creator', $look['creator']);
        $this->flushSession();
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('preferredCharacter', 'custom')->where('characters.20.creator', $look['creator']));
        $code = $this->postJson('/rooms', ['name' => 'Mirror guest'])->assertCreated()->json('code');
        $state = $this->getJson('/rooms/'.$code.'/state')->assertOk()
            ->assertJsonPath('me.character', 'custom')->assertJsonPath('players.0.customization.creator', $look['creator'])->json();
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'phase_id' => $state['phase_id'], 'character' => 'archivist'])->assertOk()
            ->assertJsonMissingPath('me.customization.creator');
        $this->postJson('/rooms/'.$code.'/actions', ['type' => 'character', 'phase_id' => $state['phase_id'], 'character' => 'custom'])->assertOk()
            ->assertJsonPath('me.customization.creator', $look['creator']);
        $this->flushSession();
        $this->actingAs($user)->postJson('/rooms/join', ['code' => $code, 'name' => 'Mirror guest', 'character' => 'custom'])->assertOk();
        $this->getJson('/rooms/'.$code.'/state')->assertJsonCount(1, 'players')->assertJsonPath('me.customization.creator', $look['creator']);
    }

    public function test_invalid_recipes_and_locked_parts_are_rejected_atomically(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([['version' => 2], ['face' => 'unknown'], ['hat' => 'antlers'], ['hat' => 'tricorn'], ['outfit' => 'ritual'], ['detail' => 'brooch'],
            ['hair_color' => '#ffffff'], ['skin' => ['warm']], ['body_type' => 'unknown'], ['body_type' => null],
            ['pose' => 'sideways'], ['pose' => ['front']], ['pose' => null], ['url' => 'https://example.test/image.png']] as $invalid) {
            $this->postJson('/account/customization', $this->look($invalid))->assertUnprocessable();
            $this->assertDatabaseCount('player_profiles', 0);
        }
        $incomplete = $this->look();
        unset($incomplete['creator']['face']);
        $this->postJson('/account/customization', $incomplete)->assertUnprocessable()->assertJsonValidationErrors('creator.face');
        $this->postJson('/account/customization', [...$this->look(), 'creator' => null])->assertUnprocessable()->assertJsonValidationErrors('creator');
        $this->postJson('/account/customization', $this->look())->assertOk();
        $before = PlayerProfile::findOrFail($user->id)->customization;
        $this->postJson('/account/customization', [...$this->look(['hat' => 'antlers']), 'accent' => 'clay'])->assertUnprocessable();
        $this->assertSame($before, PlayerProfile::findOrFail($user->id)->customization);
    }

    public function test_custom_requires_a_verified_account_with_a_saved_design(): void
    {
        $engine = app(MatchEngine::class);
        foreach ([null, User::factory()->create()->id, User::factory()->unverified()->create()->id] as $account) {
            try {
                $engine->create('test', 'A villager', 'custom', accountId: $account);
                $this->fail('Custom character without a saved verified design was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('character', $exception->errors());
            }
        }
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->postJson('/account/customization', $this->look())->assertForbidden();
        $this->assertDatabaseCount('player_profiles', 0);
    }

    public function test_lifetime_unlock_boundaries_apply_to_every_creation_path(): void
    {
        $user = User::factory()->create();
        $progression = app(AccountProgression::class);
        $progression->rememberCharacter($user->id, 'mariner');
        $profile = PlayerProfile::findOrFail($user->id);
        foreach ([249 => false, 250 => true] as $xp => $unlocked) {
            $profile->update(['xp' => $xp]);
            $option = collect($progression->view($user->id)['creator']['options']['hat'])->firstWhere('id', 'tricorn');
            $this->assertSame($unlocked, $option['unlocked']);
        }
        $progression->customize($user->id, $this->look(['hat' => 'tricorn', 'detail' => 'brooch']));
        $profile->update(['xp' => 749]);
        $this->actingAs($user)->postJson('/account/customization', $this->look(['hat' => 'antlers']))->assertUnprocessable();
        $profile->update(['xp' => 750]);
        $look = $this->look(['hat' => 'antlers', 'outfit' => 'ritual']);
        $this->postJson('/account/customization', $look)->assertOk();
        $this->travelTo(now()->addMonths(4));
        $this->assertSame($look['creator'], $progression->view($user->id)['profile']['equipped']['creator']);
        $this->assertTrue($progression->canUseCharacter($user->id, 'custom'));
    }

    public function test_old_clients_and_cast_selection_preserve_the_saved_recipe_without_exposing_it(): void
    {
        $user = User::factory()->create();
        $progression = app(AccountProgression::class);
        $look = $this->look();
        $progression->customize($user->id, $look);
        $legacy = ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'clay', 'character' => 'archivist'];
        $this->actingAs($user)->postJson('/account/customization', $legacy)->assertOk()
            ->assertJsonPath('progression.profile.equipped.creator', $look['creator']);
        $room = app(MatchEngine::class)->create('cast', 'Cast player', 'archivist', accountId: $user->id);
        $this->assertArrayNotHasKey('creator', $room->state['players'][$room->state['host_id']]['customization']);
        $this->postJson('/account/customization', [...$legacy, 'creator' => null])->assertOk()
            ->assertJsonPath('progression.profile.equipped.creator', null);
        $this->assertFalse($progression->canUseCharacter($user->id, 'custom'));
    }

    public function test_match_keeps_a_recipe_snapshot_through_edits_reconnects_and_clearing(): void
    {
        $user = User::factory()->create();
        $progression = app(AccountProgression::class);
        $engine = app(MatchEngine::class);
        $look = $this->look(['body_type' => 'type2', 'pose' => 'defiant', 'hair' => 'waves', 'hat' => 'watchcap']);
        $progression->customize($user->id, $look);
        $room = $engine->create('host', 'Mirror player', 'custom', ['mode' => 'custom', 'roles' => ['townsperson' => 2, 'veilweaver' => 1]], $user->id);
        $engine->join($room->code, 'guest-1', 'Guest one');
        $engine->join($room->code, 'guest-2', 'Guest two');
        $act = fn (string $identity, ?int $account, string $type) => $engine->access($room->code, $identity,
            ['phase_id' => $room->fresh()->state['phase_id'], 'type' => $type], $account);
        $act('host', $user->id, 'ready');
        $act('guest-1', null, 'ready');
        $act('guest-2', null, 'ready');
        $act('host', $user->id, 'start');
        $progression->customize($user->id, $this->look(['body_type' => 'type1', 'pose' => 'three_quarter', 'hair' => 'braid', 'outfit_color' => 'wine']));
        $engine->join($room->code, 'new-device', 'Mirror player', accountId: $user->id);
        $this->assertSame($look['creator'], $engine->access($room->code, 'new-device', accountId: $user->id)['me']['customization']['creator']);
        $progression->customize($user->id, [...$look, 'character' => 'mariner', 'creator' => null]);
        $room->refresh()->update(['state' => [...$room->state, 'phase' => 'finished']]);
        $act('new-device', $user->id, 'rematch');
        $engine->join($room->code, 'third-device', 'Mirror player', accountId: $user->id);
        $this->assertSame($look['creator'], $engine->access($room->code, 'third-device', accountId: $user->id)['me']['customization']['creator']);
    }

    public function test_malformed_old_recipes_fall_back_without_allowing_custom_selection(): void
    {
        $user = User::factory()->create();
        $progression = app(AccountProgression::class);
        $progression->customize($user->id, $this->look());
        $profile = PlayerProfile::findOrFail($user->id);
        $profile->update(['customization' => [...$profile->customization, 'creator' => ['version' => 99, 'url' => 'bad']]]);
        $this->assertNull($progression->view($user->id)['profile']['equipped']['creator']);
        $this->assertNull($progression->preferredCharacter($user->id));
        $this->assertFalse($progression->canUseCharacter($user->id, 'custom'));
        $this->assertNull(app(CharacterCreator::class)->saved(['version' => 99], 1));
    }

    public function test_both_body_types_and_all_poses_are_available_without_unlocks_and_persist(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['type1', 'type2'] as $bodyType) {
            foreach (['front', 'three_quarter', 'defiant'] as $pose) {
                $look = $this->look(['body_type' => $bodyType, 'pose' => $pose]);
                $this->postJson('/account/customization', $look)->assertOk()
                    ->assertJsonPath('progression.profile.equipped.creator', $look['creator']);
                $this->assertSame($look['creator'], PlayerProfile::findOrFail($user->id)->customization['creator']);
            }
        }
    }

    public function test_legacy_saved_designs_and_submissions_default_to_the_original_body_and_pose(): void
    {
        $user = User::factory()->create();
        $look = $this->look(['face' => 'weathered', 'hair' => 'waves', 'skin' => 'deep']);
        $legacy = $look['creator'];
        unset($legacy['body_type'], $legacy['pose']);
        $this->actingAs($user)->postJson('/account/customization', [...$look, 'creator' => $legacy])->assertOk()
            ->assertJsonPath('progression.profile.equipped.creator', $look['creator']);

        // Exercise records that have never passed through the new save endpoint.
        $profile = PlayerProfile::findOrFail($user->id);
        $profile->update(['customization' => [...$profile->customization, 'creator' => $legacy]]);
        $this->assertSame($look['creator'], app(AccountProgression::class)->view($user->id)['profile']['equipped']['creator']);
        $room = app(MatchEngine::class)->create('legacy', 'Legacy villager', 'custom', accountId: $user->id);
        $snapshot = $room->state['players'][$room->state['host_id']]['customization']['creator'];
        $this->assertSame($look['creator'], $snapshot);
        $this->assertSame($legacy, $profile->fresh()->customization['creator'], 'Reading a legacy design must not rewrite it.');
    }
}
