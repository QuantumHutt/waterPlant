<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import Card from '@/Components/ui/Card.vue'
import { useCurrency } from '@/composables/useCurrency'
import {
    FlaskConical, ShoppingCart, TrendingUp, AlertTriangle, Container, PiggyBank, Wallet, Phone,
} from 'lucide-vue-next'

const props = defineProps({
    metrics: { type: Object, required: true },
    receivables: { type: Object, default: () => ({ rows: [], count: 0, total: 0, total_display: 'Rs 0.00' }) },
})

const { format } = useCurrency()

const cards = [
    { key: 'todays_sales', icon: ShoppingCart, tone: 'success' },
    { key: 'todays_production', icon: FlaskConical, tone: 'brand' },
    { key: 'monthly_revenue', icon: TrendingUp, tone: 'brand' },
    { key: 'active_bottles', icon: Container, tone: 'brand' },
    { key: 'security_deposits', icon: PiggyBank, tone: 'warning' },
    { key: 'low_stock_alerts', icon: AlertTriangle, tone: 'destructive' },
    { key: 'outstanding_receivables', icon: Wallet, tone: 'warning' },
]
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-semibold tracking-tight">Dashboard</h2>
                <p class="text-sm text-muted-foreground">Live overview — {{ new Date().toLocaleDateString() }}</p>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="c in cards"
                :key="c.key"
                :label="metrics[c.key].label"
                :value="metrics[c.key].display"
                :icon="c.icon"
                :tone="c.tone"
            />
        </div>

        <!-- Udhaar: customers who owe money -->
        <Card class="mt-4">
            <template #header>
                <div class="flex items-center gap-2">
                    <span class="flex items-center gap-1.5 rounded-full bg-warning/15 text-warning px-3 py-1 text-xs font-semibold">
                        <AlertTriangle class="size-4" /> {{ receivables.count }} customers owe you
                    </span>
                    <span class="text-sm font-semibold text-warning">{{ receivables.total_display }}</span>
                </div>
            </template>
            <div class="-mt-2">
                <h3 class="mb-3 text-base font-semibold">Udhaar — Paise Lene Hain</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-muted-foreground border-b border-border">
                            <th class="py-2 pr-4 font-medium">Customer</th>
                            <th class="py-2 pr-4 font-medium">Phone</th>
                            <th class="py-2 pr-4 font-medium">Overdue</th>
                            <th class="py-2 pr-4 font-medium text-right">Baqaya (Owes)</th>
                            <th class="py-2 pr-0 font-medium text-right">Action</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="c in receivables.rows" :key="c.id" class="border-b border-border/60">
                                <td class="py-2 pr-4 font-medium">
                                    <span class="flex items-center gap-2">
                                        <span class="size-2 rounded-full" :class="c.over_limit ? 'bg-destructive' : 'bg-warning'" />
                                        {{ c.name }}
                                        <span v-if="c.over_limit" class="rounded-full bg-destructive/10 text-destructive px-2 py-0.5 text-[10px] font-semibold">OVER LIMIT</span>
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-muted-foreground">
                                    <a v-if="c.phone" :href="`tel:${c.phone}`" class="inline-flex items-center gap-1 hover:text-brand"><Phone class="size-3.5" /> {{ c.phone }}</a>
                                    <span v-else>—</span>
                                </td>
                                <td class="py-2 pr-4">
                                    <span v-if="c.overdue_days > 0" class="rounded-full px-2 py-0.5 text-xs font-medium" :class="c.overdue ? 'bg-destructive/10 text-destructive' : 'bg-muted text-muted-foreground'">
                                        {{ c.overdue_days }}d{{ c.overdue ? ' overdue' : '' }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                                <td class="py-2 pr-4 text-right font-semibold text-warning">{{ c.display }}</td>
                                <td class="py-2 pr-0 text-right">
                                    <Link :href="route('customers.ledger', c.id)" class="rounded-full bg-brand/10 text-brand px-3 py-1 text-xs font-medium hover:bg-brand/20">View / Collect</Link>
                                </td>
                            </tr>
                            <tr v-if="!receivables.rows.length"><td colspan="5" class="py-6 text-center text-success">🎉 Sab clear — kisi ka udhaar baqaya nahi.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Card>

        <Card class="mt-4" title="Today at a glance">
            <ul class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <li class="flex items-center justify-between rounded-2xl bg-muted/50 px-4 py-3">
                    <span class="text-muted-foreground">Today's Sales</span>
                    <span class="font-semibold">{{ metrics.todays_sales.display }}</span>
                </li>
                <li class="flex items-center justify-between rounded-2xl bg-muted/50 px-4 py-3">
                    <span class="text-muted-foreground">Today's Production</span>
                    <span class="font-semibold">{{ metrics.todays_production.display }}</span>
                </li>
                <li class="flex items-center justify-between rounded-2xl bg-muted/50 px-4 py-3">
                    <span class="text-muted-foreground">Active Bottles with Customers</span>
                    <span class="font-semibold">{{ metrics.active_bottles.display }}</span>
                </li>
                <li class="flex items-center justify-between rounded-2xl bg-muted/50 px-4 py-3">
                    <span class="text-muted-foreground">Security Deposits Held</span>
                    <span class="font-semibold">{{ metrics.security_deposits.display }}</span>
                </li>
            </ul>
        </Card>
    </AppLayout>
</template>
