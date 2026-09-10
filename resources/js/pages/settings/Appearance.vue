<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
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

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Choose your light"
            description="A parchment ledger by day. Deep coastal ink by night."
        />
        <AppearanceTabs />
        <p class="appearance-explanation">
            Your choice is saved automatically for account pages. System follows
            your device’s light or dark setting. The village and game keep their
            nighttime atmosphere.
        </p>
    </div>
</template>
