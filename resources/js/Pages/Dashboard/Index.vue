<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import Card from '@/Components/ui/Card.vue'
import {
    FlaskConical, ShoppingCart, TrendingUp, AlertTriangle,
    Truck, Wallet, PiggyBank,
} from 'lucide-vue-next'

const props = defineProps({
    metrics: { type: Object, required: true },
})

const cards = [
    { key: 'todays_production', icon: FlaskConical, tone: 'brand' },
    { key: 'todays_sales', icon: ShoppingCart, tone: 'success' },
    { key: 'monthly_revenue', icon: TrendingUp, tone: 'brand' },
    { key: 'profit_summary', icon: PiggyBank, tone: 'success' },
    { key: 'outstanding_receivables', icon: Wallet, tone: 'warning' },
    { key: 'low_stock_alerts', icon: AlertTriangle, tone: 'destructive' },
    { key: 'pending_deliveries', icon: Truck, tone: 'brand' },
]
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-semibold tracking-tight">Dashboard</h2>
                <p class="text-sm text-muted-foreground">Operational overview of your water plant.</p>
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

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2" title="Revenue Trend" subtitle="Wire up once Sales module lands">
                <div class="flex h-56 items-center justify-center rounded-2xl bg-muted text-sm text-muted-foreground">
                    Chart appears here once the Sales & Accounting modules are connected.
                </div>
            </Card>
            <Card title="Quick Status">
                <ul class="space-y-3 text-sm">
                    <li class="flex items-center justify-between">
                        <span class="text-muted-foreground">Today's Production</span>
                        <span class="font-medium">{{ metrics.todays_production.display }}</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-muted-foreground">Today's Sales</span>
                        <span class="font-medium">{{ metrics.todays_sales.short }}</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-muted-foreground">Receivables</span>
                        <span class="font-medium">{{ metrics.outstanding_receivables.short }}</span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span class="text-muted-foreground">Pending Deliveries</span>
                        <span class="font-medium">{{ metrics.pending_deliveries.display }}</span>
                    </li>
                </ul>
            </Card>
        </div>
    </AppLayout>
</template>
