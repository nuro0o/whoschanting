<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, Coins, Crown, ShoppingBag } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import CharacterPortrait from '@/components/chanting/CharacterPortrait.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { roomRequest } from '@/lib/chanting';
import {
    recordDate,
    type ProgressionData,
    type StoreItem,
} from '@/lib/progression';
import { send } from '@/routes/verification';

const props = defineProps<{ progression: ProgressionData | null }>();
const page = usePage();
const data = ref(props.progression);
watch(
    () => props.progression,
    (value) => {
        data.value = value;
    },
);
const store = computed(() => data.value?.store);
const selectedId = ref<string | null>(null);
const selected = computed(() =>
    store.value?.items.find((item) => item.id === selectedId.value),
);
const pending = ref(false);
const error = ref('');
const notice = ref('');
const purchasedItemId = ref<string | null>(null);
const labels = {
    titles: 'Resident title',
    accents: 'Accent color',
    backgrounds: 'Portrait backdrop',
};

function choose(item: StoreItem) {
    if (pending.value || item.owned || !item.affordable) return;
    error.value = '';
    notice.value = '';
    selectedId.value = item.id;
}
function close(open: boolean) {
    if (!open && !pending.value) selectedId.value = null;
}
function restoreFocus(event: Event) {
    if (!purchasedItemId.value) return;
    // A successful purchase replaces the original Buy button with Equip.
    // Leave the dialog's native restoration intact for Cancel and Escape.
    event.preventDefault();
    const itemId = purchasedItemId.value;
    purchasedItemId.value = null;
    void nextTick(() => {
        document.getElementById(`store-equip-${itemId}`)?.focus();
    });
}
async function purchase() {
    const item = selected.value;
    if (pending.value || !item || item.owned || !item.affordable) return;
    pending.value = true;
    error.value = '';
    try {
        const result = await roomRequest<{ progression: ProgressionData }>(
            '/account/store/purchase',
            { item_id: item.id },
        );
        data.value = result.progression;
        notice.value = `${item.name} is yours to keep. Equip it in your wardrobe.`;
        purchasedItemId.value = item.id;
        selectedId.value = null;
    } catch (caught) {
        error.value =
            caught instanceof Error
                ? caught.message
                : 'Your purchase could not be completed. Please try again.';
    } finally {
        pending.value = false;
    }
}
function transactionName(itemId: string | null) {
    return (
        store.value?.items.find((item) => item.id === itemId)?.name ??
        'Cosmetic purchase'
    );
}
</script>

<template>
    <section id="store" class="profile-store" aria-labelledby="store-title">
        <header class="store-masthead">
            <div class="store-intro">
                <p class="store-eyebrow">
                    <ShoppingBag :size="14" aria-hidden="true" /> The village
                    store
                </p>
                <h2 id="store-title">A little more <em>you.</em></h2>
                <p>
                    Earn your Crowns at the table. Find something to make your
                    resident record your own.
                </p>
            </div>
            <div class="store-wallet" aria-label="Your Crown balance">
                <span class="store-eyebrow">Your purse</span>
                <div>
                    <Crown
                        :size="27"
                        :stroke-width="1.3"
                        aria-hidden="true"
                    /><strong>{{
                        store ? store.balance.toLocaleString() : '—'
                    }}</strong>
                </div>
                <span>Crowns <small>· earned through play</small></span>
            </div>
        </header>

        <template v-if="store">
            <div class="store-earning">
                <Coins :size="23" :stroke-width="1.3" aria-hidden="true" />
                <div>
                    <p>
                        <strong
                            >{{ store.rewards.match }} Crowns per completed
                            match</strong
                        >
                        + {{ store.rewards.win }} more for a win.
                    </p>
                    <p>
                        Join with your verified account, submit a night action
                        and a vote. Abstention counts. Crowns arrive when the
                        match finishes; eliminated players can still earn.
                    </p>
                </div>
                <Link href="/dashboard" class="account-text-link"
                    >Find a game <ArrowRight :size="15"
                /></Link>
            </div>

            <div class="store-shelf-heading">
                <div>
                    <p class="account-kicker">
                        Small details. Lasting keepsakes.
                    </p>
                    <h3>For your collection</h3>
                </div>
                <Link href="/progression" class="account-text-link"
                    >Open wardrobe <ArrowRight :size="15"
                /></Link>
            </div>
            <p v-if="notice" class="store-notice" role="status">
                <Check :size="17" aria-hidden="true" />{{ notice }}
            </p>
            <ul
                v-if="store.items.length"
                class="store-shelf"
                aria-label="Cosmetics for Crowns"
            >
                <li
                    v-for="item in store.items"
                    :key="item.id"
                    class="store-item"
                    :class="`store-item--${item.category}`"
                >
                    <div class="store-preview">
                        <CharacterPortrait
                            :character="
                                data?.profile.equipped.character ?? 'mariner'
                            "
                            :creator="data?.profile.equipped.creator"
                            :accent="
                                item.category === 'accents'
                                    ? item.cosmetic_id
                                    : 'sea'
                            "
                            :background="
                                item.category === 'backgrounds'
                                    ? item.cosmetic_id
                                    : 'plain'
                            "
                            :frame="
                                item.category === 'accents'
                                    ? 'lantern'
                                    : undefined
                            "
                            decorative
                        />
                        <span
                            v-if="item.category === 'titles'"
                            class="store-title-preview"
                            >{{ item.name }}</span
                        >
                        <span v-else class="store-preview-label"
                            >{{ labels[item.category] }} preview</span
                        >
                        <span v-if="item.owned" class="store-owned"
                            ><Check :size="12" /> Owned</span
                        >
                    </div>
                    <div class="store-item-copy">
                        <p class="account-kicker">
                            {{ labels[item.category] }}
                        </p>
                        <h4>{{ item.name }}</h4>
                        <p class="store-description">{{ item.description }}</p>
                        <div class="store-item-footer">
                            <span class="store-price"
                                ><Crown :size="16" aria-hidden="true" />{{
                                    item.price
                                }}
                                <span>Crowns</span></span
                            >
                            <Link
                                v-if="item.owned"
                                :id="`store-equip-${item.id}`"
                                href="/progression"
                                class="store-equip"
                                >Equip <ArrowRight :size="14"
                            /></Link>
                            <Button
                                v-else
                                type="button"
                                :disabled="pending || !item.affordable"
                                :aria-label="
                                    item.affordable
                                        ? `Buy ${item.name} for ${item.price} Crowns`
                                        : `${item.name}: ${Math.max(0, item.price - store.balance)} more Crowns needed`
                                "
                                @click="choose(item)"
                                >{{
                                    item.affordable ? 'Buy' : 'Keep earning'
                                }}</Button
                            >
                        </div>
                    </div>
                </li>
            </ul>
            <p v-else class="store-empty">
                The merchant is preparing the next collection. Your Crowns are
                safely in your purse.
            </p>

            <details
                v-if="store.recent_transactions.length"
                class="store-ledger"
            >
                <summary>
                    Recent Crown activity
                    <span
                        >{{ store.lifetime_earned.toLocaleString() }} earned in
                        total</span
                    >
                </summary>
                <ul>
                    <li
                        v-for="entry in store.recent_transactions"
                        :key="entry.id"
                    >
                        <div>
                            <strong>{{
                                entry.kind === 'match_reward'
                                    ? 'Match reward'
                                    : transactionName(entry.item_id)
                            }}</strong
                            ><span>{{ recordDate(entry.created_at) }}</span>
                        </div>
                        <span
                            >{{ entry.amount > 0 ? '+' : ''
                            }}{{ entry.amount }} Crowns</span
                        >
                    </li>
                </ul>
            </details>
        </template>
        <div v-else class="store-verification">
            <h3>Your first Crown starts with a verified account.</h3>
            <p>
                Verify your email, then play completed games to earn Crowns and
                collect permanent cosmetics.
            </p>
            <Link :href="send()" as="button" class="account-text-link"
                >Send verification email <ArrowRight :size="15"
            /></Link>
            <p
                v-if="page.props.status === 'verification-link-sent'"
                role="status"
            >
                A verification link has been sent to your email address.
            </p>
        </div>

        <aside class="store-future" aria-labelledby="store-future-title">
            <span class="store-later">Coming later</span>
            <div>
                <h3 id="store-future-title">More ways to make it yours.</h3>
                <p>
                    Optional cosmetic purchases, season passes, and a supporter
                    subscription are planned. Every gameplay feature stays free.
                    Cosmetics never change your odds.
                </p>
            </div>
        </aside>
        <p class="store-footnote">
            Crowns are in-game currency with no cash value or withdrawals. Store
            cosmetics are yours to keep. Progression rewards remain earned
            through play.
        </p>

        <Dialog :open="!!selected" @update:open="close">
            <DialogContent
                class="account-theme store-confirmation"
                :show-close-button="!pending"
                @close-auto-focus="restoreFocus"
                @escape-key-down="pending && $event.preventDefault()"
                @pointer-down-outside="pending && $event.preventDefault()"
            >
                <template v-if="selected && store">
                    <DialogHeader>
                        <DialogTitle
                            >Make {{ selected.name }} yours?</DialogTitle
                        >
                        <DialogDescription
                            >Spend {{ selected.price }} Crowns to permanently
                            unlock this
                            {{ labels[selected.category].toLowerCase() }}. You
                            can equip it in your wardrobe after
                            purchase.</DialogDescription
                        >
                    </DialogHeader>
                    <dl class="store-receipt">
                        <div>
                            <dt>Your balance</dt>
                            <dd>{{ store.balance }} Crowns</dd>
                        </div>
                        <div>
                            <dt>After purchase</dt>
                            <dd>
                                {{
                                    Math.max(0, store.balance - selected.price)
                                }}
                                Crowns
                            </dd>
                        </div>
                    </dl>
                    <p v-if="error" class="store-error" role="alert">
                        {{ error }}
                    </p>
                    <DialogFooter>
                        <Button
                            variant="secondary"
                            :disabled="pending"
                            @click="close(false)"
                            >Cancel</Button
                        >
                        <Button
                            :disabled="
                                pending ||
                                selected.owned ||
                                !selected.affordable
                            "
                            @click="purchase"
                            >{{
                                pending
                                    ? 'Purchasing…'
                                    : `Spend ${selected.price} Crowns`
                            }}</Button
                        >
                    </DialogFooter>
                </template>
            </DialogContent>
        </Dialog>
    </section>
</template>

<style scoped>
.profile-store {
    margin: 8px 0 42px;
    scroll-margin-top: 24px;
}
.store-masthead {
    display: grid;
    grid-template-columns: 1fr 220px;
    background: #203a33;
    color: #f5efd9;
    border: 1px solid #52664e;
    padding: 29px 32px;
    gap: 30px;
}
.store-eyebrow {
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 1.7px;
    font-size: 10px;
    color: #d0c297;
}
.store-intro h2 {
    font-size: clamp(30px, 3vw, 43px);
    letter-spacing: -1.1px;
    line-height: 1.15;
    margin: 13px 0 12px;
}
.store-intro h2 em {
    color: #d6dfb8;
}
.store-intro > p:last-child {
    color: #d5dfd2;
    font-size: 12px;
    line-height: 1.8;
    max-width: 400px;
}
.store-wallet {
    padding-left: 28px;
    border-left: 1px solid #b4c4a63b;
    align-self: center;
}
.store-wallet > div {
    display: flex;
    align-items: center;
    gap: 14px;
    color: #e0c784;
    margin: 1px 0;
}
.store-wallet strong {
    font:
        400 clamp(42px, 5vw, 58px) 'Fraunces',
        Georgia,
        serif;
    letter-spacing: -2px;
}
.store-wallet > span:last-child {
    font-size: 13px;
}
.store-wallet small {
    display: block;
    font-size: 10px;
    color: #cad8c6;
    margin-top: 4px;
}
.store-earning {
    display: flex;
    align-items: center;
    gap: 16px;
    border: 1px solid var(--account-line);
    border-top: 0;
    background: var(--account-deep);
    padding: 18px 23px;
}
.store-earning > svg {
    color: var(--account-brass);
}
.store-earning > div {
    flex: 1;
}
.store-earning p {
    font-size: 12px;
    line-height: 1.7;
}
.store-earning p + p {
    color: var(--account-muted);
    font-size: 11px;
    margin-top: 4px;
}
.store-earning .account-text-link {
    white-space: nowrap;
}
.store-shelf-heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 16px;
    margin: 28px 0 20px;
}
.store-shelf-heading h3 {
    font-size: 25px;
    margin-top: 4px;
}
.store-shelf {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    list-style: none;
    padding: 0;
    gap: 22px 20px;
}
.store-item {
    min-width: 0;
    border-bottom: 1px solid var(--account-line);
    display: flex;
    flex-direction: column;
}
.store-preview {
    position: relative;
    min-height: 145px;
    background: #263d38;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 13px;
    padding: 22px 14px 17px;
    color: #eee8d2;
    border: 1px solid #54624d;
}
.store-item--accents .store-preview {
    background: #263237;
}
.store-item--backgrounds .store-preview {
    background: #2e3830;
}
.store-preview .character-portrait {
    display: block;
    width: 57px;
    height: 67px;
    border: 2px solid var(--portrait-accent);
    border-radius: 32px 32px 4px 4px;
    background-repeat: no-repeat;
}
.store-item--backgrounds .character-portrait {
    width: 83px;
    height: 83px;
    border-radius: 3px;
}
.store-title-preview {
    font:
        400 15px 'Fraunces',
        Georgia,
        serif;
    text-align: center;
}
.store-preview-label {
    font-size: 9px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: #c7d3c3;
}
.store-owned {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 3px 7px;
    background: #dce4ca;
    color: #263a2b;
    font-size: 9px;
}
.store-item-copy {
    padding: 16px 0 17px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.store-item-copy .account-kicker {
    font-size: 9px;
    letter-spacing: 1.3px;
}
.store-item h4 {
    font:
        400 19px 'Fraunces',
        Georgia,
        serif;
    line-height: 1.3;
    margin: 6px 0 8px;
}
.store-description {
    color: var(--account-muted);
    font-size: 11px;
    line-height: 1.75;
    flex: 1;
    margin-bottom: 17px;
}
.store-item-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.store-price {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    font-size: 12px;
    font-weight: 600;
}
.store-price > span {
    font-size: 9px;
    font-weight: 400;
}
.store-price svg {
    color: var(--account-brass);
}
.store-item-footer button {
    font-size: 10px;
    padding-inline: 11px;
}
.store-item-footer button:disabled {
    opacity: 1;
    background: var(--account-deep);
    color: var(--account-muted);
}
.store-equip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.store-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--account-green);
    border-left: 3px solid var(--account-green);
    padding: 12px 15px;
    background: var(--account-deep);
    margin-bottom: 20px;
    font-size: 12px;
}
.store-ledger {
    margin-top: 22px;
    font-size: 11px;
}
.store-ledger summary {
    cursor: pointer;
    padding: 8px 0;
}
.store-ledger summary > span {
    margin-left: 12px;
    color: var(--account-muted);
}
.store-ledger ul {
    list-style: none;
    padding: 0;
    margin-top: 8px;
}
.store-ledger li {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 10px 0;
    border-top: 1px solid var(--account-line);
}
.store-ledger li div > span {
    display: block;
    color: var(--account-muted);
    font-size: 10px;
    margin-top: 3px;
}
.store-ledger li > span {
    white-space: nowrap;
}
.store-future {
    display: flex;
    align-items: start;
    gap: 20px;
    border: 1px solid var(--account-line);
    padding: 21px 23px;
    margin-top: 26px;
}
.store-later {
    flex-shrink: 0;
    border: 1px solid var(--account-line);
    color: var(--account-brass);
    font-size: 9px;
    letter-spacing: 1px;
    text-transform: uppercase;
    padding: 5px 8px;
    margin-top: 3px;
}
.store-future h3 {
    font-size: 20px;
}
.store-future p {
    font-size: 11px;
    line-height: 1.8;
    color: var(--account-muted);
    margin-top: 6px;
}
.store-footnote {
    font-size: 10px;
    line-height: 1.8;
    color: var(--account-muted);
    margin-top: 12px;
}
.store-verification,
.store-empty {
    padding: 26px 23px;
    border: 1px solid var(--account-line);
    background: var(--account-panel);
}
.store-verification h3 {
    font-size: 23px;
}
.store-verification p {
    color: var(--account-muted);
    font-size: 12px;
    line-height: 1.8;
    margin: 10px 0 16px;
}
.store-receipt {
    display: grid;
    gap: 8px;
    font-size: 13px;
    padding: 16px 0;
    border-block: 1px solid var(--account-line);
}
.store-receipt > div {
    display: flex;
    justify-content: space-between;
    gap: 16px;
}
.store-receipt dd {
    font-weight: 600;
}
.store-error {
    color: var(--destructive);
    font-size: 13px;
}
@media (max-width: 1050px) {
    .store-shelf {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 600px) {
    .store-masthead {
        grid-template-columns: 1fr;
        padding: 24px;
        gap: 21px;
    }
    .store-wallet {
        padding: 17px 0 0;
        border-left: 0;
        border-top: 1px solid #b4c4a63b;
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
    }
    .store-wallet > div {
        grid-column: 2;
        grid-row: 1 / 3;
    }
    .store-wallet small {
        display: inline;
        margin-left: 4px;
    }
    .store-earning {
        flex-wrap: wrap;
        padding: 17px;
        gap: 12px;
    }
    .store-earning > div {
        flex-basis: calc(100% - 40px);
    }
    .store-earning .account-text-link {
        margin-left: 35px;
    }
    .store-shelf {
        gap: 22px 15px;
    }
    .store-shelf-heading {
        align-items: start;
        flex-direction: column;
        gap: 8px;
    }
    .store-item-footer {
        flex-wrap: wrap;
    }
    .store-item-footer button {
        width: 100%;
    }
    .store-future {
        flex-direction: column;
        gap: 12px;
        padding: 20px;
    }
    .store-title-preview {
        font-size: 14px;
        min-height: 2.6em;
        line-height: 1.3;
        display: flex;
        align-items: center;
    }
    .store-item h4 {
        font-size: 18px;
    }
}
@media (max-width: 370px) {
    .store-shelf {
        grid-template-columns: 1fr;
    }
}
</style>
