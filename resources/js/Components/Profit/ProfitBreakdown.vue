<script setup>
// Day-by-day or month-by-month profit table.
import { useCurrency } from '@/composables/useCurrency'

defineProps({ rows: { type: Array, required: true }, label: { type: String, default: 'Date' } })
const { format } = useCurrency()
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-muted-foreground border-b border-border">
                <th class="py-2 pr-4 font-medium">{{ label }}</th>
                <th class="py-2 pr-4 font-medium text-right">Sales</th>
                <th class="py-2 pr-4 font-medium text-right">Cost</th>
                <th class="py-2 pr-4 font-medium text-right">Gross Profit</th>
                <th class="py-2 pr-4 font-medium text-right">Expenses</th>
                <th class="py-2 pr-0 font-medium text-right">Net Profit</th>
            </tr></thead>
            <tbody>
                <tr v-for="r in rows" :key="r.period" class="border-b border-border/60">
                    <td class="py-2 pr-4 whitespace-nowrap">{{ r.period }}</td>
                    <td class="py-2 pr-4 text-right">{{ format(r.revenue) }}<span v-if="r.pending_revenue" class="block text-[11px] text-warning">{{ format(r.pending_revenue) }} no cost</span></td>
                    <td class="py-2 pr-4 text-right">{{ format(r.cogs) }}</td>
                    <td class="py-2 pr-4 text-right">{{ format(r.gross_profit) }}</td>
                    <td class="py-2 pr-4 text-right">{{ format(r.expenses + r.internal_use + r.stock_loss - r.other_income) }}</td>
                    <td class="py-2 pr-0 text-right font-semibold" :class="r.net_profit >= 0 ? 'text-success' : 'text-destructive'">{{ format(r.net_profit) }}</td>
                </tr>
                <tr v-if="!rows.length"><td colspan="6" class="py-6 text-center text-muted-foreground">No sales or expenses yet.</td></tr>
            </tbody>
        </table>
    </div>
</template>
