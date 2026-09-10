<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import RegistrySection from '@/components/account/RegistrySection.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <RegistrySection
        number="I"
        title="Choose your light"
        description="A parchment ledger by day. Deep coastal ink by night."
    >
        <div class="registry-appearance space-y-6">
            <AppearanceTabs />
            <p class="appearance-explanation">
                Your choice is saved automatically for account pages. System
                follows your device’s light or dark setting. The village and
                game keep their nighttime atmosphere.
            </p>
        </div>
    </RegistrySection>
</template>
