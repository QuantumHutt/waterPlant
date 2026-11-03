<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import {
    LayoutDashboard, Package, Boxes, FlaskConical, Container, ShoppingCart, Receipt,
    Users as UsersIcon, Truck, Calculator, Wallet, UserCog, Droplets, BarChart3,
    ShieldCheck, Settings, Building2, Home, Banknote, Menu, Moon, Sun, LogOut,
} from 'lucide-vue-next'
import { auth, hasRole, logout } from '../lib/auth'
import { BRANDS, brand, setBrand, isDark, toggleDark } from '../lib/theme'

const open = ref(false)
const menu = ref(false)
const router = useRouter()

const nav = computed(() => [
    { label: 'Dashboard', icon: LayoutDashboard, to: '/dashboard', role: 'manager' },
    { label: 'Sales / POS', icon: Receipt, to: '/sales', role: 'cashier' },
    { label: 'Customers', icon: UsersIcon, to: '/customers', role: 'cashier' },
    { label: 'Products', icon: Package, to: '/products', role: 'cashier' },
    { label: 'Expenses', icon: Wallet, to: '/expenses', role: 'manager' },
    // Modules not yet ported from the Laravel app
    { label: 'Inventory', icon: Boxes, soon: true },
    { label: 'Internal Use', icon: Home, soon: true },
    { label: 'Production', icon: FlaskConical, soon: true },
    { label: 'Gallons', icon: Container, soon: true },
    { label: 'Suppliers', icon: Building2, soon: true },
    { label: 'Purchases', icon: ShoppingCart, soon: true },
    { label: 'Delivery', icon: Truck, soon: true },
    { label: 'Accounting', icon: Calculator, soon: true },
    { label: 'Cash Book', icon: Banknote, soon: true },
    { label: 'Employees', icon: UserCog, soon: true },
    { label: 'Water Quality', icon: Droplets, soon: true },
    { label: 'Reports', icon: BarChart3, soon: true },
].filter((i) => i.soon || hasRole(i.role)))

const adminNav = computed(() => (hasRole('admin') ? [
    { label: 'Users', icon: ShieldCheck, to: '/users' },
    { label: 'Settings', icon: Settings, to: '/settings' },
] : []))

const initials = computed(() => (auth.user?.name || '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase())
const companyName = computed(() => auth.settings?.company?.name)

async function doLogout() {
    await logout()
    router.push('/login')
}
</script>

<template>
    <div class="min-h-screen bg-background">
        <div class="mx-auto px-2 py-3 sm:px-4 sm:py-6">
            <div class="overflow-hidden rounded-3xl bg-card shadow-sm ring-1 ring-border">
                <div v-if="open" class="no-print fixed inset-0 z-40 bg-black/50 lg:hidden" @click="open = false" />
                <div class="flex h-[95vh]">
                    <div
                        class="no-print fixed inset-y-0 left-0 z-50 transform transition-transform duration-300 lg:relative lg:z-auto"
                        :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                    >
                        <aside class="flex h-full w-72 flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground">
                            <div class="px-6 py-5">
                                <img src="/images/blueroot-logo-white.png" alt="BlueRoot" class="h-9 w-auto object-contain" />
                                <p class="mt-1 truncate text-xs text-white/70">{{ companyName }}</p>
                            </div>
                            <nav class="flex-1 overflow-y-auto px-3 pb-6">
                                <ul class="space-y-1">
                                    <li v-for="item in nav" :key="item.label">
                                        <span v-if="item.soon" class="flex cursor-default items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium text-sidebar-foreground/40">
                                            <component :is="item.icon" class="size-[18px]" />
                                            <span>{{ item.label }}</span>
                                            <span class="ml-auto text-[10px] uppercase tracking-wide">soon</span>
                                        </span>
                                        <RouterLink v-else :to="item.to" @click="open = false"
                                            class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition hover:bg-sidebar-accent"
                                            active-class="!bg-brand text-white shadow-sm">
                                            <component :is="item.icon" class="size-[18px]" />
                                            <span>{{ item.label }}</span>
                                        </RouterLink>
                                    </li>
                                </ul>
                                <template v-if="adminNav.length">
                                    <p class="px-3 pb-2 pt-6 text-xs font-semibold uppercase tracking-wider text-sidebar-foreground/50">Administration</p>
                                    <ul class="space-y-1">
                                        <li v-for="item in adminNav" :key="item.label">
                                            <RouterLink :to="item.to" @click="open = false"
                                                class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition hover:bg-sidebar-accent"
                                                active-class="!bg-brand text-white shadow-sm">
                                                <component :is="item.icon" class="size-[18px]" />
                                                <span>{{ item.label }}</span>
                                            </RouterLink>
                                        </li>
                                    </ul>
                                </template>
                            </nav>
                        </aside>
                    </div>

                    <main class="w-full flex-1 overflow-auto rounded-b-3xl bg-muted p-3 sm:p-5 md:px-7 md:py-6 lg:w-auto lg:rounded-r-3xl lg:rounded-bl-none">
                        <header class="no-print flex items-center justify-between gap-3 pb-5">
                            <div class="flex items-center gap-3">
                                <button type="button" @click="open = true" aria-label="Open menu"
                                    class="flex size-9 items-center justify-center rounded-full bg-card ring-1 ring-border transition hover:bg-muted lg:hidden">
                                    <Menu class="size-4" />
                                </button>
                                <div>
                                    <p class="text-sm text-muted-foreground">Welcome back,</p>
                                    <h1 class="text-lg font-semibold leading-tight">{{ auth.user?.name }}</h1>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="hidden items-center gap-1.5 sm:flex">
                                    <button v-for="b in BRANDS" :key="b.key" @click="setBrand(b.key)" :aria-label="`${b.key} theme`"
                                        class="size-5 rounded-full ring-offset-2 ring-offset-muted transition"
                                        :class="brand === b.key ? 'ring-2 ring-foreground/60' : ''" :style="{ background: b.color }" />
                                </div>
                                <button type="button" @click="toggleDark" :aria-label="isDark ? 'Light mode' : 'Dark mode'"
                                    class="flex size-9 items-center justify-center rounded-full bg-card ring-1 ring-border transition hover:bg-muted">
                                    <Sun v-if="isDark" class="size-4" /><Moon v-else class="size-4" />
                                </button>
                                <div class="relative">
                                    <button @click="menu = !menu" class="flex items-center gap-2 rounded-full bg-card py-1 pl-1 pr-3 ring-1 ring-border transition hover:bg-muted">
                                        <span class="flex size-7 items-center justify-center rounded-full bg-brand text-xs font-semibold text-white">{{ initials }}</span>
                                        <span class="hidden text-sm font-medium capitalize sm:block">{{ auth.user?.role }}</span>
                                    </button>
                                    <div v-if="menu" class="fixed inset-0 z-40" @click="menu = false" />
                                    <div v-if="menu" class="absolute right-0 z-50 mt-2 w-56 rounded-2xl bg-card py-1 shadow-lg ring-1 ring-border">
                                        <div class="truncate border-b border-border px-4 py-2 text-xs text-muted-foreground">{{ auth.user?.email }}</div>
                                        <button @click="doLogout" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm hover:bg-muted">
                                            <LogOut class="size-4" /> Log out
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </header>
                        <slot />
                    </main>
                </div>
            </div>
        </div>
    </div>
</template>
