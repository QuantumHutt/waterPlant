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
                    <div class="flex items-center gap-3">
                        <span class="flex size-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
                            <Droplets class="size-6" />
                        </span>
                        <div>
                            <p class="text-lg font-semibold leading-tight">Water Plant ERP</p>
                            <p class="text-sm text-white/70">Production · Sales · Accounting</p>
                        </div>
                    </div>
                </div>
                <div class="relative space-y-4">
                    <h2 class="text-3xl font-semibold leading-tight">Run your water filter plant on one platform.</h2>
                    <p class="text-white/80">Inventory, production batches, gallon tracking, POS, double-entry accounting and reports — branch ready.</p>
                </div>
                <p class="relative text-xs text-white/60">© {{ new Date().getFullYear() }} Water Plant ERP</p>
            </div>

            <!-- Form panel -->
            <div class="bg-card p-8 sm:p-12">
                <div class="mx-auto w-full max-w-sm">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-sidebar-gradient text-white">
                            <Droplets class="size-5" />
                        </span>
                        <p class="text-lg font-semibold">Water Plant ERP</p>
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
