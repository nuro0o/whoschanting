<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Check, Mail, Waves } from '@lucide/vue';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import RegistrySection from '@/components/account/RegistrySection.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { send } from '@/routes/verification';

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
    <Head title="Your resident record" />
    <article class="resident-record">
        <header class="resident-identity">
            <div class="resident-identity-copy">
                <p class="account-kicker">
                    The village register
                    <span aria-hidden="true">/</span> Resident record
                </p>
                <h1>{{ user.name }}</h1>
                <p class="resident-welcome">
                    A familiar name. A story all your own.
                </p>
                <div class="resident-inscription">
                    <div v-if="membershipDate">
                        <span>First crossing</span
                        ><strong>{{ membershipDate }}</strong>
                    </div>
                    <div class="resident-verification">
                        <span>Email status</span
                        ><strong
                            ><Check
                                v-if="user.email_verified_at"
                                :size="15"
                            /><Mail v-else :size="15" />{{
                                user.email_verified_at
                                    ? 'Verified resident'
                                    : 'Verification pending'
                            }}</strong
                        >
                    </div>
                </div>
            </div>
            <figure class="resident-harbor">
                <div class="resident-harbor-frame">
                    <img
                        src="/assets/chanting/village.png"
                        alt="Lamplit cottages overlook the village harbor beneath a strange moon."
                    />
                </div>
                <figcaption>
                    <Waves
                        :size="16"
                        :stroke-width="1.1"
                        aria-hidden="true"
                    /><span>A place in the village.</span
                    ><span aria-hidden="true">✦</span>
                </figcaption>
            </figure>
        </header>

        <RegistrySection
            number="I"
            title="Your particulars"
            description="The details held in the village register. Keep them true, even if your alibi isn’t."
        >
            <Form
                v-bind="ProfileController.update.form()"
                class="registry-profile-form"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="registry-field">
                    <div class="registry-field-label">
                        <Label for="name">Name</Label>
                        <p>How we know you.</p>
                    </div>
                    <div class="registry-field-control">
                        <Input
                            id="name"
                            name="name"
                            :default-value="user.name"
                            :disabled="processing"
                            required
                            autocomplete="name"
                            placeholder="Your name"
                            :aria-invalid="!!errors.name"
                            :aria-describedby="
                                errors.name ? 'name-error' : undefined
                            "
                        /><InputError id="name-error" :message="errors.name" />
                    </div>
                </div>
                <div class="registry-field">
                    <div class="registry-field-label">
                        <Label for="email">Email address</Label>
                        <p>Where our letters find you.</p>
                    </div>
                    <div class="registry-field-control">
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            :default-value="user.email"
                            :disabled="processing"
                            required
                            autocomplete="username"
                            placeholder="Your email address"
                            :aria-invalid="!!errors.email"
                            :aria-describedby="
                                errors.email ? 'email-error' : undefined
                            "
                        /><InputError
                            id="email-error"
                            :message="errors.email"
                        />
                    </div>
                </div>
                <div
                    v-if="page.props.mustVerifyEmail && !user.email_verified_at"
                    class="registry-verification-note"
                >
                    <Mail :size="18" aria-hidden="true" />
                    <div>
                        <p>Your email address is unverified.</p>
                        <Link :href="send()" as="button"
                            >Resend the verification email
                            <ArrowRight :size="14"
                        /></Link>
                        <p
                            v-if="
                                page.props.status === 'verification-link-sent'
                            "
                            class="account-save-status"
                            role="status"
                        >
                            A new verification link has been sent to your email
                            address.
                        </p>
                    </div>
                </div>
                <div class="registry-form-footer">
                    <div>
                        <p
                            v-if="recentlySuccessful"
                            class="account-save-status"
                            role="status"
                        >
                            <Check :size="15" /> Your record is up to date.
                        </p>
                        <p v-else class="registry-form-note">
                            Your account details. Your place here.
                        </p>
                    </div>
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="update-profile-button"
                        >{{ processing ? 'Saving…' : 'Save profile'
                        }}<ArrowRight :size="16"
                    /></Button>
                </div>
            </Form>
        </RegistrySection>
        <DeleteUser />
    </article>
</template>
