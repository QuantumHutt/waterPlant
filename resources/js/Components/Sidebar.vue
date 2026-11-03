<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { usePermissions } from '@/composables/usePermissions'
import BlueRootLogo from '@/Components/BlueRootLogo.vue'
import { computed } from 'vue'
import {
    LayoutDashboard, Package, Boxes, FlaskConical, Container, ShoppingCart,
    Receipt, Users as UsersIcon, Truck, Calculator, Wallet, UserCog,
    Droplets, BarChart3, ShieldCheck, Settings, Building2, Home,
} from 'lucide-vue-next'

defineProps({ onClose: { type: Function, default: () => {} } })

const { can, canAny, hasRole } = usePermissions()
const page = usePage()

// route() comes from Ziggy (global). Helper that tolerates unbuilt routes.
const has = (name) => {
    try { return typeof route === 'function' && route().has(name) } catch (e) { return false }
}
const to = (name) => (has(name) ? route(name) : '#')
const isCurrent = (name) => {
    try { return has(name) && route().current(name) } catch (e) { return false }
}

const nav = computed(() => [
    { label: 'Dashboard', icon: LayoutDashboard, route: 'dashboard', show: !hasRole('salesman') },
    { label: 'Products', icon: Package, route: 'products.index', show: can('products.view') },
    { label: 'Inventory', icon: Boxes, route: 'inventory.index', show: can('inventory.view') },
    { label: 'Internal Use', icon: Home, route: 'consumption.index', show: can('consumption.view') },
    { label: 'Production', icon: FlaskConical, route: 'production.index', show: can('production.view') },
    { label: 'Gallons', icon: Container, route: 'gallons.index', show: can('gallons.view') },
    { label: 'Suppliers', icon: Building2, route: 'suppliers.index', show: can('suppliers.view') },
    { label: 'Purchases', icon: ShoppingCart, route: 'purchases.index', show: can('purchases.view') },
    { label: 'Sales / POS', icon: Receipt, route: 'sales.index', show: can('sales.view') },
    { label: 'Customers', icon: UsersIcon, route: 'customers.index', show: can('customers.view') },
    { label: 'Delivery', icon: Truck, route: 'delivery.index', show: can('delivery.view') },
    { label: 'Accounting', icon: Calculator, route: 'accounting.index', show: can('accounting.view') },
    { label: 'Expenses', icon: Wallet, route: 'expenses.index', show: can('expenses.view') },
    { label: 'Employees', icon: UserCog, route: 'employees.index', show: can('employees.view') },
    { label: 'Water Quality', icon: Droplets, route: 'quality.index', show: can('quality.view') },
    { label: 'Reports', icon: BarChart3, route: 'reports.index', show: can('reports.view') },
])

const adminNav = computed(() => [
    { label: 'Users', icon: ShieldCheck, route: 'users.index', show: can('users.view') },
    { label: 'Settings', icon: Settings, route: 'settings.index', show: can('settings.view') },
])
</script>

<template>
    <aside class="flex h-full w-72 flex-col bg-sidebar text-sidebar-foreground border-r border-sidebar-border">
        <!-- Brand -->
        <div class="px-6 py-5">
            <BlueRootLogo :invert="true" size="md" />
        </div>

        <nav class="flex-1 overflow-y-auto px-3 pb-6">
            <ul class="space-y-1">
                <li v-for="item in nav.filter((i) => i.show)" :key="item.label">
                    <Link
                        :href="to(item.route)"
                        @click="onClose"
                        class="
                        
                        
                        
                        group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                        :class="isCurrent(item.route)
                            ? 'bg-brand text-white shadow-sm'
                            : (has(item.route) ? 'text-sidebar-foreground hover:bg-sidebar-accent' : 'text-sidebar-foreground/40 cursor-default')"
                    >
                        <component :is="item.icon" class="size-[18px]" />
                        <span>{{ item.label }}</span>
                        <span v-if="!has(item.route)" class="ml-auto text-[10px] uppercase tracking-wide text-sidebar-foreground/40">soon</span>
                    </Link>
                </li>
            </ul>

            <template v-if="adminNav.some((i) => i.show)">
                <p class="px-3 pt-6 pb-2 text-xs font-semibold uppercase tracking-wider text-sidebar-foreground/50">Administration</p>
                <ul class="space-y-1">
                    
                    <li v-for="item in adminNav.filter((i) => i.show)" :key="item.label">
                        <Link
                            :href="to(item.route)"
                            @click="onClose"
                            class="group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                            :class="isCurrent(item.route) ? 'bg-brand text-white shadow-sm' : 'hover:bg-sidebar-accent'"
                        >
                            <component :is="item.icon" class="size-[18px]" />
                            <span>{{ item.label }}</span>
                        </Link>
                    </li>
                </ul>
            </template>
        </nav>
    </aside>
</template>
