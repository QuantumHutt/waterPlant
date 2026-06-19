<script setup>
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Droplets } from 'lucide-vue-next'

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen w-full bg-background p-3 sm:p-6">
        <div class="mx-auto grid max-w-6xl overflow-hidden rounded-3xl shadow-xl ring-1 ring-border lg:grid-cols-2">
            <!-- Brand panel (purple + black) -->
            <div class="relative hidden flex-col justify-between bg-sidebar p-10 text-white lg:flex">
                <div class="absolute inset-0 bg-sidebar-gradient opacity-90"></div>
                <div class="relative">
                    <img src="/images/blueroot-logo-white.png" alt="BlueRoot" class="h-12 w-auto" />
                    <p class="mt-2 text-sm text-white/80">AL-Noor Water Plant &amp; Filter House</p>
                </div>
                <div class="relative space-y-4">
                    <h2 class="text-3xl font-semibold leading-tight">Run your water plant on one platform.</h2>
                    <p class="text-white/80">Inventory, production batches, gallon &amp; deposit tracking, POS, double-entry accounting and reports.</p>
                </div>
                <p class="relative text-xs text-white/60">© {{ new Date().getFullYear() }} BlueRoot — AL-Noor Water Plant &amp; Filter House</p>
            </div>

            <!-- Form panel -->
            <div class="bg-card p-8 sm:p-12">
                <div class="mx-auto w-full max-w-sm">
                    <div class="mb-8 lg:hidden">
                        <img src="/images/blueroot-logo.png" alt="BlueRoot" class="h-10 w-auto" />
                    </div>

                    <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Sign in to your account to continue.</p>

                    <div v-if="status" class="mt-4 rounded-2xl bg-success/10 px-4 py-2 text-sm font-medium text-success">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="mt-8 space-y-5">
                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                            <input
                                id="email"
                                type="email"
                                v-model="form.email"
                                required
                                autofocus
                                autocomplete="username"
                                class="block w-full rounded-xl border-border bg-background px-4 py-2.5 text-sm focus:border-brand focus:ring-brand"
                                placeholder="you@waterplant.test"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                            <input
                                id="password"
                                type="password"
                                v-model="form.password"
                                required
                                autocomplete="current-password"
                                class="block w-full rounded-xl border-border bg-background px-4 py-2.5 text-sm focus:border-brand focus:ring-brand"
                                placeholder="••••••••"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-sm text-muted-foreground">
                                <input type="checkbox" v-model="form.remember" class="rounded border-border text-brand focus:ring-brand" />
                                Remember me
                            </label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm font-medium text-brand hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Signing in…' : 'Sign in' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
