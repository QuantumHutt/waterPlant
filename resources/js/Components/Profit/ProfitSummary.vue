<script setup>
// Operational P&L for a period: sales → cost → gross profit → expenses → net profit.
import { useCurrency } from '@/composables/useCurrency'

defineProps({ pnl: { type: Object, required: true }, showCategories: { type: Boolean, default: true } })
const { format } = useCurrency()
</script>

<template>
    <div class="space-y-1.5 text-sm">
        <div class="flex justify-between"><span class="text-muted-foreground">Sales ({{ pnl.invoices }} invoices)</span><span class="font-medium">{{ format(pnl.revenue) }}</span></div>
        <div v-if="pnl.pending_revenue" class="flex justify-between text-xs text-warning"><span>· of which cost not set (refill / cooler)</span><span>{{ format(pnl.pending_revenue) }}</span></div>
        <div class="flex justify-between"><span class="text-muted-foreground">− Cost of goods sold</span><span>{{ format(pnl.cogs) }}</span></div>
        <div class="flex justify-between border-t border-border pt-1.5 font-semibold"><span>Gross Profit</span><span :class="pnl.gross_profit >= 0 ? 'text-success' : 'text-destructive'">{{ format(pnl.gross_profit) }}</span></div>
        <div class="flex justify-between"><span class="text-muted-foreground">− Expenses</span><span>{{ format(pnl.expenses) }}</span></div>
        <template v-if="showCategories">
            <div v-for="c in pnl.expenses_by_category" :key="c.category" class="flex justify-between pl-3 text-xs text-muted-foreground"><span>{{ c.category }} ({{ c.count }})</span><span>{{ format(c.amount) }}</span></div>
        </template>
        <div v-if="pnl.internal_use" class="flex justify-between"><span class="text-muted-foreground">− Internal / owner use (at cost)</span><span>{{ format(pnl.internal_use) }}</span></div>
        <div v-if="pnl.stock_loss" class="flex justify-between"><span class="text-muted-foreground">− Damaged / wasted stock</span><span>{{ format(pnl.stock_loss) }}</span></div>
        <div v-if="pnl.other_income" class="flex justify-between"><span class="text-muted-foreground">+ Other income</span><span>{{ format(pnl.other_income) }}</span></div>
        <div class="flex justify-between border-t border-border pt-1.5 text-base font-semibold">
            <span>Net Profit <span v-if="pnl.margin !== null" class="text-xs font-normal text-muted-foreground">({{ pnl.margin }}%)</span></span>
            <span :class="pnl.net_profit >= 0 ? 'text-success' : 'text-destructive'">{{ format(pnl.net_profit) }}</span>
        </div>
        <p v-if="pnl.pending_revenue" class="pt-1 text-xs text-warning">{{ format(pnl.pending_revenue) }} sales have no cost set yet, so they're not counted in profit. Set the cost on the Products page.</p>
    </div>
</template>
