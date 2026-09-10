<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Moon, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { home } from '@/routes';
import '../../../css/chanting.css';
import '../../../css/chanting-auth.css';

defineProps<{ title?: string; description?: string }>();
const page = usePage();
const scene = computed(() => {
    if (page.component === 'auth/Register') {
        return {
            image: '/assets/chanting/auth-register.png',
            caption: 'EVERY VILLAGE HAS ITS SECRETS.',
            heading: 'A new face.\nA fresh alibi.',
            description: 'Come for the company. Stay for the accusations.',
            eyebrow: 'MAKE YOURSELF AT HOME',
        };
    }
    if (page.component === 'auth/VerifyEmail') {
        return {
            image: '/assets/chanting/auth-register.png',
            caption: 'A LITTLE INTRODUCTION FIRST.',
            heading: 'Almost one\nof the locals.',
            description: 'Your seat is waiting. Check your inbox to take it.',
            eyebrow: 'ONE LAST STEP',
        };
    }
    return {
        image: '/assets/chanting/auth-login.png',
        caption: 'THE LANTERNS ARE STILL LIT.',
        heading: 'Familiar faces.\nQuestionable motives.',
        description: 'There’s always a place for you at the table.',
        eyebrow:
            page.component === 'auth/Login'
                ? 'BACK IN THE VILLAGE'
                : 'KEEP YOUR ACCOUNT YOURS',
    };
});
</script>

<template>
    <div class="chanting chanting-auth">
        <header class="auth-header">
            <Link
                :href="home()"
                class="wordmark"
                aria-label="Who's Chanting? home"
            >
                <span class="brand-eye"><Eye :size="25" /></span>
                who’s chanting<span class="brand-question">?</span>
            </Link>
            <Link
                :href="home()"
                class="auth-home-link"
                aria-label="Back to the village"
            >
                <ArrowLeft :size="15" /> <span>Back to the village</span>
            </Link>
        </header>
        <main class="auth-main">
            <aside class="auth-scene" aria-label="A glimpse of the village">
                <img
                    :key="scene.image"
                    :src="scene.image"
                    alt=""
                    class="auth-scene-image"
                    fetchpriority="high"
                />
                <div class="auth-scene-shade"></div>
                <div class="auth-scene-copy">
                    <p class="auth-scene-caption">
                        <Moon :size="13" /> {{ scene.caption }}
                    </p>
                    <h2>{{ scene.heading }}</h2>
                    <p class="auth-scene-description">
                        {{ scene.description }}
                    </p>
                </div>
                <span class="auth-scene-number" aria-hidden="true"
                    >A VERY SUSPICIOUS LITTLE VILLAGE</span
                >
            </aside>
            <section class="auth-form-side" aria-labelledby="auth-title">
                <div class="auth-form-content">
                    <div class="auth-intro">
                        <p class="eyebrow"><span></span>{{ scene.eyebrow }}</p>
                        <h1 id="auth-title">{{ title }}</h1>
                        <p class="auth-description">{{ description }}</p>
                    </div>
                    <div class="auth-fields"><slot /></div>
                    <p
                        v-if="page.component === 'auth/Register'"
                        class="auth-footnote"
                    >
                        <Sparkles :size="16" /> Pick your favourite character.
                        Keep your role a secret.
                    </p>
                    <p
                        v-else-if="page.component === 'auth/Login'"
                        class="auth-footnote"
                    >
                        <Moon :size="15" /> Trust your friends. Mostly.
                    </p>
                </div>
                <p class="auth-bottom-note">GOOD COMPANY. TERRIBLE SECRETS.</p>
            </section>
        </main>
    </div>
</template>
