<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import StatCard from '@/Components/StatCard.vue'
import { useCurrency } from '@/composables/useCurrency'
import { ShoppingCart, FlaskConical, Boxes, PiggyBank } from 'lucide-vue-next'

const props = defineProps({ filters: Object, sales: Object, production: Object, inventory: Object, bottles: Object, profitLoss: Object, topProducts: Array })
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
            <StatCard label="Net Profit" :value="format(profitLoss.net_profit)" hint="for period" :icon="PiggyBank" :tone="profitLoss.net_profit >= 0 ? 'success' : 'destructive'" />
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Bottles Issued</p><p class="text-xl font-semibold">{{ bottles.issued }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Returned</p><p class="text-xl font-semibold">{{ bottles.returned }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Active w/ Customers</p><p class="text-xl font-semibold text-brand">{{ bottles.active }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Missing</p><p class="text-xl font-semibold text-destructive">{{ bottles.missing }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Damaged</p><p class="text-xl font-semibold">{{ bottles.damaged }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Deposit Liability</p><p class="text-xl font-semibold">{{ format(bottles.deposit_balance) }}</p></div>
        </div>

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
