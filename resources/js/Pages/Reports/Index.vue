<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import StatCard from '@/Components/StatCard.vue'
import { useCurrency } from '@/composables/useCurrency'
import ProfitSummary from '@/Components/Profit/ProfitSummary.vue'
import ProfitBreakdown from '@/Components/Profit/ProfitBreakdown.vue'
import BottleProfit from '@/Components/Profit/BottleProfit.vue'
import { ShoppingCart, FlaskConical, Boxes, PiggyBank } from 'lucide-vue-next'

const props = defineProps({ filters: Object, sales: Object, production: Object, inventory: Object, bottles: Object, profitLoss: Object, grossProfit: Object, byBottle: Array, purchases: Object, udhaar_collected: Number, customer_outstanding: Number, pnl: Object, daily: Array, monthly: Array, topProducts: Array })
const { format } = useCurrency()

const from = ref(props.filters.from)
const to = ref(props.filters.to)
function apply() { router.get(route('reports.index'), { from: from.value, to: to.value }, { preserveState: true }) }
</script>

<template>
    <Head title="Reports" />
    <AppLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 class="text-2xl font-semibold tracking-tight">Reports</h2><p class="text-sm text-muted-foreground">Sales, production, inventory &amp; profit.</p></div>
                <div class="flex items-end gap-2">
                    <div><label class="block text-xs text-muted-foreground">From</label><input v-model="from" type="date" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" /></div>
                    <div><label class="block text-xs text-muted-foreground">To</label><input v-model="to" type="date" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" /></div>
                    <button @click="apply" class="rounded-full bg-brand text-white px-4 py-2 text-sm font-medium">Apply</button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Sales" :value="format(sales.total)" :hint="`${sales.count} invoices`" :icon="ShoppingCart" tone="brand" />
            <StatCard label="Water Produced (L)" :value="String(production.water_litres)" :hint="`${production.batches} batches`" :icon="FlaskConical" tone="success" />
            <StatCard label="Inventory Value" :value="format(inventory.value)" :hint="`${inventory.low_stock} low stock`" :icon="Boxes" tone="warning" />
            <StatCard label="Net Profit" :value="format(pnl.net_profit)" :hint="`Gross ${format(pnl.gross_profit)} − Expenses ${format(pnl.expenses)}`" :icon="PiggyBank" :tone="pnl.net_profit >= 0 ? 'success' : 'destructive'" />
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Bottles Issued</p><p class="text-xl font-semibold">{{ bottles.issued }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Returned</p><p class="text-xl font-semibold">{{ bottles.returned }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Active w/ Customers</p><p class="text-xl font-semibold text-brand">{{ bottles.active }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Missing</p><p class="text-xl font-semibold text-destructive">{{ bottles.missing }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Damaged</p><p class="text-xl font-semibold">{{ bottles.damaged }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Deposit Liability</p><p class="text-xl font-semibold">{{ format(bottles.deposit_balance) }}</p></div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <Card title="Profit &amp; Loss (selected dates)"><ProfitSummary :pnl="pnl" /></Card>
            <Card class="lg:col-span-2" title="Daily Profit"><ProfitBreakdown :rows="daily" label="Date" /></Card>
        </div>

        <Card class="mt-4" title="Profit by Bottle (selected dates)"><BottleProfit :rows="byBottle" /></Card>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Card title="Purchases &amp; Suppliers">
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-muted-foreground">Purchases received ({{ purchases.count }})</span><span class="font-medium">{{ format(purchases.total) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted-foreground">Paid to suppliers</span><span>{{ format(purchases.paid) }}</span></div>
                    <div class="flex justify-between border-t border-border pt-1.5 font-semibold"><span>Still owed to suppliers (all time)</span><span class="text-warning">{{ format(purchases.supplier_outstanding) }}</span></div>
                    <div v-for="s in purchases.by_supplier" :key="s.name" class="flex justify-between pl-3 text-xs text-muted-foreground"><span>{{ s.name }}</span><span>{{ format(s.current_balance) }}</span></div>
                    <p class="pt-1 text-xs text-muted-foreground">Purchases are stock, not an expense — their cost is counted in profit when the goods sell.</p>
                </div>
            </Card>
            <Card title="Udhaar (credit)">
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-muted-foreground">New udhaar given in period</span><span>{{ format(sales.outstanding) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted-foreground">Udhaar collected in period</span><span class="text-success">{{ format(udhaar_collected) }}</span></div>
                    <div class="flex justify-between border-t border-border pt-1.5 font-semibold"><span>Customers still owe (all time)</span><span class="text-warning">{{ format(customer_outstanding) }}</span></div>
                </div>
            </Card>
        </div>

        <Card class="mt-4" title="Monthly Profit (last 12 months)"><ProfitBreakdown :rows="monthly" label="Month" /></Card>

        <Card class="mt-4" title="Product-wise Gross Profit">
            <p class="mb-3 text-xs text-muted-foreground">Uses the cost recorded at the time of each sale. Shop = walk-in / retail price · Market = shop customers.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border"><th class="py-2 pr-4 font-medium">Product</th><th class="py-2 pr-4 font-medium">Channel</th><th class="py-2 pr-4 font-medium text-right">Qty</th><th class="py-2 pr-4 font-medium text-right">Revenue</th><th class="py-2 pr-4 font-medium text-right">Cost</th><th class="py-2 pr-4 font-medium text-right">Gross Profit</th><th class="py-2 pr-0 font-medium text-right">Per Unit</th></tr></thead>
                    <tbody>
                        <tr v-for="r in grossProfit.rows" :key="r.sku + r.channel" class="border-b border-border/60">
                            <td class="py-2 pr-4">{{ r.name }}</td>
                            <td class="py-2 pr-4 capitalize">{{ r.channel }}</td>
                            <td class="py-2 pr-4 text-right">{{ r.qty }}</td>
                            <td class="py-2 pr-4 text-right">{{ format(r.revenue) }}</td>
                            <td class="py-2 pr-4 text-right">{{ format(r.cogs) }}</td>
                            <td class="py-2 pr-4 text-right">
                                <span v-if="r.gross_profit === null" class="text-xs font-medium text-warning">Pending — cost not configured</span>
                                <template v-else>
                                    <span :class="r.gross_profit >= 0 ? 'text-success' : 'text-destructive'">{{ format(r.gross_profit) }}</span>
                                    <p v-if="r.pending_qty" class="text-[11px] text-warning">+ {{ r.pending_qty }} unit(s) pending cost</p>
                                </template>
                            </td>
                            <td class="py-2 pr-0 text-right">{{ r.profit_per_unit === null ? '—' : format(r.profit_per_unit) }}</td>
                        </tr>
                        <tr v-if="!grossProfit.rows.length"><td colspan="7" class="py-6 text-center text-muted-foreground">No sales.</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-border font-semibold"><td class="py-2" colspan="3">Total</td><td class="py-2 pr-4 text-right">{{ format(grossProfit.revenue) }}</td><td class="py-2 pr-4 text-right">{{ format(grossProfit.cogs) }}</td><td class="py-2 pr-4 text-right">{{ format(grossProfit.gross_profit) }}</td><td></td></tr>
                        <tr v-if="grossProfit.pending_revenue"><td class="py-2 text-warning" colspan="7">{{ format(grossProfit.pending_revenue) }} of revenue has no configured cost (e.g. 19L refill) and is excluded from gross profit.</td></tr>
                    </tfoot>
                </table>
            </div>
        </Card>

        <Card class="mt-4" title="Customer Bottle &amp; Deposit Ledger">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-muted-foreground border-b border-border"><th class="py-2 pr-4 font-medium">Customer</th><th class="py-2 pr-4 font-medium text-right">Active Bottles</th><th class="py-2 pr-4 font-medium text-right">Missing</th><th class="py-2 pr-0 font-medium text-right">Deposit Held</th></tr></thead>
                <tbody>
                    <tr v-for="b in bottles.ledger" :key="b.name" class="border-b border-border/60"><td class="py-2 pr-4">{{ b.name }}</td><td class="py-2 pr-4 text-right">{{ b.held_qty }}</td><td class="py-2 pr-4 text-right">{{ b.missing_qty }}</td><td class="py-2 pr-0 text-right">{{ format(b.deposit_held) }}</td></tr>
                    <tr v-if="!bottles.ledger.length"><td colspan="4" class="py-6 text-center text-muted-foreground">No bottles issued.</td></tr>
                </tbody>
            </table>
        </Card>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Card title="Sales by Type">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border"><th class="py-2 pr-4 font-medium">Type</th><th class="py-2 pr-4 font-medium text-right">Count</th><th class="py-2 pr-0 font-medium text-right">Total</th></tr></thead>
                    <tbody>
                        <tr v-for="r in sales.by_type" :key="r.type" class="border-b border-border/60"><td class="py-2 pr-4 capitalize">{{ r.type }}</td><td class="py-2 pr-4 text-right">{{ r.count }}</td><td class="py-2 pr-0 text-right">{{ format(r.total) }}</td></tr>
                        <tr v-if="!sales.by_type.length"><td colspan="3" class="py-6 text-center text-muted-foreground">No sales.</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-border"><td class="py-2 text-muted-foreground">Received / Outstanding</td><td></td><td class="py-2 text-right">{{ format(sales.received) }} / {{ format(sales.outstanding) }}</td></tr>
                        <tr v-if="sales.returns_count"><td class="py-2 text-destructive">Returns ({{ sales.returns_count }})</td><td></td><td class="py-2 text-right text-destructive">- {{ format(sales.returns_total) }}</td></tr>
                    </tfoot>
                </table>
            </Card>

            <Card title="Top Products">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border"><th class="py-2 pr-4 font-medium">Product</th><th class="py-2 pr-4 font-medium text-right">Qty</th><th class="py-2 pr-0 font-medium text-right">Total</th></tr></thead>
                    <tbody>
                        <tr v-for="p in topProducts" :key="p.name" class="border-b border-border/60"><td class="py-2 pr-4">{{ p.name }}</td><td class="py-2 pr-4 text-right">{{ p.qty }}</td><td class="py-2 pr-0 text-right">{{ format(p.total) }}</td></tr>
                        <tr v-if="!topProducts.length"><td colspan="3" class="py-6 text-center text-muted-foreground">No data.</td></tr>
                    </tbody>
                </table>
            </Card>
        </div>
    </AppLayout>
</template>
