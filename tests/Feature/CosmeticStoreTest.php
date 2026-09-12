<?php

namespace Tests\Feature;

use App\Game\AccountProgression;
use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CosmeticStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function funded(int $balance = 250): User
    {
        $user = User::factory()->create();
        app(AccountProgression::class)->rememberCharacter($user->id, 'mariner');
        PlayerProfile::whereKey($user->id)->update(['coins' => $balance, 'coins_earned' => $balance]);

        return $user;
    }

    public function test_profile_exposes_private_store_without_creating_a_wallet_on_read(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')->where('progression.store.balance', 0)
            ->where('progression.store.currency', 'Crowns')->has('progression.store.items', 6)
            ->where('progression.store.rewards', ['per_player' => 1, 'small_game_max_players' => 6, 'small_game_win' => 5, 'large_game_win' => 10]));
        $this->assertDatabaseCount('player_profiles', 0);
        $this->assertDatabaseCount('coin_transactions', 0);

        $this->actingAs(User::factory()->unverified()->create())->get('/settings/profile')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('progression', null));
    }

    public function test_purchase_requires_a_verified_account_and_valid_product(): void
    {
        $this->postJson('/account/store/purchase', ['item_id' => 'title-night-market'])->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())
            ->postJson('/account/store/purchase', ['item_id' => 'title-night-market'])->assertForbidden();
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([[], ['item_id' => []], ['item_id' => 'unknown'], ['item_id' => 'subscription'], ['item_id' => 'season-pass']] as $input) {
            $this->postJson('/account/store/purchase', $input)->assertUnprocessable()->assertJsonValidationErrors('item_id');
        }
        $this->assertDatabaseCount('store_purchases', 0);
        $this->assertDatabaseCount('coin_transactions', 0);
        $this->assertDatabaseCount('player_profiles', 0);
    }

    public function test_purchase_uses_server_price_and_account_and_retry_does_not_charge_again(): void
    {
        $user = $this->funded();
        $other = $this->funded();
        $input = ['item_id' => 'title-night-market', 'price' => 0, 'coins' => 99999, 'user_id' => $other->id];
        $first = $this->actingAs($user)->postJson('/account/store/purchase', $input)->assertOk()
            ->assertJsonPath('progression.store.balance', 150)->assertJsonPath('progression.store.lifetime_earned', 250)
            ->assertJsonPath('progression.store.items.0.owned', true)
            ->assertJsonPath('progression.profile.equipped.title', 'newcomer');
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $this->postJson('/account/store/purchase', $input)->assertOk()->assertJsonPath('progression.store.balance', 150);
        $this->assertSame(250, PlayerProfile::findOrFail($other->id)->coins);
        $this->assertDatabaseHas('store_purchases', ['user_id' => $user->id, 'item_id' => 'title-night-market', 'price' => 100]);
        $this->assertDatabaseHas('coin_transactions', ['user_id' => $user->id, 'kind' => 'purchase', 'amount' => -100, 'balance_after' => 150]);
        $this->assertDatabaseCount('store_purchases', 1);
        $this->assertDatabaseCount('coin_transactions', 1);
    }

    public function test_insufficient_balance_and_stale_second_purchase_cannot_overspend(): void
    {
        $user = $this->funded(100);
        $this->actingAs($user)->postJson('/account/store/purchase', ['item_id' => 'accent-amethyst'])
            ->assertUnprocessable()->assertJsonValidationErrors('item_id');
        $this->assertDatabaseCount('store_purchases', 0);
        $this->assertDatabaseCount('coin_transactions', 0);
        $this->assertSame(100, PlayerProfile::findOrFail($user->id)->coins);

        $this->postJson('/account/store/purchase', ['item_id' => 'title-night-market'])->assertOk()
            ->assertJsonPath('progression.store.balance', 0);
        $this->postJson('/account/store/purchase', ['item_id' => 'title-velvet-voice', 'balance' => 1000])
            ->assertUnprocessable();
        $this->postJson('/account/store/purchase', ['item_id' => 'title-night-market'])->assertOk()
            ->assertJsonPath('progression.store.balance', 0);
        $this->assertDatabaseCount('store_purchases', 1);
        $this->assertDatabaseCount('coin_transactions', 1);
    }

    public function test_failed_purchase_transaction_rolls_back_balance_entitlement_and_receipt(): void
    {
        $user = $this->funded();
        DB::beginTransaction();
        app(AccountProgression::class)->purchase($user->id, 'title-night-market');
        DB::rollBack();
        $this->assertSame(250, PlayerProfile::findOrFail($user->id)->coins);
        $this->assertDatabaseCount('store_purchases', 0);
        $this->assertDatabaseCount('coin_transactions', 0);
    }

    public function test_store_cosmetics_require_ownership_and_can_be_equipped_permanently(): void
    {
        $user = $this->funded(1000);
        $look = ['title' => 'night_market', 'frame' => 'plain', 'accent' => 'amethyst', 'background' => 'moonlit', 'character' => 'mariner'];
        $this->actingAs($user)->postJson('/account/customization', $look)->assertUnprocessable();
        foreach (['title-night-market', 'accent-amethyst', 'background-moonlit'] as $id) {
            $this->postJson('/account/store/purchase', ['item_id' => $id])->assertOk();
        }
        $this->postJson('/account/customization', $look)->assertOk()
            ->assertJsonPath('progression.profile.equipped.title', 'night_market')
            ->assertJsonPath('progression.profile.equipped.accent', 'amethyst')
            ->assertJsonPath('progression.profile.equipped.background', 'moonlit');
        $this->travel(4)->months();
        $progression = app(AccountProgression::class);
        $view = $progression->view($user->id);
        $this->assertSame(500, $view['store']['balance']);
        $this->assertTrue(collect($view['cosmetics']['titles'])->firstWhere('id', 'night_market')['unlocked']);
        $this->assertFalse(collect($view['cosmetics']['titles'])->firstWhere('id', 'watchful')['unlocked']);
        $this->assertFalse(collect($view['cosmetics']['accents'])->firstWhere('id', 'patina')['unlocked']);
        $appearance = $progression->appearance($user->id);
        $this->assertSame('Night Market Regular', $appearance['title_name']);
        $this->assertArrayNotHasKey('coins', $appearance);
        $this->assertArrayNotHasKey('store', $appearance);
        $this->assertSame(0, $view['profile']['xp']);
        $this->assertSame(0, $view['season']['xp']);
    }

    public function test_other_accounts_cannot_equip_or_see_purchases_and_deletion_removes_store_records(): void
    {
        $owner = $this->funded();
        app(AccountProgression::class)->purchase($owner->id, 'title-night-market');
        $other = User::factory()->create();
        $this->actingAs($other)->get('/settings/profile')->assertInertia(fn (Assert $page) => $page
            ->where('progression.store.items.0.owned', false)->where('progression.store.balance', 0)
            ->has('progression.store.recent_transactions', 0));
        $this->postJson('/account/customization', ['title' => 'night_market', 'frame' => 'plain', 'accent' => 'sea', 'character' => 'mariner'])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
        $owner->delete();
        $this->assertDatabaseCount('store_purchases', 0);
        $this->assertDatabaseCount('coin_transactions', 0);
    }
}
