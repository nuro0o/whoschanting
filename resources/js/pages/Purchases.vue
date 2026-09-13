<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Clock3,
    Mail,
    ReceiptText,
    ShoppingBag,
} from '@lucide/vue';
import type { Purchase, PurchasePage } from '@/types/purchases';

defineProps<{ purchases: PurchasePage }>();
const page = usePage();
const categories: Record<string, string> = {
    titles: 'Title',
    frames: 'Frame',
    accents: 'Accent',
    backgrounds: 'Backdrop',
    tables: 'Table',
    banishments: 'Banishment',
    celebrations: 'Celebration',
    characters: 'Character',
};
const statuses: Record<string, string> = {
    pending: 'Awaiting payment',
    paid: 'Paid',
    refunded: 'Refunded',
    disputed: 'Under review',
    expired: 'Checkout expired',
    failed: 'Payment unsuccessful',
};
function statusLabel(status: string) {
    return statuses[status] ?? status.replaceAll('_', ' ');
}
function amount(purchase: Purchase) {
    return new Intl.NumberFormat('en-IE', {
        style: 'currency',
        currency: purchase.currency,
    }).format((purchase.total_amount ?? purchase.amount) / 100);
}
function date(value: string, time = false) {
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return (
        new Intl.DateTimeFormat('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            ...(time ? ({ hour: '2-digit', minute: '2-digit' } as const) : {}),
            timeZone: 'UTC',
        }).format(parsed) + (time ? ' UTC' : '')
    );
}
function supportUrl(purchase: Purchase) {
    return `mailto:${page.props.legal.support_email}?subject=${encodeURIComponent(`Purchase ${purchase.id} — ${purchase.name}`)}`;
}
function paginationLabel(label: string) {
    return label.replace(/&laquo;|&raquo;/g, '').trim();
}
</script>

<template>
    <Head title="Your purchases" />
    <div class="purchases-page">
        <header class="purchase-heading">
            <div>
                <p class="account-kicker">
                    The village register
                    <span aria-hidden="true">/</span> Purchases
                </p>
                <h1>Your little pieces <em>of the village.</em></h1>
                <p>
                    Every pack, its contents and the moments it has joined you
                    at the table. Your purchase records stay together here.
                </p>
            </div>
            <Link href="/settings/profile#store" class="account-text-link"
                ><ShoppingBag :size="16" aria-hidden="true" /> Visit the Crown
                Store <ArrowRight :size="14" aria-hidden="true"
            /></Link>
        </header>
        <section
            class="purchase-banner"
            aria-labelledby="purchase-banner-title"
        >
            <div>
                <p class="account-kicker">A village kept alight, together</p>
                <h2 id="purchase-banner-title">
                    Thank you for supporting the village.
                </h2>
                <p>
                    Purchases help keep the gathering going. The core game stays
                    free.
                </p>
            </div>
            <img
                src="/assets/chanting/village-morning.webp"
                alt=""
                width="1536"
                height="1024"
            />
        </section>
        <template v-if="purchases.data.length">
            <div class="purchase-list-heading">
                <h2>Your purchase records</h2>
                <span
                    >Page {{ purchases.current_page }} of
                    {{ purchases.last_page }}</span
                >
            </div>
            <ol class="purchase-list" aria-label="Your purchases">
                <li v-for="purchase in purchases.data" :key="purchase.id">
                    <article
                        class="purchase-record"
                        :class="`purchase-record--${purchase.status}`"
                        :aria-labelledby="`purchase-${purchase.id}`"
                    >
                        <header class="purchase-record-heading">
                            <div>
                                <p class="account-kicker">
                                    {{
                                        purchase.cosmetics.length
                                            ? 'Cosmetic bundle'
                                            : 'Expansion'
                                    }}
                                </p>
                                <h3 :id="`purchase-${purchase.id}`">
                                    {{ purchase.name }}
                                </h3>
                                <p>
                                    {{
                                        purchase.paid_at
                                            ? 'Purchased'
                                            : 'Created'
                                    }}
                                    {{
                                        date(
                                            purchase.paid_at ??
                                                purchase.created_at,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="purchase-price">
                                <span
                                    class="purchase-status"
                                    :class="`purchase-status--${purchase.status}`"
                                    ><Check
                                        v-if="purchase.status === 'paid'"
                                        :size="12"
                                        aria-hidden="true"
                                    /><Clock3
                                        v-else-if="
                                            purchase.status === 'pending'
                                        "
                                        :size="12"
                                        aria-hidden="true"
                                    />{{ statusLabel(purchase.status) }}</span
                                ><strong>{{ amount(purchase) }}</strong
                                ><small>{{
                                    purchase.total_amount !== null
                                        ? 'Total'
                                        : purchase.paid_at
                                          ? 'Subtotal · final total unavailable'
                                          : 'Bundle subtotal'
                                }}</small>
                            </div>
                        </header>
                        <div class="purchase-record-body">
                            <div class="purchase-keepsakes">
                                <h4>
                                    <ReceiptText
                                        :size="15"
                                        aria-hidden="true"
                                    />
                                    Included in this pack
                                </h4>
                                <ul>
                                    <li
                                        v-for="item in purchase.cosmetics"
                                        :key="`${item.category}-${item.id}`"
                                    >
                                        <span>{{ item.name }}</span
                                        ><small>{{
                                            categories[item.category] ??
                                            item.category
                                        }}</small>
                                    </li>
                                    <li v-if="!purchase.cosmetics.length">
                                        <span>{{ purchase.name }}</span
                                        ><small>Expansion access</small>
                                    </li>
                                </ul>
                                <dl class="purchase-use">
                                    <div>
                                        <dt>Match use</dt>
                                        <dd>
                                            {{ purchase.games_used }}
                                            {{
                                                purchase.games_used === 1
                                                    ? 'game'
                                                    : 'games'
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>First used</dt>
                                        <dd>
                                            {{
                                                purchase.first_used_at
                                                    ? date(
                                                          purchase.first_used_at,
                                                      )
                                                    : 'Not used in a match'
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                                <p class="purchase-receipt-status">
                                    <Mail
                                        :size="13"
                                        aria-hidden="true"
                                    /><span>{{
                                        purchase.receipt_sent_at
                                            ? `Confirmation emailed ${date(purchase.receipt_sent_at)}`
                                            : 'No email confirmation recorded yet'
                                    }}</span>
                                </p>
                            </div>
                            <aside
                                class="purchase-refund"
                                :aria-labelledby="`refund-${purchase.id}`"
                            >
                                <p class="account-kicker">Refund information</p>
                                <h4 :id="`refund-${purchase.id}`">
                                    {{ purchase.refund.label }}
                                </h4>
                                <p>{{ purchase.refund.explanation }}</p>
                                <p
                                    v-if="purchase.refund.deadline"
                                    class="purchase-deadline"
                                >
                                    Policy deadline:
                                    <time
                                        :datetime="purchase.refund.deadline"
                                        >{{
                                            date(purchase.refund.deadline, true)
                                        }}</time
                                    >
                                </p>
                                <div class="purchase-help-actions">
                                    <Link
                                        v-if="purchase.refund.can_request"
                                        :href="purchase.withdrawal_url"
                                        class="purchase-refund-link"
                                        >Request refund
                                        <ArrowRight
                                            :size="14"
                                            aria-hidden="true" /></Link
                                    ><a :href="supportUrl(purchase)"
                                        >Email support</a
                                    >
                                </div>
                            </aside>
                        </div>
                        <footer class="purchase-reference">
                            <span>Order reference</span
                            ><code>{{ purchase.id }}</code
                            ><Link
                                v-if="purchase.status === 'paid'"
                                href="/progression"
                                >Open wardrobe
                                <ArrowRight :size="13" aria-hidden="true"
                            /></Link>
                        </footer>
                    </article>
                </li>
            </ol>
            <nav
                v-if="purchases.last_page > 1"
                class="purchase-pagination"
                aria-label="Purchase history pages"
            >
                <template v-for="(link, index) in purchases.links" :key="index"
                    ><Link
                        v-if="link.url"
                        :href="link.url"
                        :aria-current="link.active ? 'page' : undefined"
                        :aria-label="
                            /^\d+$/.test(link.label)
                                ? `Page ${link.label}`
                                : paginationLabel(link.label)
                        "
                        >{{ paginationLabel(link.label) }}</Link
                    ><span v-else :aria-disabled="true">{{
                        paginationLabel(link.label)
                    }}</span></template
                >
            </nav>
        </template>
        <section
            v-else
            class="purchase-empty"
            aria-labelledby="purchase-empty-title"
        >
            <ReceiptText :size="32" :stroke-width="1.2" aria-hidden="true" />
            <p class="account-kicker">A fresh page in the register</p>
            <h2 id="purchase-empty-title">No purchases here yet.</h2>
            <p>
                When you buy a cosmetic pack, its contents, confirmation details
                and refund options will appear here.
            </p>
            <Link href="/settings/profile#store" class="purchase-empty-link"
                >Explore the Crown Store
                <ArrowRight :size="15" aria-hidden="true"
            /></Link>
        </section>
        <aside class="purchase-policy-note">
            <p>
                Request a refund within 14 days if your purchase has been used
                in no more than two distinct games. After the third game, this
                refund option is unavailable; support remains available for
                other purchase issues and applicable statutory rights.
            </p>
            <h2>What counts as using a pack?</h2>
            <p>
                A pack is used when one of its cosmetics is used in a started
                match. Previewing, equipping and waiting in the lobby do not
                count. A table counts when you host with it; an animation counts
                when it actually plays. For expansion access, a match counts
                against the purchase that enables the expansion for the
                gathering.
            </p>
            <p>
                You can always ask us about a purchase, including faulty or
                missing content.
                <Link href="/refunds">Read the refund policy</Link> or
                <Link href="/contact">contact support</Link>.
            </p>
        </aside>
    </div>
</template>

<style scoped>
.purchase-heading {
    display: flex;
    justify-content: space-between;
    align-items: end;
    gap: 30px;
    margin-bottom: 32px;
}
.purchase-heading h1 {
    font:
        400 clamp(34px, 4vw, 48px)/1.14 'Fraunces',
        Georgia,
        serif;
    letter-spacing: -1.4px;
    margin: 15px 0 16px;
    max-width: 650px;
}
.purchase-heading h1 em {
    color: var(--account-green);
    font-weight: 400;
}
.purchase-heading > div > p:last-child {
    color: var(--account-muted);
    font-size: 13px;
    line-height: 1.8;
    max-width: 610px;
}
.purchase-heading > a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    padding-bottom: 5px;
}
.purchase-banner {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(240px, 1fr);
    border: 1px solid var(--account-line);
    background: #263f36;
    color: #eee8d6;
    margin-bottom: 34px;
    overflow: hidden;
}
.purchase-banner > div {
    padding: 28px 30px;
    align-self: center;
}
.purchase-banner .account-kicker {
    color: #d9c699;
    font-size: 9px;
}
.purchase-banner h2 {
    font:
        400 27px/1.25 'Fraunces',
        Georgia,
        serif;
    margin: 12px 0;
    max-width: 380px;
}
.purchase-banner p:last-child {
    color: #d2ddcd;
    font-size: 12px;
    line-height: 1.8;
    max-width: 390px;
}
.purchase-banner img {
    width: 100%;
    height: 100%;
    min-height: 205px;
    max-height: 240px;
    object-fit: cover;
    object-position: 50% 60%;
}
.purchase-list-heading {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 19px;
}
.purchase-list-heading h2 {
    font:
        400 25px 'Fraunces',
        Georgia,
        serif;
}
.purchase-list-heading > span {
    font-size: 11px;
    color: var(--account-muted);
}
.purchase-list {
    list-style: none;
    padding: 0;
    display: grid;
    gap: 24px;
}
.purchase-record {
    border: 1px solid var(--account-line);
    border-top: 3px solid var(--account-brass);
    background: var(--account-panel);
}
.purchase-record--refunded,
.purchase-record--expired,
.purchase-record--failed {
    border-top-color: #8c9284;
}
.purchase-record-heading {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 24px;
    padding: 23px 26px 20px;
    border-bottom: 1px solid var(--account-line);
}
.purchase-record-heading .account-kicker {
    font-size: 9px;
}
.purchase-record h3 {
    font:
        400 29px/1.2 'Fraunces',
        Georgia,
        serif;
    letter-spacing: -0.6px;
    margin: 8px 0 10px;
}
.purchase-record-heading > div > p:last-child {
    font-size: 11px;
    color: var(--account-muted);
}
.purchase-price {
    text-align: right;
    flex-shrink: 0;
    max-width: 195px;
}
.purchase-price strong {
    display: block;
    font:
        400 28px 'Fraunces',
        Georgia,
        serif;
    margin-top: 10px;
}
.purchase-price small {
    display: block;
    font-size: 9px;
    color: var(--account-muted);
    margin-top: 3px;
}
.purchase-status {
    display: inline-flex;
    gap: 5px;
    align-items: center;
    padding: 3px 8px;
    font-size: 10px;
    background: var(--account-deep);
    border: 1px solid var(--account-line);
    color: var(--account-text);
}
.purchase-status--paid {
    background: #e2e9d6;
    border-color: #b7c9a4;
    color: #365139;
}
.purchase-status--disputed,
.purchase-status--pending {
    background: #efe5ca;
    border-color: #c9bb93;
    color: #675426;
}
.purchase-record-body {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(240px, 1fr);
}
.purchase-keepsakes {
    padding: 23px 26px;
}
.purchase-keepsakes h4 {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 500;
}
.purchase-keepsakes h4 svg {
    color: var(--account-brass);
}
.purchase-keepsakes ul {
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px 20px;
    margin: 18px 0 23px;
}
.purchase-keepsakes li {
    font-size: 12px;
    line-height: 1.6;
}
.purchase-keepsakes li small {
    display: block;
    color: var(--account-muted);
    font-size: 10px;
}
.purchase-use {
    display: flex;
    flex-wrap: wrap;
    gap: 15px 36px;
    border-top: 1px solid var(--account-line);
    padding-top: 17px;
}
.purchase-use dt {
    font-size: 10px;
    color: var(--account-muted);
    margin-bottom: 5px;
}
.purchase-use dd {
    font-size: 12px;
    font-weight: 500;
}
.purchase-receipt-status {
    display: flex;
    align-items: start;
    gap: 7px;
    font-size: 10px;
    line-height: 1.7;
    color: var(--account-muted);
    margin-top: 16px;
}
.purchase-receipt-status svg {
    flex-shrink: 0;
    margin-top: 2px;
}
.purchase-refund {
    padding: 24px;
    border-left: 1px solid var(--account-line);
    background: color-mix(
        in srgb,
        var(--account-deep) 48%,
        var(--account-panel)
    );
}
.purchase-refund .account-kicker {
    font-size: 9px;
}
.purchase-refund h4 {
    font:
        400 21px/1.35 'Fraunces',
        Georgia,
        serif;
    margin: 11px 0;
}
.purchase-refund > p:not(.account-kicker) {
    font-size: 12px;
    line-height: 1.85;
    color: var(--account-muted);
}
.purchase-refund .purchase-deadline {
    margin-top: 14px;
}
.purchase-deadline time {
    display: block;
    color: var(--account-text);
    font-weight: 500;
}
.purchase-help-actions {
    display: flex;
    flex-direction: column;
    align-items: start;
    gap: 10px;
    margin-top: 21px;
}
.purchase-help-actions > a {
    font-size: 11px;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.purchase-help-actions .purchase-refund-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 500;
    color: var(--account-green);
    padding: 8px 0;
}
.purchase-reference {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 5px 12px;
    padding: 13px 26px;
    border-top: 1px solid var(--account-line);
}
.purchase-reference > span {
    font-size: 9px;
    color: var(--account-muted);
}
.purchase-reference code {
    font-family: inherit;
    font-size: 10px;
    overflow-wrap: anywhere;
}
.purchase-reference > a {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    margin-left: auto;
    text-decoration: underline;
    text-underline-offset: 3px;
}
.purchase-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    margin-top: 28px;
}
.purchase-pagination > * {
    padding: 8px 12px;
    min-width: 36px;
    text-align: center;
    border: 1px solid var(--account-line);
    font-size: 12px;
}
.purchase-pagination > a[aria-current='page'] {
    background: var(--account-green);
    color: #fffdf1;
    border-color: var(--account-green);
}
.purchase-pagination > span {
    color: var(--account-muted);
}
.purchase-policy-note {
    border-top: 1px solid var(--account-line);
    padding-top: 23px;
    margin: 32px 0;
}
.purchase-policy-note h2 {
    font:
        400 21px 'Fraunces',
        Georgia,
        serif;
    margin-bottom: 11px;
}
.purchase-policy-note p {
    font-size: 12px;
    line-height: 1.8;
    color: var(--account-muted);
    max-width: 840px;
}
.purchase-policy-note p + p {
    margin-top: 9px;
}
.purchase-policy-note a {
    text-decoration: underline;
    text-underline-offset: 3px;
}
.purchase-empty {
    border-block: 1px solid var(--account-line);
    text-align: center;
    padding: 48px 24px;
    background: var(--account-panel);
}
.purchase-empty > svg {
    margin: 0 auto 18px;
    color: var(--account-brass);
}
.purchase-empty h2 {
    font:
        400 31px 'Fraunces',
        Georgia,
        serif;
    margin: 13px 0;
}
.purchase-empty > p:not(.account-kicker) {
    font-size: 13px;
    line-height: 1.8;
    color: var(--account-muted);
    max-width: 430px;
    margin-inline: auto;
}
.purchase-empty-link {
    display: inline-flex;
    gap: 10px;
    align-items: center;
    background: var(--account-green);
    color: #fffdf1;
    padding: 11px 17px;
    margin-top: 23px;
    font-size: 12px;
}
.purchases-page a:focus-visible {
    outline: 2px solid var(--account-green);
    outline-offset: 4px;
}
.purchases-page a:hover {
    text-decoration: underline;
    text-underline-offset: 4px;
}
@media (max-width: 1000px) {
    .purchase-heading {
        align-items: start;
        flex-direction: column;
        gap: 17px;
    }
    .purchase-record-body {
        grid-template-columns: minmax(0, 1.2fr) minmax(220px, 1fr);
    }
}
@media (max-width: 700px) {
    .purchase-banner {
        grid-template-columns: 1fr;
    }
    .purchase-banner img {
        max-height: 175px;
        min-height: 150px;
        order: -1;
    }
    .purchase-banner > div {
        padding: 23px;
    }
    .purchase-record-body {
        grid-template-columns: 1fr;
    }
    .purchase-refund {
        border-left: 0;
        border-top: 1px solid var(--account-line);
        padding: 21px;
    }
    .purchase-record-heading,
    .purchase-keepsakes {
        padding: 21px;
    }
    .purchase-record h3 {
        font-size: 25px;
    }
    .purchase-help-actions {
        flex-direction: row;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px 20px;
    }
    .purchase-price {
        max-width: 132px;
    }
    .purchase-reference {
        padding: 13px 21px;
    }
}
@media (max-width: 430px) {
    .purchase-heading h1 {
        font-size: 35px;
    }
    .purchase-banner h2 {
        font-size: 25px;
    }
    .purchase-record-heading {
        flex-wrap: wrap;
        gap: 15px;
    }
    .purchase-price {
        display: flex;
        align-items: center;
        gap: 8px 12px;
        flex-wrap: wrap;
        max-width: none;
        width: 100%;
        text-align: left;
    }
    .purchase-price strong {
        margin-top: 0;
        font-size: 24px;
        margin-left: auto;
    }
    .purchase-price small {
        width: 100%;
        text-align: right;
        margin-top: 0;
    }
    .purchase-reference > a {
        width: 100%;
        margin-top: 8px;
    }
    .purchase-list-heading h2 {
        font-size: 22px;
    }
}
</style>
