<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    Check,
    KeyRound,
    ShieldCheck,
    Users,
    Waves,
} from '@lucide/vue';
import { computed } from 'vue';
import RoomEntry from '@/components/chanting/RoomEntry.vue';
import { type Character } from '@/lib/chanting';

defineProps<{
    characters: Character[];
    preferredCharacter: string | null;
    rules: { min_players: number; max_players: number };
    twoFactorEnabled: boolean;
    passkeyCount: number;
    canManageTwoFactor: boolean;
    canManagePasskeys: boolean;
}>();
const page = usePage();
const user = computed(() => page.props.auth.user);
const membershipDate = computed(() => {
    const date = new Date(user.value.created_at);
    return Number.isNaN(date.getTime())
        ? null
        : new Intl.DateTimeFormat('en', {
              month: 'long',
              year: 'numeric',
          }).format(date);
});
</script>

<template>
    <Head title="Village ledger" />
    <div class="ledger-page">
        <header class="account-page-heading">
            <div>
                <p class="account-kicker">
                    The village ledger <span aria-hidden="true">/</span> Your
                    account
                </p>
                <h1>A place among <em>suspects.</em></h1>
            </div>
            <span class="ledger-edition"
                ><Waves :size="17" :stroke-width="1.3" /> THE TIDE BROUGHT YOU
                BACK</span
            >
        </header>
        <section class="ledger-arrival" aria-labelledby="arrival-title">
            <img
                src="/assets/chanting/welcome-email-ferryman.png"
                alt="The hooded Ferryman rows you through the mist toward the village."
                class="ledger-arrival-art"
            />
            <div class="ledger-arrival-copy">
                <p class="account-kicker">A familiar passenger</p>
                <h2 id="arrival-title">
                    Welcome back,<br /><span>{{ user.name }}.</span>
                </h2>
                <p>
                    Your seat is waiting. Your friends’<br
                        class="ledger-desktop-break"
                    />
                    intentions are less certain.
                </p>
            </div>
            <span class="ledger-arrival-caption" aria-hidden="true"
                >A SAFE CROSSING. PROBABLY.</span
            >
        </section>
        <div class="ledger-columns">
            <section class="ledger-play" aria-labelledby="gather-title">
                <header class="ledger-section-heading">
                    <div>
                        <p class="account-kicker">01 / Tonight’s company</p>
                        <h2 id="gather-title">Gather the usual suspects.</h2>
                    </div>
                    <span class="ledger-player-count"
                        ><Users :size="15" /> {{ rules.min_players }}–{{
                            rules.max_players
                        }}</span
                    >
                </header>
                <div class="account-entry chanting">
                    <RoomEntry
                        :characters="characters"
                        :preferred-character="preferredCharacter"
                        :initial-name="user.name"
                        compact-characters
                    />
                </div>
            </section>
            <aside class="ledger-record" aria-label="Your account at a glance">
                <section class="ledger-record-section">
                    <header class="ledger-record-heading">
                        <p class="account-kicker">02 / Village records</p>
                        <Link
                            href="/settings/profile"
                            aria-label="Edit your profile"
                            ><ArrowUpRight :size="18"
                        /></Link>
                    </header>
                    <h2>Your name in the book.</h2>
                    <dl class="ledger-account-details">
                        <div>
                            <dt>Name</dt>
                            <dd>{{ user.name }}</dd>
                        </div>
                        <div>
                            <dt>Email address</dt>
                            <dd>{{ user.email }}</dd>
                        </div>
                    </dl>
                    <span
                        class="ledger-status"
                        :class="{ 'is-unverified': !user.email_verified_at }"
                        ><Check v-if="user.email_verified_at" :size="13" />{{
                            user.email_verified_at
                                ? 'Email verified'
                                : 'Email unverified'
                        }}</span
                    >
                    <p v-if="membershipDate" class="ledger-member-since">
                        In the village since {{ membershipDate }}.
                    </p>
                </section>
                <section class="ledger-record-section ledger-security">
                    <h3>
                        <ShieldCheck :size="18" :stroke-width="1.4" /> Keep your
                        secrets safe.
                    </h3>
                    <dl>
                        <div v-if="canManageTwoFactor">
                            <dt>Two-factor authentication</dt>
                            <dd :class="{ enabled: twoFactorEnabled }">
                                {{
                                    twoFactorEnabled ? 'Enabled' : 'Not enabled'
                                }}
                            </dd>
                        </div>
                        <div v-if="canManagePasskeys">
                            <dt><KeyRound :size="13" /> Passkeys</dt>
                            <dd>
                                {{
                                    passkeyCount
                                        ? passkeyCount + ' added'
                                        : 'None added'
                                }}
                            </dd>
                        </div>
                    </dl>
                    <p v-if="!canManageTwoFactor && !canManagePasskeys">
                        Keep your password strong and your account protected.
                    </p>
                    <Link href="/settings/security" class="account-text-link"
                        >Manage security <ArrowRight :size="15"
                    /></Link>
                </section>
            </aside>
        </div>
        <section class="ledger-field-guide" aria-labelledby="field-guide-title">
            <span class="ledger-guide-symbol" aria-hidden="true">✦</span>
            <div>
                <h2 id="field-guide-title">
                    A little rusty at keeping secrets?
                </h2>
                <p>
                    Create a room, share the code, and let the suspicion begin.
                    Your character never reveals your role.
                </p>
            </div>
            <Link href="/#how-to-play" class="account-text-link"
                >How to play <ArrowUpRight :size="16"
            /></Link>
        </section>
    </div>
</template>
