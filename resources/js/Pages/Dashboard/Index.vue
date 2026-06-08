<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/StatCard.vue'
import Card from '@/Components/ui/Card.vue'
import {
    FlaskConical, ShoppingCart, TrendingUp, AlertTriangle, Container, PiggyBank, Wallet,
} from 'lucide-vue-next'

const props = defineProps({
    metrics: { type: Object, required: true },
})

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
