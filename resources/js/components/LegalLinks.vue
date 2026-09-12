<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
withDefaults(defineProps<{ label?: string }>(), {
    label: 'Support and legal information',
});
const links = [
    { href: '/contact', name: 'Contact' },
    { href: '/privacy', name: 'Privacy' },
    { href: '/terms', name: 'Terms' },
    { href: '/refunds', name: 'Refunds' },
];
</script>

<template>
    <nav class="legal-links" :aria-label="label">
        <Link
            v-for="link in links"
            :key="link.href"
            :href="link.href"
            :aria-current="
                page.url.split('?')[0] === link.href ? 'page' : undefined
            "
            >{{ link.name }}</Link
        >
    </nav>
</template>

<style scoped>
.legal-links {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px 20px;
    font: inherit;
    font-size: 12px;
    line-height: 1.7;
}
.legal-links a {
    color: inherit;
    text-decoration: underline;
    text-underline-offset: 4px;
    text-decoration-thickness: 1px;
    padding: 7px 0;
}
.legal-links a:hover {
    text-decoration-thickness: 2px;
}
.legal-links a:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 4px;
}
.legal-links a[aria-current='page'] {
    font-weight: 700;
}
</style>
