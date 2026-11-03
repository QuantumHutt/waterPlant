<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import Card from '@/Components/ui/Card.vue'
import { useCurrency } from '@/composables/useCurrency'
import ProfitSummary from '@/Components/Profit/ProfitSummary.vue'
import ProfitBreakdown from '@/Components/Profit/ProfitBreakdown.vue'
import BottleProfit from '@/Components/Profit/BottleProfit.vue'
import { ref } from 'vue'
import {
    FlaskConical, ShoppingCart, TrendingUp, AlertTriangle, Container, PiggyBank, Wallet, Phone,
} from 'lucide-vue-next'

const props = defineProps({
    metrics: { type: Object, required: true },
    receivables: { type: Object, default: () => ({ rows: [], count: 0, total: 0, total_display: 'Rs 0.00' }) },
    profit: { type: Object, default: null }, // null when the user can't see reports
    stockSales: { type: Array, default: () => [] },
})

const { format } = useCurrency()
const bottleTab = ref('today')

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

        <!-- Stock & Sales: what sold today / this month and what is left -->
        <Card class="mt-4" title="Stock &amp; Sales">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-2 pr-4 font-medium">Product</th>
                        <th class="py-2 pr-4 font-medium text-right">Sold Today</th>
                        <th class="py-2 pr-4 font-medium text-right">Sold This Month</th>
                        <th class="py-2 pr-0 font-medium text-right">In Stock Now</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="r in stockSales" :key="r.sku" class="border-b border-border/60">
                            <td class="py-2 pr-4 font-medium">{{ r.name }}</td>
                            <td class="py-2 pr-4 text-right">
                                <template v-if="r.is_bottle_stock">—</template>
                                <template v-else><span class="font-semibold">{{ r.today_qty }}</span> <span class="text-xs text-muted-foreground">{{ format(r.today_amount) }}</span></template>
                            </td>
                            <td class="py-2 pr-4 text-right">
                                <template v-if="r.is_bottle_stock">—</template>
                                <template v-else><span class="font-semibold">{{ r.month_qty }}</span> <span class="text-xs text-muted-foreground">{{ format(r.month_amount) }}</span></template>
                            </td>
                            <td class="py-2 pr-0 text-right">
                                <span v-if="r.unlimited" class="rounded-full bg-success/10 px-2 py-0.5 text-xs font-semibold text-success">Unlimited</span>
                                <span v-else class="font-semibold" :class="r.stock <= 0 ? 'text-destructive' : (r.stock <= 10 ? 'text-warning' : '')">
                                    {{ r.stock }} {{ r.is_pack ? 'packs' : (r.is_bottle_stock ? 'empty bottles in shop' : 'pcs') }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!stockSales.length"><td colspan="4" class="py-6 text-center text-muted-foreground">No products.</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">Packs are counted from single-bottle stock (e.g. 60 × 1.5L = 10 packs). 19L bottles with customers: {{ metrics.active_bottles.display }}.</p>
        </Card>

        <!-- Profit: today + this month (sales − cost − expenses) -->
        <template v-if="profit">
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Today's Profit" :value="format(profit.today.net_profit)" :hint="`Sales ${format(profit.today.revenue)} · Expenses ${format(profit.today.expenses)}`" :icon="TrendingUp" :tone="profit.today.net_profit >= 0 ? 'success' : 'destructive'" />
                <StatCard label="This Month's Profit" :value="format(profit.month.net_profit)" :hint="`Sales ${format(profit.month.revenue)} · Expenses ${format(profit.month.expenses)}`" :icon="PiggyBank" :tone="profit.month.net_profit >= 0 ? 'success' : 'destructive'" />
            </div>
            <Card class="mt-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold">Profit by Bottle</h3>
                    <div class="flex rounded-full bg-muted p-1 text-xs font-semibold">
                        <button type="button" @click="bottleTab = 'today'" class="rounded-full px-3 py-1" :class="bottleTab === 'today' ? 'bg-card shadow' : 'text-muted-foreground'">Today</button>
                        <button type="button" @click="bottleTab = 'month'" class="rounded-full px-3 py-1" :class="bottleTab === 'month' ? 'bg-card shadow' : 'text-muted-foreground'">This Month</button>
                    </div>
                </div>
                <BottleProfit :rows="bottleTab === 'today' ? profit.bottles_today : profit.bottles_month" />
            </Card>
            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card title="This Month — Profit &amp; Loss"><ProfitSummary :pnl="profit.month" /></Card>
                <Card class="lg:col-span-2" title="Daily Profit — this month"><ProfitBreakdown :rows="profit.daily" label="Date" /></Card>
            </div>
        </template>

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
