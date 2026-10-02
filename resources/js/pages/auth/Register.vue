<script setup lang="ts">
import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();

// Set by the referral middleware while an invitation is still waiting to be
// applied. Null when nobody sent this visitor, so nothing is shown.
const referrerName = computed(() => {
    const name = (page.props as Record<string, unknown>).referralReferrerName;

    return typeof name === 'string' && name.trim() !== '' ? name.trim() : null;
});
</script>

<template>
    <AuthBase
        title="Create an account"
        description="Enter your details below to create your account"
    >
        <Head title="Register" />

        <Form
            v-bind="RegisteredUserController.store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div
                v-if="referrerName"
                data-test="register-referrer-banner"
                class="rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-foreground"
            >
                Invited by <span class="font-medium">{{ referrerName }}</span>
            </div>

            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <!--
                    Only for someone whose link did not survive being pasted into
                    a message app. Optional, and ignored if it is not a real code.
                -->
                <div class="grid gap-2">
                    <Label for="referral_code">
                        Referral code
                        <span class="font-normal text-muted-foreground"
                            >(optional)</span
                        >
                    </Label>
                    <Input
                        id="referral_code"
                        type="text"
                        :tabindex="5"
                        name="referral_code"
                        placeholder="AHMAD-7K2QX"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        data-test="register-referral-code"
                    />
                    <p class="text-xs text-muted-foreground">
                        Have a friend's code? Enter it here.
                    </p>
                    <InputError :message="errors.referral_code" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="6"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="h-4 w-4 animate-spin"
                    />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="7"
                    >Log in</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
