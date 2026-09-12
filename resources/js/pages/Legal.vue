<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, Eye, Mail } from '@lucide/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import LegalLinks from '@/components/LegalLinks.vue';
import { roomRequest } from '@/lib/chanting';
import type {
    LegalDocument,
    LegalSettings,
    SupportReceipt,
} from '@/types/legal';
import '../../css/chanting.css';
import '../../css/account.css';

const props = defineProps<{
    document: LegalDocument;
    legal: LegalSettings;
    supportRequest?: SupportReceipt | null;
}>();
const form = reactive({
    name: '',
    email: '',
    order_reference: '',
    message: '',
    website: '',
});
const step = ref<'details' | 'review'>('details');
const pending = ref(false);
const error = ref('');
const receipt = ref<SupportReceipt | null>(props.supportRequest ?? null);
const page = usePage();
const orderEdited = ref(false);
watch(
    () => [page.url, receipt.value],
    () => {
        if (
            props.document.id !== 'refunds' ||
            receipt.value ||
            orderEdited.value ||
            form.order_reference
        )
            return;
        const order = new URLSearchParams(
            page.url.split('?')[1]?.split('#')[0],
        ).get('order');
        if (order) form.order_reference = order.slice(0, 255);
    },
    { immediate: true },
);
const reviewHeading = ref<HTMLElement>();
const detailsHeading = ref<HTMLElement>();
const receiptHeading = ref<HTMLElement>();
const submissionError = ref<HTMLElement>();
let requestId: string | undefined;
let confirmedPayload: string | undefined;
const supportEmail = computed(() => props.legal.support_email);
function emailLink(subject: string) {
    return `mailto:${supportEmail.value}?subject=${encodeURIComponent(subject)}`;
}
function displayDate(value: string) {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
              timeZone: 'UTC',
          }).format(date);
}
function displayTime(value: string) {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en', {
              dateStyle: 'long',
              timeStyle: 'short',
              timeZone: 'UTC',
          }).format(date) + ' UTC';
}
watch(
    () => props.supportRequest,
    (value) => {
        receipt.value = value ?? null;
    },
);
async function review() {
    error.value = '';
    step.value = 'review';
    await nextTick();
    reviewHeading.value?.focus();
}
async function edit() {
    if (pending.value) return;
    step.value = 'details';
    await nextTick();
    detailsHeading.value?.focus();
}
async function newRequest() {
    receipt.value = null;
    Object.assign(form, {
        name: '',
        email: '',
        order_reference: '',
        message: '',
        website: '',
    });
    requestId = undefined;
    confirmedPayload = undefined;
    orderEdited.value = false;
    step.value = 'details';
    error.value = '';
    await nextTick();
    detailsHeading.value?.focus();
}
async function submit() {
    if (pending.value || step.value !== 'review') return;
    pending.value = true;
    error.value = '';
    try {
        const payload = JSON.stringify(form);
        if (!requestId || payload !== confirmedPayload) {
            requestId = crypto.randomUUID();
            confirmedPayload = payload;
        }
        receipt.value = await roomRequest<SupportReceipt>('/refunds', {
            ...form,
            confirmation: true,
            request_id: requestId,
        });
        await nextTick();
        receiptHeading.value?.focus();
    } catch (caught) {
        error.value =
            caught instanceof Error
                ? caught.message
                : 'We could not record your request. Try again, or email support using the address below.';
        await nextTick();
        submissionError.value?.focus();
    } finally {
        pending.value = false;
    }
}
</script>

<template>
    <Head :title="document.title" />
    <div class="account-shell legal-page">
        <a class="legal-skip" href="#legal-content">Skip to content</a>
        <header class="legal-masthead">
            <Link href="/" class="legal-brand" aria-label="Who's Chanting? home"
                ><Eye :size="24" :stroke-width="1.4" aria-hidden="true" /><span
                    >who’s chanting<span class="legal-question">?</span></span
                ></Link
            >
            <Link href="/" class="legal-home"
                ><ArrowLeft :size="15" aria-hidden="true" /> Back to the
                village</Link
            >
        </header>
        <main id="legal-content" tabindex="-1">
            <header class="legal-heading">
                <p class="legal-kicker">
                    The village desk <span aria-hidden="true">/</span>
                    {{
                        document.id === 'contact'
                            ? 'Here to help'
                            : 'Clear information, in one place'
                    }}
                </p>
                <h1>{{ document.title }}</h1>
                <p class="legal-summary">{{ document.summary }}</p>
                <p class="legal-updated">
                    Updated {{ displayDate(document.updated_at) }}
                </p>
            </header>
            <div class="legal-layout">
                <aside class="legal-sidebar">
                    <nav aria-label="On this page">
                        <p class="legal-kicker">On this page</p>
                        <a
                            v-for="(section, index) in document.sections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            ><span aria-hidden="true">{{
                                String(index + 1).padStart(2, '0')
                            }}</span
                            >{{ section.title }}</a
                        ><a
                            v-if="document.id === 'refunds'"
                            href="#withdrawal-request"
                            ><span aria-hidden="true">↗</span>Submit a
                            withdrawal request</a
                        >
                    </nav>
                    <div class="legal-sidebar-help">
                        <Mail
                            :size="19"
                            :stroke-width="1.4"
                            aria-hidden="true"
                        />
                        <p>A question for us?</p>
                        <a :href="emailLink('Who’s Chanting support')"
                            >Email support
                            <ArrowRight :size="13" aria-hidden="true"
                        /></a>
                    </div>
                </aside>
                <div class="legal-reading">
                    <section
                        v-if="document.id === 'contact'"
                        class="contact-desk"
                        aria-labelledby="contact-desk-title"
                    >
                        <p class="legal-kicker">A direct line to the village</p>
                        <h2 id="contact-desk-title">How can we help?</h2>
                        <a
                            class="support-address"
                            :href="emailLink('Who’s Chanting support')"
                            >{{ supportEmail }}</a
                        >
                        <div class="contact-subjects">
                            <a :href="emailLink('General support')"
                                >General support
                                <ArrowRight :size="14" aria-hidden="true" /></a
                            ><a :href="emailLink('Purchase or refund question')"
                                >Purchases &amp; refunds
                                <ArrowRight :size="14" aria-hidden="true" /></a
                            ><a :href="emailLink('Privacy request')"
                                >A privacy request
                                <ArrowRight :size="14" aria-hidden="true"
                            /></a>
                        </div>
                    </section>
                    <section
                        v-for="section in document.sections"
                        :id="section.id"
                        :key="section.id"
                        class="legal-section"
                        :aria-labelledby="`${section.id}-title`"
                    >
                        <h2 :id="`${section.id}-title`">{{ section.title }}</h2>
                        <p
                            v-for="(paragraph, index) in section.paragraphs"
                            :key="index"
                        >
                            {{ paragraph }}
                        </p>
                        <ul v-if="section.bullets?.length">
                            <li
                                v-for="(bullet, index) in section.bullets"
                                :key="index"
                            >
                                {{ bullet }}
                            </li>
                        </ul>
                        <div
                            v-if="section.links?.length"
                            class="legal-section-links"
                        >
                            <a
                                v-for="link in section.links"
                                :key="link.href"
                                :href="link.href"
                                >{{ link.label }}
                                <ArrowRight :size="13" aria-hidden="true"
                            /></a>
                        </div>
                    </section>
                    <section
                        v-if="document.id === 'refunds'"
                        id="withdrawal-request"
                        class="withdrawal-panel"
                        aria-labelledby="withdrawal-title"
                    >
                        <template v-if="receipt">
                            <p class="legal-kicker">
                                <Check :size="14" aria-hidden="true" /> Request
                                received
                            </p>
                            <h2
                                id="withdrawal-title"
                                ref="receiptHeading"
                                tabindex="-1"
                            >
                                We’ve recorded your withdrawal request.
                            </h2>
                            <p>
                                Keep this reference for your records. This
                                confirms receipt of your request; it is not a
                                refund approval.
                            </p>
                            <dl class="withdrawal-review">
                                <div>
                                    <dt>Request reference</dt>
                                    <dd>{{ receipt.reference }}</dd>
                                </div>
                                <div>
                                    <dt>Received</dt>
                                    <dd>
                                        {{ displayTime(receipt.received_at) }}
                                    </dd>
                                </div>
                            </dl>
                            <p>
                                For questions about this request, email
                                <a
                                    :href="
                                        emailLink(
                                            `Withdrawal request ${receipt.reference}`,
                                        )
                                    "
                                    >{{ supportEmail }}</a
                                >
                                and include your reference.
                            </p>
                            <button
                                type="button"
                                class="legal-button"
                                @click="newRequest"
                            >
                                Submit another request
                            </button>
                        </template>
                        <template v-else>
                            <p class="legal-kicker">
                                Your purchase, your rights
                            </p>
                            <h2 id="withdrawal-title">
                                Submit a withdrawal request
                            </h2>
                            <p>
                                You can use this form without an account. Review
                                your details before sending your notification.
                            </p>
                            <ol
                                class="withdrawal-steps"
                                aria-label="Request steps"
                            >
                                <li
                                    :aria-current="
                                        step === 'details' ? 'step' : undefined
                                    "
                                >
                                    <span>1</span>Your details
                                </li>
                                <li
                                    :aria-current="
                                        step === 'review' ? 'step' : undefined
                                    "
                                >
                                    <span>2</span>Review &amp; confirm
                                </li>
                            </ol>
                            <form
                                v-if="step === 'details'"
                                @submit.prevent="review"
                            >
                                <h3 ref="detailsHeading" tabindex="-1">
                                    Your details
                                </h3>
                                <div class="withdrawal-fields">
                                    <label for="withdrawal-name"
                                        >Name<input
                                            id="withdrawal-name"
                                            v-model="form.name"
                                            name="name"
                                            autocomplete="name"
                                            maxlength="120"
                                            required /></label
                                    ><label for="withdrawal-email"
                                        >Email address<input
                                            id="withdrawal-email"
                                            v-model="form.email"
                                            type="email"
                                            name="email"
                                            autocomplete="email"
                                            maxlength="254"
                                            required
                                    /></label>
                                </div>
                                <label for="withdrawal-order"
                                    >Order or payment reference<input
                                        id="withdrawal-order"
                                        v-model="form.order_reference"
                                        @input="orderEdited = true"
                                        name="order_reference"
                                        maxlength="255"
                                        required
                                        aria-describedby="withdrawal-order-help"
                                /></label>
                                <p
                                    id="withdrawal-order-help"
                                    class="withdrawal-help"
                                >
                                    Use the reference from your purchase
                                    confirmation or payment receipt. If you
                                    cannot find it, email support for help.
                                </p>
                                <label for="withdrawal-message"
                                    >Anything else we should know?
                                    <span class="field-optional"
                                        >(optional)</span
                                    ><textarea
                                        id="withdrawal-message"
                                        v-model="form.message"
                                        name="message"
                                        maxlength="2000"
                                        rows="4"
                                    />
                                </label>
                                <div class="withdrawal-trap" aria-hidden="true">
                                    <label for="withdrawal-website"
                                        >Website<input
                                            id="withdrawal-website"
                                            v-model="form.website"
                                            name="website"
                                            tabindex="-1"
                                            autocomplete="off"
                                    /></label>
                                </div>
                                <p class="withdrawal-help">
                                    We use these details to handle your request.
                                    See our
                                    <Link href="/privacy">Privacy Notice</Link>.
                                </p>
                                <button class="legal-button" type="submit">
                                    Review request
                                    <ArrowRight :size="15" aria-hidden="true" />
                                </button>
                            </form>
                            <form v-else @submit.prevent="submit">
                                <h3 ref="reviewHeading" tabindex="-1">
                                    Check your notification
                                </h3>
                                <dl class="withdrawal-review">
                                    <div>
                                        <dt>Name</dt>
                                        <dd>{{ form.name }}</dd>
                                    </div>
                                    <div>
                                        <dt>Email</dt>
                                        <dd>{{ form.email }}</dd>
                                    </div>
                                    <div>
                                        <dt>Order reference</dt>
                                        <dd>{{ form.order_reference }}</dd>
                                    </div>
                                    <div v-if="form.message">
                                        <dt>Your message</dt>
                                        <dd class="withdrawal-message">
                                            {{ form.message }}
                                        </dd>
                                    </div>
                                </dl>
                                <p class="withdrawal-declaration">
                                    I notify you that I withdraw from the
                                    purchase identified above.
                                </p>
                                <p class="withdrawal-help">
                                    Confirming sends this notification to
                                    support. We’ll show a request reference when
                                    it has been recorded.
                                </p>
                                <p
                                    v-if="error"
                                    ref="submissionError"
                                    class="withdrawal-error"
                                    role="alert"
                                    tabindex="-1"
                                >
                                    {{ error }}
                                </p>
                                <div class="withdrawal-actions">
                                    <button
                                        class="legal-button"
                                        type="submit"
                                        :disabled="pending"
                                    >
                                        {{
                                            pending
                                                ? 'Sending notification…'
                                                : 'Confirm withdrawal'
                                        }}</button
                                    ><button
                                        type="button"
                                        class="legal-edit"
                                        :disabled="pending"
                                        @click="edit"
                                    >
                                        Edit details
                                    </button>
                                </div>
                            </form>
                            <p class="withdrawal-alternative">
                                Prefer email? Send your withdrawal notification
                                to
                                <a
                                    :href="emailLink('Withdrawal notification')"
                                    >{{ supportEmail }}</a
                                >.
                            </p>
                        </template>
                    </section>
                    <section
                        v-if="
                            legal.operator_name ||
                            legal.business_address ||
                            legal.registration_number
                        "
                        class="legal-operator"
                        aria-labelledby="legal-operator-title"
                    >
                        <h2 id="legal-operator-title">Service operator</h2>
                        <dl>
                            <div v-if="legal.operator_name">
                                <dt>Operator</dt>
                                <dd>{{ legal.operator_name }}</dd>
                            </div>
                            <div v-if="legal.business_address">
                                <dt>Business address</dt>
                                <dd>{{ legal.business_address }}</dd>
                            </div>
                            <div v-if="legal.registration_number">
                                <dt>Registration number</dt>
                                <dd>{{ legal.registration_number }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </div>
        </main>
        <footer class="legal-footer">
            <div>
                <strong>WHO’S CHANTING?</strong>
                <p>Good company. Clear information.</p>
            </div>
            <LegalLinks />
        </footer>
    </div>
</template>

<style scoped>
.legal-page {
    min-height: 100vh;
    padding: 0 clamp(20px, 5vw, 72px);
    font-family: 'DM Sans', sans-serif;
    color: var(--account-text);
}
.legal-masthead,
.legal-page main,
.legal-footer {
    max-width: 1120px;
    margin-inline: auto;
}
.legal-masthead {
    padding: 30px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    border-bottom: 1px solid var(--account-line);
}
.legal-brand {
    display: inline-flex;
    gap: 10px;
    align-items: center;
    font:
        500 26px 'Fraunces',
        Georgia,
        serif;
    letter-spacing: -0.7px;
}
.legal-question {
    color: var(--account-brass);
}
.legal-home {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}
.legal-kicker {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 1.6px;
    text-transform: uppercase;
    color: var(--account-brass);
}
.legal-heading {
    padding: 54px 0 40px;
    border-bottom: 1px solid var(--account-line);
}
.legal-heading h1 {
    font:
        400 clamp(38px, 5vw, 61px)/1.12 'Fraunces',
        Georgia,
        serif;
    letter-spacing: -1.8px;
    max-width: 900px;
    margin: 15px 0 20px;
}
.legal-summary {
    font-size: 16px;
    line-height: 1.8;
    max-width: 68ch;
    color: var(--account-muted);
}
.legal-updated {
    font-size: 11px;
    color: var(--account-muted);
    margin-top: 22px;
}
.legal-layout {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    gap: clamp(38px, 6vw, 88px);
    padding: 40px 0 64px;
}
.legal-sidebar {
    align-self: start;
    position: sticky;
    top: 28px;
}
.legal-sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.legal-sidebar nav > p {
    margin-bottom: 10px;
}
.legal-sidebar nav > a {
    display: flex;
    gap: 13px;
    align-items: baseline;
    padding: 8px 0;
    font-size: 12px;
    line-height: 1.6;
}
.legal-sidebar nav > a > span {
    font-size: 10px;
    color: var(--account-brass);
    font-variant-numeric: tabular-nums;
    flex: 0 0 15px;
}
.legal-sidebar-help {
    margin-top: 30px;
    border-top: 1px solid var(--account-line);
    padding-top: 22px;
}
.legal-sidebar-help > svg {
    color: var(--account-brass);
}
.legal-sidebar-help > p {
    font:
        400 19px 'Fraunces',
        Georgia,
        serif;
    margin: 11px 0;
}
.legal-sidebar-help > a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.legal-reading {
    max-width: 72ch;
    min-width: 0;
}
.legal-section {
    margin-bottom: 32px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--account-line);
    scroll-margin-top: 24px;
}
.legal-reading h2 {
    font:
        400 27px/1.3 'Fraunces',
        Georgia,
        serif;
    letter-spacing: -0.5px;
    margin-bottom: 16px;
}
.legal-reading p,
.legal-reading li,
.legal-reading dd {
    font-size: 14px;
    line-height: 1.9;
    overflow-wrap: anywhere;
}
.legal-section p + p {
    margin-top: 14px;
}
.legal-section ul {
    list-style: disc;
    padding-left: 22px;
    margin-top: 16px;
}
.legal-section li + li {
    margin-top: 9px;
}
.legal-section-links {
    display: flex;
    gap: 8px 22px;
    flex-wrap: wrap;
    margin-top: 16px;
}
.legal-section-links a {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
}
.legal-reading a {
    text-decoration: underline;
    text-underline-offset: 4px;
}
.legal-page a:hover {
    color: var(--account-green);
}
.legal-page :is(a, button, input, textarea):focus-visible {
    outline: 2px solid var(--account-green);
    outline-offset: 4px;
}
.legal-page :is(h2, h3, p)[tabindex='-1']:focus {
    outline: 2px solid var(--account-green);
    outline-offset: 6px;
}
.contact-desk {
    background: #263e35;
    color: #f1eddb;
    padding: 30px;
    border-top: 3px solid #af955d;
    margin-bottom: 36px;
}
.contact-desk .legal-kicker {
    color: #decb97;
    font-size: 10px;
}
.contact-desk h2 {
    margin-top: 14px;
    font-size: 32px;
}
.support-address {
    display: inline-block;
    font-size: clamp(12px, 1.5vw, 16px);
    overflow-wrap: anywhere;
}
.contact-desk a:hover {
    color: #decb97;
}
.contact-desk a:focus-visible {
    outline-color: #decb97;
}
.contact-subjects {
    display: grid;
    gap: 9px;
    margin-top: 24px;
    border-top: 1px solid #c6cba53b;
    padding-top: 20px;
}
.contact-subjects a {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding-block: 5px;
    font-size: 12px;
}
.legal-operator {
    margin-top: 35px;
}
.legal-operator dl > div {
    margin-top: 12px;
}
.legal-operator dt,
.withdrawal-review dt {
    font-size: 11px;
    color: var(--account-muted);
}
.legal-operator dd {
    white-space: pre-line;
}
.withdrawal-panel {
    background: var(--account-panel);
    border: 1px solid var(--account-line);
    border-top: 3px solid var(--account-brass);
    padding: 30px;
    scroll-margin-top: 24px;
}
.withdrawal-panel > h2 {
    margin-top: 13px;
}
.withdrawal-panel > p {
    font-size: 13px;
}
.withdrawal-steps {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 20px;
    padding: 21px 0;
    margin: 17px 0 21px;
    border-block: 1px solid var(--account-line);
    list-style: none;
}
.withdrawal-steps li {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--account-muted);
    font-size: 12px;
}
.withdrawal-steps li span {
    border: 1px solid var(--account-line);
    width: 24px;
    height: 24px;
    display: grid;
    place-items: center;
    font-size: 10px;
}
.withdrawal-steps li[aria-current='step'] {
    color: var(--account-text);
    font-weight: 600;
}
.withdrawal-steps li[aria-current='step'] span {
    background: var(--account-green);
    color: #fff;
    border-color: var(--account-green);
}
.withdrawal-panel h3 {
    font:
        400 21px 'Fraunces',
        Georgia,
        serif;
    margin-bottom: 20px;
}
.withdrawal-panel label {
    display: block;
    font-size: 12px;
    line-height: 1.7;
    margin-top: 19px;
}
.withdrawal-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
.withdrawal-fields label {
    margin-top: 0;
}
.withdrawal-panel :is(input, textarea) {
    display: block;
    width: 100%;
    border: 1px solid #9fa798;
    background: #fffdf6;
    padding: 10px 12px;
    color: var(--account-text);
    border-radius: 0;
    margin-top: 7px;
    font:
        400 16px/1.5 'DM Sans',
        sans-serif;
}
.withdrawal-panel textarea {
    resize: vertical;
}
.withdrawal-panel .withdrawal-help {
    font-size: 11px;
    line-height: 1.8;
    color: var(--account-muted);
    margin-top: 9px;
}
.field-optional {
    color: var(--account-muted);
}
.withdrawal-trap {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
}
.legal-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 12px 18px;
    margin-top: 22px;
    border: 1px solid var(--account-green);
    background: var(--account-green);
    color: #fffdf2;
    font:
        500 13px/1.5 'DM Sans',
        sans-serif;
    cursor: pointer;
}
.legal-button:hover {
    background: #334d33;
}
.legal-button:disabled,
.legal-edit:disabled {
    opacity: 0.65;
    cursor: wait;
}
.withdrawal-review {
    margin-bottom: 20px;
}
.withdrawal-review > div {
    padding-block: 12px;
    border-bottom: 1px solid var(--account-line);
}
.withdrawal-review dd {
    margin-top: 3px;
}
.withdrawal-message {
    white-space: pre-wrap;
}
.withdrawal-panel .withdrawal-declaration {
    border-left: 3px solid var(--account-brass);
    background: var(--account-deep);
    padding: 14px 16px;
    font-size: 15px;
}
.withdrawal-actions {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 22px;
}
.legal-edit {
    font: inherit;
    font-size: 12px;
    text-decoration: underline;
    text-underline-offset: 4px;
    cursor: pointer;
}
.withdrawal-panel .withdrawal-error {
    color: #9c3429;
    background: #f7e7df;
    border-left: 3px solid #9c3429;
    padding: 12px 15px;
    margin-top: 16px;
    font-size: 13px;
}
.withdrawal-panel .withdrawal-alternative {
    border-top: 1px solid var(--account-line);
    margin-top: 25px;
    padding-top: 20px;
    font-size: 12px;
}
.legal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
    border-top: 1px solid var(--account-line);
    padding: 27px 0 35px;
}
.legal-footer strong {
    font-size: 10px;
    letter-spacing: 1.5px;
    color: var(--account-brass);
}
.legal-footer p {
    font:
        italic 13px 'Fraunces',
        Georgia,
        serif;
    margin-top: 7px;
    color: var(--account-muted);
}
.legal-skip {
    position: absolute;
    top: 8px;
    left: 20px;
    transform: translateY(-200%);
    padding: 10px;
    background: var(--account-panel);
    z-index: 10;
}
.legal-skip:focus {
    transform: none;
}
@media (max-width: 800px) {
    .legal-layout {
        grid-template-columns: 1fr;
        gap: 32px;
        padding-top: 28px;
    }
    .legal-sidebar {
        position: static;
    }
    .legal-sidebar nav {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        gap: 4px 20px;
        padding-bottom: 22px;
        border-bottom: 1px solid var(--account-line);
    }
    .legal-sidebar nav > p {
        width: 100%;
        margin-bottom: 5px;
    }
    .legal-sidebar nav > a {
        font-size: 12px;
    }
    .legal-sidebar-help {
        display: none;
    }
    .legal-reading {
        max-width: none;
    }
}
@media (max-width: 520px) {
    .legal-masthead {
        padding-block: 22px;
        gap: 14px;
        flex-wrap: wrap;
    }
    .legal-brand {
        font-size: 24px;
    }
    .legal-home {
        font-size: 11px;
    }
    .legal-heading {
        padding-block: 36px 30px;
    }
    .legal-heading h1 {
        letter-spacing: -1px;
    }
    .legal-summary {
        font-size: 14px;
    }
    .legal-kicker {
        font-size: 9px;
        letter-spacing: 1.1px;
        flex-wrap: wrap;
    }
    .legal-reading h2 {
        font-size: 25px;
    }
    .legal-reading p,
    .legal-reading li {
        font-size: 13px;
    }
    .contact-desk,
    .withdrawal-panel {
        padding: 22px 18px;
    }
    .withdrawal-fields {
        grid-template-columns: 1fr;
        gap: 18px;
    }
    .withdrawal-steps {
        gap: 14px;
    }
    .withdrawal-steps li {
        font-size: 11px;
    }
    .legal-button {
        width: 100%;
    }
}
</style>
