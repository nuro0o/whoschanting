<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Moon, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
const page = usePage();
const isProfile = computed(() => page.url.startsWith('/settings/profile'));
const section = computed(() =>
    page.url.startsWith('/settings/security')
        ? {
              title: 'Some secrets are yours alone.',
              label: 'Security',
              chapter: 'III',
              description:
                  'A strong password. A second lock. A little peace of mind.',
              icon: ShieldCheck,
          }
        : {
              title: 'By lamplight. Or moonlight.',
              label: 'Appearance',
              chapter: 'IV',
              description: 'Choose the light in which you keep your records.',
              icon: Moon,
          },
);
</script>
<template>
    <div class="registry-settings">
        <header v-if="!isProfile" class="registry-settings-intro">
            <div>
                <p class="account-kicker">
                    The village register / {{ section.label }}
                </p>
                <h1>{{ section.title }}</h1>
                <p>{{ section.description }}</p>
            </div>
            <span class="registry-chapter-seal" aria-hidden="true"
                ><component
                    :is="section.icon"
                    :size="28"
                    :stroke-width="1"
                /><span>{{ section.chapter }}</span></span
            >
        </header>
        <div class="registry-settings-content"><slot /></div>
        <Link href="/dashboard" class="registry-back"
            ><ArrowLeft :size="15" /> Return to the village ledger</Link
        >
    </div>
</template>
