<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Join the village.',
        description:
            'Make it official. Choose your character and gather your friends.',
    },
});
</script>

<template>
    <Head title="Create account" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Your name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    name="name"
                    placeholder="What should we call you?"
                    :aria-invalid="Boolean(errors.name)"
                    :aria-describedby="errors.name ? 'name-error' : undefined"
                />
                <InputError
                    id="name-error"
                    :message="errors.name"
                    role="alert"
                />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                    :aria-invalid="Boolean(errors.email)"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                />
                <InputError
                    id="email-error"
                    :message="errors.email"
                    role="alert"
                />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                    :aria-invalid="Boolean(errors.password)"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                />
                <InputError
                    id="password-error"
                    :message="errors.password"
                    role="alert"
                />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                    :aria-invalid="Boolean(errors.password_confirmation)"
                    :aria-describedby="
                        errors.password_confirmation
                            ? 'password-confirmation-error'
                            : undefined
                    "
                />
                <InputError
                    id="password-confirmation-error"
                    :message="errors.password_confirmation"
                    role="alert"
                />
            </div>

            <p class="auth-email-help">
                We’ll email you a verification link to activate your account.
            </p>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Creating your account…' : 'Create account' }}
            </Button>
        </div>

        <div class="auth-switch">
            Already have an account?
            <TextLink :href="login()" class="underline underline-offset-4"
                >Sign in</TextLink
            >
        </div>
    </Form>
</template>
