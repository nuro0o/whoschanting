<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
const page = usePage();
const section = computed(() => {
    if (page.url.startsWith('/settings/security'))
        return {
            title: 'Keep your secrets.',
            label: 'Security',
            description:
                'Protect your account, from your password to your next sign-in.',
        };
    if (page.url.startsWith('/settings/appearance'))
        return {
            title: 'Set the atmosphere.',
            label: 'Appearance',
            description:
                'A little lamplight, or a little darkness. Make the ledger yours.',
        };
    return {
        title: 'Your name in the book.',
        label: 'Profile',
        description:
            'The details that make your place in the village your own.',
    };
});
</script>

<template>
    <div class="account-settings">
        <header class="account-page-heading">
            <div>
                <p class="account-kicker">
                    Your account <span aria-hidden="true">/</span>
                    {{ section.label }}
                </p>
                <h1>{{ section.title }}</h1>
                <p class="account-heading-description">
                    {{ section.description }}
                </p>
            </div>
        </header>
        <div class="account-settings-columns">
            <section
                class="account-settings-form"
                :aria-label="section.label + ' settings'"
            >
                <slot />
            </section>
            <aside class="account-settings-aside">
                <img
                    src="/assets/chanting/verify-email-ferryman.png"
                    alt="The Ferryman offers a hand from his boat."
                />
                <div>
                    <p class="account-kicker">You belong here</p>
                    <h2>The boat is waiting.</h2>
                    <p>
                        Your next crossing is only a room away. Bring a few
                        familiar faces.
                    </p>
                    <Link href="/dashboard" class="account-text-link"
                        >Return to the ledger <ArrowRight :size="15"
                    /></Link>
                </div>
            </aside>
        </div>
        <Link href="/dashboard" class="account-settings-back"
            ><ArrowLeft :size="15" /> Back to the village ledger</Link
        >
    </div>
</template>
