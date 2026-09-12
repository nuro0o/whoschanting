<?php

namespace Tests\Feature;

use App\Events\RoomUpdated;
use App\Game\AccountProgression;
use App\Game\MatchEngine;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CharacterCreatorPausedTest extends TestCase
{
    use RefreshDatabase;

    public function test_paused_creator_is_hidden_and_rejected_while_saved_designs_and_cast_remain_available(): void
    {
        $this->withoutVite();
        Event::fake([RoomUpdated::class]);
        $this->assertFalse(config('character_creator.enabled'));
        $user = User::factory()->create();
        $progression = app(AccountProgression::class);
        $look = ['title' => 'newcomer', 'frame' => 'plain', 'accent' => 'sea', 'background' => 'plain',
            'character' => 'custom', 'creator' => config('character_creator.default')];
        config(['character_creator.enabled' => true]);
        $progression->customize($user->id, $look);
        $room = app(MatchEngine::class)->create('host', 'Existing villager', 'custom', accountId: $user->id);
        $snapshot = $room->state;
        config(['character_creator.enabled' => false]);

        $this->actingAs($user)->get('/progression')->assertInertia(fn (Assert $page) => $page
            ->missing('progression.creator')
            ->where('progression.profile.equipped.character', null)
            ->where('progression.profile.equipped.creator', null));
        $this->assertNotContains('custom', array_column($progression->characterCatalog($user->id), 'id'));
        $this->assertNull($progression->preferredCharacter($user->id));
        $this->assertFalse($progression->canUseCharacter($user->id, 'custom'));
        $this->postJson('/account/customization', $look)->assertUnprocessable()->assertJsonValidationErrors('character');
        $this->postJson('/account/customization', [...$look, 'character' => 'mariner'])->assertUnprocessable()->assertJsonValidationErrors('character');
        $this->postJson('/rooms', ['name' => 'New villager', 'character' => 'custom'])->assertUnprocessable()->assertJsonValidationErrors('character');
        $this->postJson('/rooms/'.$room->code.'/actions', ['type' => 'character', 'phase_id' => $snapshot['phase_id'], 'character' => 'custom'])
            ->assertUnprocessable()->assertJsonValidationErrors('character');

        $this->postJson('/account/customization', [...$look, 'character' => 'mariner', 'creator' => null])->assertOk()
            ->assertJsonPath('progression.profile.equipped.character', 'mariner');
        $this->assertSame($look['creator'], PlayerProfile::findOrFail($user->id)->customization['creator']);
        $this->assertSame($snapshot, $room->fresh()->state);
        $code = $this->postJson('/rooms', ['name' => 'New villager'])->assertCreated()->json('code');
        $this->getJson('/rooms/'.$code.'/state')->assertOk()->assertJsonPath('me.character', 'mariner');
        config(['character_creator.enabled' => true]);
        $this->assertTrue($progression->canUseCharacter($user->id, 'custom'));
    }
}
