<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Check your inbox.',
        description:
            'Verify your email to finish joining the village. Then let the guessing begin.',
    },
});
defineProps<{ status?: string }>();
const page = usePage();
</script>

<template>
    <Head title="Verify your email" />
    <div
        v-if="status === 'verification-link-sent'"
        class="auth-status"
        role="status"
    >
        A fresh verification link is on its way. Check your inbox in a moment.
    </div>
    <div class="auth-verification-details">
        <Mail :size="25" class="auth-envelope" aria-hidden="true" />
        <p>Confirm the email address on your account:</p>
        <strong>{{ page.props.auth.user.email }}</strong>
        <p>
            Open the email and select <em>Verify my email</em> to activate your
            account.
        </p>
    </div>
    <Form
        v-bind="send.form()"
        class="auth-verification-actions"
        v-slot="{ errors, processing }"
    >
        <p class="auth-email-help">
            Nothing yet? Check your spam folder, or send a fresh link below.
            Once you’re verified, we’ll send a welcome with a few tips for your
            first game.
        </p>
        <Button type="submit" :disabled="processing">
            <Spinner v-if="processing" />
            {{
                processing ? 'Sending your link…' : 'Resend verification email'
            }}
        </Button>
        <InputError :message="errors.email" role="alert" />
        <p class="text-muted-foreground text-center text-sm">
            Wrong email?
            <TextLink href="/settings/profile">Update your address</TextLink>
        </p>
        <div class="auth-switch">
            <TextLink :href="logout()" as="button" class="auth-secondary-link">
                Log out and return to the village
            </TextLink>
            <p class="mt-2 text-sm">You can still play as a guest.</p>
        </div>
    </Form>
</template>
