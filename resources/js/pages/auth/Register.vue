<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
const page = usePage();

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
            <div class="registration-terms">
                <input
                    type="hidden"
                    name="terms_version"
                    :value="page.props.legal?.version ?? ''"
                />
                <label for="register-terms"
                    ><input
                        id="register-terms"
                        type="checkbox"
                        name="terms"
                        value="1"
                        required
                        :disabled="processing"
                        :aria-invalid="Boolean(errors.terms)"
                        :aria-describedby="
                            errors.terms ? 'terms-error' : undefined
                        "
                    /><span
                        >I agree to the
                        <a href="/terms" target="_blank" rel="noopener"
                            >Terms of Service<span class="sr-only">
                                (opens in a new tab)</span
                            ></a
                        >.</span
                    ></label
                >
                <p>
                    Read our
                    <a href="/privacy" target="_blank" rel="noopener"
                        >Privacy Notice<span class="sr-only">
                            (opens in a new tab)</span
                        ></a
                    >
                    to understand how we use your information.
                </p>
                <InputError
                    id="terms-error"
                    :message="errors.terms || errors.terms_version"
                    role="alert"
                />
            </div>

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
<style scoped>
.registration-terms {
    font-size: 13px;
    line-height: 1.7;
}
.registration-terms label {
    display: flex;
    align-items: start;
    gap: 10px;
    cursor: pointer;
}
.registration-terms input[type='checkbox'] {
    margin-top: 4px;
    width: 17px;
    height: 17px;
    flex-shrink: 0;
    accent-color: var(--green);
}
.registration-terms > p {
    margin-top: 10px;
    color: var(--muted);
}
.registration-terms a {
    text-decoration: underline;
    text-underline-offset: 3px;
}
.registration-terms :is(input, a):focus-visible {
    outline: 2px solid var(--green);
    outline-offset: 3px;
}
</style>
