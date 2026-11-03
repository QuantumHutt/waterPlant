<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import Modal from '@/Components/Modal.vue'
import { usePermissions } from '@/composables/usePermissions'
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
    cashToday: { type: Object, default: null },
})

const { format } = useCurrency()
const bottleTab = ref('today')
const { can } = usePermissions()

// Deposit cash to bank from the dashboard: type the amount, see what stays in the drawer.
const showDeposit = ref(false)
const deposit = useForm({ direction: 'deposit', amount: null, note: '' })
// Two linked boxes: type in either — bank + cash always = what's in the drawer now.
const drawerNow = computed(() => Number(props.cashToday?.drawer ?? 0))
const keepCash = ref(null)
function fromBank(v) { deposit.amount = v; keepCash.value = v === null || v === '' ? null : Math.max(0, Math.round((drawerNow.value - Number(v)) * 100) / 100) }
function fromCash(v) { keepCash.value = v; deposit.amount = v === null || v === '' ? null : Math.max(0, Math.round((drawerNow.value - Number(v)) * 100) / 100) }
function submitDeposit() {
    deposit.post(route('cash.transfer'), { preserveScroll: true, preserveState: true, onSuccess: () => { deposit.reset(); showDeposit.value = false } })
}

// Receive udhaar straight from the list — amount can't exceed what they owe.
const collectFor = ref(null)
const collect = useForm({ amount: null, method: 'cash', note: '' })
function openCollect(c) { collectFor.value = c; collect.reset(); collect.clearErrors(); collect.amount = c.balance }
function submitCollect() {
    collect.post(route('customers.payment', collectFor.value.id), { preserveScroll: true, preserveState: true, onSuccess: () => (collectFor.value = null) })
}

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

        <!-- Cash today: drawer, bank, deposit -->
        <Card v-if="cashToday" class="mt-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap gap-6">
                    <div><p class="text-xs text-muted-foreground">Drawer mein hona chahiye (cash)</p><p class="text-2xl font-bold text-success">{{ format(cashToday.drawer) }}</p><p v-if="cashToday.since" class="text-[11px] text-muted-foreground">last deposit {{ cashToday.since }}</p></div>
                    <div><p class="text-xs text-muted-foreground">Bank balance</p><p class="text-2xl font-bold text-brand">{{ format(cashToday.bank) }}</p></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="can('cash.transfer') && cashToday.suggested > 0" type="button" @click="showDeposit = true; fromBank(String(cashToday.suggested))"
                        class="rounded-full bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                        Hisaab: {{ format(cashToday.suggested) }} bank mein, {{ format(cashToday.float) }} change →
                    </button>
                    <span v-else-if="can('cash.transfer') && cashToday.drawer > 0" class="self-center rounded-full bg-muted px-4 py-2 text-xs text-muted-foreground">
                        Drawer ({{ format(cashToday.drawer) }}) change ({{ format(cashToday.float) }}) se kam hai — abhi deposit nahi banta
                    </span>
                    <button v-if="can('cash.transfer')" type="button" @click="showDeposit = !showDeposit; deposit.amount = null; keepCash = null"
                        class="rounded-full bg-muted px-4 py-2.5 text-sm font-semibold hover:bg-muted/70">Other amount</button>
                    <Link v-if="can('cash.close')" :href="route('cash.index')" class="rounded-full bg-warning/15 px-5 py-2.5 text-sm font-semibold text-warning hover:bg-warning/25">
                        {{ cashToday.closed ? 'Day closed ✓' : 'Din close karein' }}
                    </Link>
                </div>
            </div>
            <div v-if="showDeposit" class="mt-4 rounded-2xl bg-brand/5 p-4 ring-1 ring-brand/20">
                <p class="mb-3 text-sm">Drawer mein abhi <b>{{ format(cashToday.drawer) }}</b> hai — isko baantein. Koi ek box likhein, doosra khud bharega.</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:max-w-lg">
                    <label class="block rounded-xl bg-card p-3 ring-2 ring-brand/40">
                        <span class="text-xs font-bold uppercase tracking-wide text-brand">🏦 Bank mein jama</span>
                        <input :value="deposit.amount" @input="fromBank($event.target.value)" type="number" min="0" :max="cashToday.drawer" placeholder="Bank mein kitne? e.g. 5000"
                            class="mt-1 w-full rounded-lg border-border bg-background text-xl font-bold focus:border-brand focus:ring-brand" />
                    </label>
                    <label class="block rounded-xl bg-card p-3 ring-2 ring-success/40">
                        <span class="text-xs font-bold uppercase tracking-wide text-success">💵 Cash mein (drawer)</span>
                        <input :value="keepCash" @input="fromCash($event.target.value)" type="number" min="0" :max="cashToday.drawer" placeholder="Drawer mein kitne rahenge? e.g. 500"
                            class="mt-1 w-full rounded-lg border-border bg-background text-xl font-bold focus:border-success focus:ring-success" />
                    </label>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <input v-model="deposit.note" placeholder="Bank slip no. (optional)" class="w-48 rounded-xl border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                    <button type="button" @click="submitDeposit" :disabled="deposit.processing || !(deposit.amount > 0)"
                        class="rounded-xl bg-brand px-6 py-2.5 text-sm font-bold text-white disabled:opacity-40">
                        {{ deposit.amount > 0 ? `${format(deposit.amount)} bank mein jama karein` : 'Bank mein jama karein' }}
                    </button>
                    <button type="button" @click="showDeposit = false" class="px-3 text-sm text-muted-foreground">Cancel</button>
                </div>
                <p v-if="deposit.errors.amount" class="mt-2 text-xs text-destructive">{{ deposit.errors.amount }}</p>
            </div>
        </Card>

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
            <!-- Sales → Gross profit → Expenses → Net profit, for today and this month -->
            <div v-for="p in [{ key: 'today', title: 'Today' }, { key: 'month', title: 'This Month' }]" :key="p.key" class="mt-4">
                <p class="mb-2 text-sm font-semibold text-muted-foreground">{{ p.title }}</p>
                <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                    <StatCard label="Sales" :value="format(profit[p.key].revenue)" :hint="`${profit[p.key].invoices} bills`" :icon="ShoppingCart" tone="brand" />
                    <StatCard label="Gross Profit" :value="format(profit[p.key].gross_profit)"
                        :hint="profit[p.key].pending_revenue > 0 ? `⚠ ${format(profit[p.key].pending_revenue)} sales have no cost set (refill) — set cost in Products` : `Sales − cost ${format(profit[p.key].cogs)}`"
                        :icon="TrendingUp" :tone="profit[p.key].pending_revenue > 0 ? 'warning' : 'success'" />
                    <StatCard label="Expenses" :value="format(profit[p.key].expenses + profit[p.key].internal_use + profit[p.key].stock_loss)" :hint="profit[p.key].expenses_by_category?.[0] ? `Biggest: ${profit[p.key].expenses_by_category[0].category}` : 'No expenses'" :icon="Wallet" tone="destructive" />
                    <StatCard label="Net Profit (after expenses)" :value="format(profit[p.key].net_profit)" :hint="profit[p.key].margin !== null ? `${profit[p.key].margin}% of sales` : 'Gross profit − expenses'" :icon="PiggyBank" :tone="profit[p.key].net_profit >= 0 ? 'success' : 'destructive'" />
                </div>
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
                                    <button v-if="can('customers.collect')" type="button" @click="openCollect(c)" class="mr-1 rounded-full bg-success px-3 py-1 text-xs font-semibold text-white hover:opacity-90">Collect</button>
                                    <Link :href="route('customers.ledger', c.id)" class="rounded-full bg-brand/10 text-brand px-3 py-1 text-xs font-medium hover:bg-brand/20">Ledger</Link>
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

        <!-- Receive udhaar -->
        <Modal :show="!!collectFor" @close="collectFor = null" max-width="md">
            <form v-if="collectFor" @submit.prevent="submitCollect" class="space-y-3 p-6">
                <h3 class="text-lg font-semibold">Receive Udhaar — {{ collectFor.name }}</h3>
                <p class="text-sm text-muted-foreground">Owes {{ collectFor.display }}. Oldest bills are settled first.</p>
                <div class="flex gap-2">
                    <input v-model.number="collect.amount" type="number" min="1" :max="collectFor.balance" class="flex-1 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                    <select v-model="collect.method" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                        <option value="cash">Cash</option><option value="bank">Bank / Easypaisa</option><option value="cheque">Cheque</option>
                    </select>
                </div>
                <button type="button" @click="collect.amount = collectFor.balance" class="text-xs font-semibold text-brand underline">Full {{ collectFor.display }}</button>
                <p v-if="collect.errors.amount" class="text-xs text-destructive">{{ collect.errors.amount }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="collectFor = null" class="rounded-full px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" :disabled="collect.processing || !(collect.amount > 0)" class="rounded-full bg-success px-5 py-2 text-sm font-semibold text-white disabled:opacity-40">Receive</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
