<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Coins,
    Crown,
    ShoppingBag,
    Sparkles,
    Play,
    ExternalLink,
} from '@lucide/vue';
import { useNow } from '@vueuse/core';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import CharacterPortrait from '@/components/chanting/CharacterPortrait.vue';
import CosmeticScenePreview from '@/components/chanting/CosmeticScenePreview.vue';
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
    type StoreBundle,
    type CheckoutStatus,
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
const bundles = computed(() => store.value?.bundles ?? []);
const checkoutBundleId = ref<string | null>(null);
const checkoutError = ref('');
const checkoutMessage = ref('');
const checkoutStatusElement = ref<HTMLElement>();
const checkoutStatus = ref<CheckoutStatus | null>(null);
const checkingPayment = ref(false);
const previewBundle = ref<StoreBundle | null>(null);
const consentBundleId = ref<string | null>(null);
const consentBundle = computed(() =>
    bundles.value.find((bundle) => bundle.id === consentBundleId.value),
);
const termsAccepted = ref(false);
const consentError = ref('');
const consentErrorElement = ref<HTMLElement>();
let consentVersion = '';
function chooseBundle(bundle: StoreBundle) {
    if (
        checkoutBundleId.value ||
        checkingPayment.value ||
        checkoutStatus.value === 'pending' ||
        bundle.owned ||
        !bundle.available
    )
        return;
    termsAccepted.value = false;
    consentError.value = '';
    consentVersion = page.props.legal?.version ?? '';
    consentBundleId.value = bundle.id;
}
function closeConsent(open: boolean) {
    if (open || checkoutBundleId.value) return;
    consentBundleId.value = null;
    termsAccepted.value = false;
    consentError.value = '';
    consentVersion = '';
}
let sessionId = '';
let paymentTimer: ReturnType<typeof setTimeout> | undefined;
let mounted = false;
let paymentChecks = 0;
const categoryNames: Record<string, string> = {
    titles: 'Title',
    frames: 'Portrait frame',
    accents: 'Accent',
    backgrounds: 'Backdrop',
    tables: 'Table',
    banishments: 'Banishment',
    celebrations: 'Victory celebration',
};
function bundleCosmetic(bundle: StoreBundle, category: string) {
    return bundle.cosmetics.find((item) => item.category === category)?.id;
}
function money(bundle: StoreBundle) {
    return new Intl.NumberFormat('en-IE', {
        style: 'currency',
        currency: bundle.currency,
    }).format(bundle.amount / 100);
}
async function checkout(bundle: StoreBundle) {
    if (
        checkoutBundleId.value ||
        checkingPayment.value ||
        checkoutStatus.value === 'pending' ||
        !bundle.available ||
        bundle.owned ||
        !termsAccepted.value ||
        consentBundleId.value !== bundle.id
    )
        return;
    checkoutBundleId.value = bundle.id;
    checkoutError.value = '';
    consentError.value = '';
    try {
        const result = await roomRequest<{ url: string }>(
            '/account/store/checkout',
            {
                bundle_id: bundle.id,
                terms: true,
                terms_version: consentVersion,
            },
        );
        const destination = new URL(result.url);
        if (
            destination.protocol !== 'https:' ||
            destination.hostname !== 'checkout.stripe.com'
        ) {
            throw new Error('Checkout could not be opened. Please try again.');
        }
        window.location.assign(destination.href);
    } catch (caught) {
        consentError.value =
            caught instanceof Error
                ? caught.message
                : 'Checkout could not be opened. Please try again.';
        checkoutBundleId.value = null;
        await nextTick();
        consentErrorElement.value?.focus();
    }
}
async function checkPayment(manual = false) {
    if (!sessionId || checkingPayment.value) return;
    clearTimeout(paymentTimer);
    if (manual) paymentChecks = 0;
    checkingPayment.value = true;
    checkoutError.value = '';
    try {
        const result = await roomRequest<{
            status: CheckoutStatus;
            progression: ProgressionData;
        }>(`/account/store/status?session_id=${encodeURIComponent(sessionId)}`);
        if (!mounted) return;
        data.value = result.progression;
        checkoutStatus.value = result.status;
        const messages: Record<CheckoutStatus, string> = {
            pending:
                'Your payment is being confirmed. Your collection will update here as soon as it is ready.',
            paid: 'Payment confirmed. Your bundle is yours to keep — open your wardrobe to equip it.',
            refunded:
                'This payment has been refunded. Your collection now shows your current unlocks.',
            disputed:
                'This payment is under review. Your collection now shows your current unlocks.',
            expired:
                'This checkout expired. You can start a new checkout below.',
            failed: 'This payment was not completed. You can try checkout again below.',
        };
        checkoutMessage.value = messages[result.status];
        if (result.status === 'pending' && ++paymentChecks < 8) {
            paymentTimer = setTimeout(() => void checkPayment(), 3000);
        }
    } catch (caught) {
        if (!mounted) return;
        checkoutError.value =
            caught instanceof Error
                ? caught.message
                : 'We could not check your payment. Please refresh its status.';
        checkoutMessage.value =
            'Your payment has not been confirmed here yet. Refresh its status before starting another checkout.';
    } finally {
        checkingPayment.value = false;
    }
}
onMounted(() => {
    mounted = true;
    const query = new URL(window.location.href).searchParams;
    if (query.get('checkout') === 'cancelled')
        checkoutMessage.value =
            'Checkout closed. Your collection is unchanged. You can return whenever you are ready.';
    if (query.get('checkout') === 'success' && query.get('session_id')) {
        sessionId = query.get('session_id')!;
        checkoutStatus.value = 'pending';
        checkoutMessage.value = 'Checking your payment…';
        void checkPayment();
    }
});
onBeforeUnmount(() => {
    mounted = false;
    clearTimeout(paymentTimer);
});
const now = useNow({ interval: 1000 });
const cooldownMinutes = computed(() =>
    store.value?.crown_cooldown_until
        ? Math.max(
              0,
              Math.ceil(
                  (Date.parse(store.value.crown_cooldown_until) -
                      now.value.getTime()) /
                      60000,
              ),
          )
        : 0,
);
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
                    Earn your Crowns at the table. Collect keepsakes, or support
                    the village with a look your whole gathering can enjoy.
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
            <section
                v-if="bundles.length"
                class="premium-collection"
                aria-labelledby="premium-title"
            >
                <header class="premium-heading">
                    <div>
                        <p class="account-kicker">
                            Keep the village lanterns lit
                        </p>
                        <h3 id="premium-title">
                            Something worth gathering around.
                        </h3>
                    </div>
                    <p>
                        One payment. Yours to keep.<br />Every gameplay feature
                        stays free.
                    </p>
                </header>
                <div
                    v-if="checkoutMessage || checkoutError"
                    ref="checkoutStatusElement"
                    tabindex="-1"
                    class="premium-status"
                    :class="{ 'has-error': checkoutError }"
                >
                    <p v-if="checkoutMessage" role="status">
                        {{ checkoutMessage }}
                    </p>
                    <p v-if="checkoutError" class="store-error" role="alert">
                        {{ checkoutError }}
                    </p>
                    <Button
                        v-if="
                            sessionId &&
                            (checkoutStatus === 'pending' || checkoutError)
                        "
                        variant="secondary"
                        :disabled="checkingPayment"
                        @click="checkPayment(true)"
                        >{{
                            checkingPayment
                                ? 'Checking payment…'
                                : 'Refresh payment status'
                        }}</Button
                    >
                    <Link
                        v-if="checkoutStatus === 'paid'"
                        href="/progression"
                        class="account-text-link"
                        >Open wardrobe <ArrowRight :size="14"
                    /></Link>
                </div>
                <div class="premium-bundles">
                    <article
                        v-for="bundle in bundles"
                        :key="bundle.id"
                        class="premium-bundle"
                        :class="[
                            `premium-bundle--${bundle.id}`,
                            { 'is-owned': bundle.owned },
                        ]"
                    >
                        <div class="premium-art">
                            <span class="premium-edition">{{
                                bundle.id === 'founders-pack'
                                    ? 'The founding collection'
                                    : 'A gathering in a different light'
                            }}</span>
                            <div class="premium-portrait-stage">
                                <span
                                    class="premium-orbit"
                                    aria-hidden="true"
                                ></span
                                ><CharacterPortrait
                                    :character="
                                        data?.profile.equipped.character ??
                                        'mariner'
                                    "
                                    :creator="data?.profile.equipped.creator"
                                    :frame="
                                        bundleCosmetic(bundle, 'frames') ??
                                        'plain'
                                    "
                                    :accent="bundleCosmetic(bundle, 'accents')"
                                    :background="
                                        bundleCosmetic(bundle, 'backgrounds')
                                    "
                                    decorative
                                />
                            </div>
                            <span class="premium-art-caption"
                                ><Sparkles :size="13" aria-hidden="true" /> A
                                signature for your table</span
                            >
                        </div>
                        <div class="premium-copy">
                            <p class="account-kicker">
                                Permanent cosmetic bundle
                                <span v-if="bundle.owned"> · Owned</span>
                            </p>
                            <h4>{{ bundle.name }}</h4>
                            <p class="premium-description">
                                {{ bundle.description }}
                            </p>
                            <ul
                                class="premium-contents"
                                :aria-label="`${bundle.name} contents`"
                            >
                                <li
                                    v-for="item in bundle.cosmetics"
                                    :key="`${item.category}-${item.id}`"
                                >
                                    <Check :size="12" aria-hidden="true" /><span
                                        >{{ item.name
                                        }}<small>{{
                                            categoryNames[item.category] ??
                                            item.category
                                        }}</small></span
                                    >
                                </li>
                            </ul>
                            <button
                                type="button"
                                class="premium-preview-button"
                                @click="previewBundle = bundle"
                            >
                                <Play :size="13" aria-hidden="true" /> Preview
                                the collection
                            </button>
                            <div class="premium-purchase">
                                <div>
                                    <strong>{{ money(bundle) }}</strong
                                    ><small>One-time purchase</small>
                                </div>
                                <Link
                                    v-if="bundle.owned"
                                    href="/progression"
                                    class="store-equip"
                                    >Equip <ArrowRight :size="14" /></Link
                                ><Button
                                    v-else
                                    :disabled="
                                        !!checkoutBundleId ||
                                        checkingPayment ||
                                        checkoutStatus === 'pending' ||
                                        !bundle.available
                                    "
                                    @click="chooseBundle(bundle)"
                                    >{{
                                        checkoutBundleId === bundle.id
                                            ? 'Opening checkout…'
                                            : bundle.available
                                              ? 'Get the bundle'
                                              : 'Coming soon'
                                    }}<ExternalLink
                                        v-if="
                                            bundle.available &&
                                            !checkoutBundleId
                                        "
                                        :size="12"
                                        aria-hidden="true"
                                /></Button>
                            </div>
                        </div>
                    </article>
                </div>
                <p class="premium-note">
                    Secure checkout with Stripe. Table looks are shared when you
                    host. Your banishment plays when you are voted out; one
                    winner’s equipped celebration is chosen at random for
                    everyone to enjoy.
                </p>
            </section>
            <div class="store-earning">
                <Coins :size="23" :stroke-width="1.3" aria-hidden="true" />
                <div>
                    <p>
                        <strong
                            >{{ store.rewards.per_player }} Crown per starting
                            player in a completed match</strong
                        >
                        + {{ store.rewards.small_game_win }} for a win with 1–{{
                            store.rewards.small_game_max_players
                        }}
                        players, or + {{ store.rewards.large_game_win }} with
                        {{ store.rewards.small_game_max_players + 1 }}+ players.
                    </p>
                    <p>
                        Join with your verified account, submit a night action
                        and a vote. Abstention counts. Crowns arrive when the
                        match finishes; eliminated players can still earn.
                    </p>
                    <p v-if="cooldownMinutes" role="status">
                        Crown earnings resume in {{ cooldownMinutes }}
                        {{ cooldownMinutes === 1 ? 'minute' : 'minutes' }} after
                        several very short matches. Keep playing for XP and
                        achievements; your existing Crowns are still available
                        to spend.
                    </p>
                    <p v-else>
                        Repeated very short matches may briefly pause Crown
                        earnings. You can always keep playing for XP and
                        achievements.
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

        <p class="store-footnote">
            Crowns are in-game currency with no cash value or withdrawals. Store
            cosmetics are yours to keep. Progression rewards remain earned
            through play.
        </p>

        <Dialog :open="!!consentBundle" @update:open="closeConsent">
            <DialogContent
                class="account-theme store-consent-dialog"
                :show-close-button="!checkoutBundleId"
                @escape-key-down="checkoutBundleId && $event.preventDefault()"
                @pointer-down-outside="
                    checkoutBundleId && $event.preventDefault()
                "
            >
                <template v-if="consentBundle">
                    <DialogHeader
                        ><DialogTitle
                            >Make {{ consentBundle.name }} yours.</DialogTitle
                        ><DialogDescription
                            >Review your purchase before continuing to secure
                            checkout with Stripe.</DialogDescription
                        ></DialogHeader
                    >
                    <form @submit.prevent="checkout(consentBundle)">
                        <dl class="store-receipt">
                            <div>
                                <dt>{{ consentBundle.name }}</dt>
                                <dd>{{ money(consentBundle) }}</dd>
                            </div>
                            <div>
                                <dt>Payment</dt>
                                <dd>One time</dd>
                            </div>
                        </dl>
                        <p class="store-consent-copy">
                            Your digital cosmetics unlock when payment is
                            confirmed. Your statutory withdrawal and refund
                            rights remain. Read our
                            <a href="/refunds" target="_blank" rel="noopener"
                                >Refunds policy<span class="sr-only">
                                    (opens in a new tab)</span
                                ></a
                            >.
                        </p>
                        <label class="store-consent-label" for="store-terms"
                            ><input
                                id="store-terms"
                                v-model="termsAccepted"
                                type="checkbox"
                                required
                                :disabled="!!checkoutBundleId"
                            /><span
                                >I agree to the
                                <a href="/terms" target="_blank" rel="noopener"
                                    >Terms of Service<span class="sr-only">
                                        (opens in a new tab)</span
                                    ></a
                                >
                                for this purchase.</span
                            ></label
                        >
                        <p class="store-consent-copy">
                            Our
                            <a href="/privacy" target="_blank" rel="noopener"
                                >Privacy Notice<span class="sr-only">
                                    (opens in a new tab)</span
                                ></a
                            >
                            explains how we use your information.
                        </p>
                        <p
                            v-if="consentError"
                            ref="consentErrorElement"
                            class="store-error"
                            role="alert"
                            tabindex="-1"
                        >
                            {{ consentError }}
                            <a href="/settings/profile#store"
                                >Refresh the store</a
                            >
                            to load the current terms and try again.
                        </p>
                        <DialogFooter
                            ><Button
                                type="button"
                                variant="secondary"
                                :disabled="!!checkoutBundleId"
                                @click="closeConsent(false)"
                                >Cancel</Button
                            ><Button
                                type="submit"
                                :disabled="
                                    !!checkoutBundleId ||
                                    !termsAccepted ||
                                    !consentBundle.available ||
                                    consentBundle.owned
                                "
                                >{{
                                    checkoutBundleId
                                        ? 'Opening checkout…'
                                        : 'Continue to Stripe'
                                }}<ExternalLink
                                    :size="13"
                                    aria-hidden="true" /></Button
                        ></DialogFooter>
                    </form>
                </template>
            </DialogContent>
        </Dialog>
        <Dialog
            :open="!!previewBundle"
            @update:open="!$event && (previewBundle = null)"
        >
            <DialogContent class="account-theme premium-preview-dialog">
                <template v-if="previewBundle">
                    <DialogHeader
                        ><DialogTitle>{{ previewBundle.name }}</DialogTitle
                        ><DialogDescription
                            >Take a seat at this table. Play the banishment and
                            victory effects to see what your gathering will
                            enjoy.</DialogDescription
                        ></DialogHeader
                    >
                    <CosmeticScenePreview
                        :table="bundleCosmetic(previewBundle, 'tables')"
                        :banishment="
                            bundleCosmetic(previewBundle, 'banishments')
                        "
                        :celebration="
                            bundleCosmetic(previewBundle, 'celebrations')
                        "
                    />
                    <p class="premium-note">
                        All {{ previewBundle.cosmetics.length }} cosmetics are
                        included for {{ money(previewBundle) }}. Equip each
                        detail separately in your wardrobe.
                    </p>
                </template>
            </DialogContent>
        </Dialog>
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
:global(.account-theme.store-consent-dialog) {
    max-width: min(560px, calc(100vw - 2rem));
    max-height: calc(100dvh - 2rem);
    overflow-y: auto;
}
.store-consent-copy {
    font-size: 12px;
    line-height: 1.8;
    color: var(--account-muted);
    margin: 17px 0;
}
.store-consent-label {
    display: flex;
    align-items: start;
    gap: 10px;
    font-size: 13px;
    line-height: 1.8;
    cursor: pointer;
}
.store-consent-label input {
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    margin-top: 4px;
    accent-color: var(--account-green);
}
.store-consent-dialog a {
    text-decoration: underline;
    text-underline-offset: 3px;
}
.store-consent-dialog :is(input, a):focus-visible {
    outline: 2px solid var(--account-green);
    outline-offset: 3px;
}
.store-consent-dialog .store-error {
    margin-bottom: 18px;
    line-height: 1.8;
}
.premium-collection {
    padding: 30px 0;
}
.premium-heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 28px;
    margin-bottom: 23px;
}
.premium-heading h3 {
    max-width: 470px;
    font:
        400 clamp(25px, 3vw, 34px)/1.18 'Fraunces',
        Georgia,
        serif;
    margin-top: 8px;
    letter-spacing: -0.7px;
}
.premium-heading > p {
    flex-shrink: 0;
    color: var(--account-muted);
    font-size: 11px;
    line-height: 1.8;
}
.premium-bundles {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}
.premium-bundle {
    --bundle-accent: #c3afd8;
    --bundle-dark: #343246;
    background: var(--account-panel);
    border: 1px solid var(--account-line);
    min-width: 0;
    display: flex;
    flex-direction: column;
}
.premium-bundle--founders-pack {
    --bundle-accent: #dec58a;
    --bundle-dark: #263d32;
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: minmax(230px, 0.85fr) minmax(0, 1.5fr);
    border-top: 3px solid #8e743d;
}
.premium-bundle--harvest-festival {
    --bundle-accent: #edbe79;
    --bundle-dark: #4b392b;
}
.premium-art {
    position: relative;
    color: #f5efd9;
    background: radial-gradient(
        ellipse at 50% 50%,
        color-mix(in srgb, var(--bundle-accent) 22%, var(--bundle-dark)),
        var(--bundle-dark) 78%
    );
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 25px 16px;
    min-height: 205px;
    overflow: hidden;
    border-bottom: 1px solid
        color-mix(in srgb, var(--bundle-accent) 40%, transparent);
}
.premium-edition {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.6px;
    color: var(--bundle-accent);
    text-align: center;
}
.premium-portrait-stage {
    position: relative;
    display: grid;
    place-items: center;
    width: 190px;
    height: 146px;
}
.premium-orbit {
    position: absolute;
    width: 170px;
    height: 72px;
    bottom: 4px;
    border: 1px solid color-mix(in srgb, var(--bundle-accent) 65%, transparent);
    border-radius: 50%;
    box-shadow:
        0 6px 0 -3px var(--bundle-dark),
        0 7px 0 -3px color-mix(in srgb, var(--bundle-accent) 45%, transparent);
    transform: rotate(-8deg);
}
.premium-art .character-portrait {
    display: block;
    width: 64px;
    height: 78px;
    border-radius: 35px 35px 4px 4px;
    border: 2px solid var(--bundle-accent);
}
.premium-bundle--founders-pack .premium-portrait-stage {
    height: 200px;
}
.premium-bundle--founders-pack .premium-art .character-portrait {
    width: 85px;
    height: 103px;
}
.premium-bundle--founders-pack .premium-orbit {
    width: 215px;
    height: 90px;
    bottom: 12px;
}
.premium-art-caption {
    display: flex;
    align-items: center;
    gap: 7px;
    font:
        italic 12px 'Fraunces',
        Georgia,
        serif;
    color: #ece3ca;
}
.premium-copy {
    padding: 23px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.premium-copy > .account-kicker {
    font-size: 9px;
    letter-spacing: 1.4px;
}
.premium-copy h4 {
    font:
        400 28px/1.15 'Fraunces',
        Georgia,
        serif;
    margin: 8px 0 10px;
    letter-spacing: -0.6px;
}
.premium-description {
    font-size: 12px;
    line-height: 1.8;
    color: var(--account-muted);
}
.premium-contents {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 16px;
    margin: 21px 0;
    padding: 0;
    list-style: none;
}
.premium-contents li {
    display: flex;
    align-items: start;
    gap: 6px;
    font-size: 11px;
    line-height: 1.5;
}
.premium-contents svg {
    flex-shrink: 0;
    color: var(--account-green);
    margin-top: 3px;
}
.premium-contents small {
    display: block;
    color: var(--account-muted);
    font-size: 9px;
}
.premium-preview-button {
    display: inline-flex;
    align-items: center;
    align-self: start;
    gap: 7px;
    font-size: 11px;
    text-decoration: underline;
    text-underline-offset: 4px;
    padding: 8px 0;
    margin-top: auto;
    cursor: pointer;
    color: var(--account-green);
}
.premium-preview-button:focus-visible {
    outline: 2px solid var(--account-green);
    outline-offset: 4px;
}
.premium-purchase {
    border-top: 1px solid var(--account-line);
    margin-top: 16px;
    padding-top: 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.premium-purchase strong {
    display: block;
    font:
        400 28px 'Fraunces',
        Georgia,
        serif;
}
.premium-purchase small {
    display: block;
    color: var(--account-muted);
    font-size: 9px;
    margin-top: 3px;
}
.premium-purchase button {
    font-size: 11px;
}
.premium-purchase button:disabled {
    opacity: 1;
    color: var(--account-muted);
    background: var(--account-deep);
}
.premium-note {
    color: var(--account-muted);
    font-size: 11px;
    line-height: 1.8;
    margin-top: 14px;
}
.premium-status {
    background: var(--account-deep);
    border-left: 3px solid var(--account-green);
    padding: 16px 19px;
    margin-bottom: 22px;
    font-size: 12px;
    line-height: 1.8;
}
.premium-status.has-error {
    border-left-color: var(--destructive);
}
.premium-status button,
.premium-status a {
    margin-top: 10px;
}
:global(.account-theme.premium-preview-dialog) {
    max-width: min(660px, calc(100vw - 2rem));
    max-height: calc(100dvh - 2rem);
    overflow-y: auto;
}
@media (max-width: 750px) {
    .premium-heading {
        align-items: start;
        flex-direction: column;
        gap: 12px;
    }
    .premium-bundle--founders-pack {
        grid-template-columns: 1fr;
    }
    .premium-bundle--founders-pack .premium-portrait-stage {
        height: 170px;
    }
    .premium-copy {
        padding: 20px 16px;
    }
    .premium-purchase {
        flex-wrap: wrap;
    }
    .premium-contents {
        grid-template-columns: 1fr;
    }
    .premium-bundle--founders-pack .premium-contents {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 480px) {
    .premium-bundles {
        grid-template-columns: 1fr;
    }
    .premium-bundle--founders-pack {
        grid-column: auto;
    }
    .premium-contents {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .premium-heading h3 {
        max-width: 320px;
    }
}
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
