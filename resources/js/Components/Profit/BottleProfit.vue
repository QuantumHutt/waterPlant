<script setup>
// Profit per bottle size (single + packs together), quantity in bottles.
import { computed } from 'vue'
import { useCurrency } from '@/composables/useCurrency'

const props = defineProps({ rows: { type: Array, required: true } })
const { format } = useCurrency()
const total = computed(() => ({
    revenue: props.rows.reduce((s, r) => s + r.revenue, 0),
    cogs: props.rows.reduce((s, r) => s + r.cogs, 0),
    profit: props.rows.reduce((s, r) => s + (r.gross_profit ?? 0), 0),
}))
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-muted-foreground border-b border-border">
                <th class="py-2 pr-4 font-medium">Bottle</th>
                <th class="py-2 pr-4 font-medium text-right">Qty Sold</th>
                <th class="py-2 pr-4 font-medium text-right">Sales</th>
                <th class="py-2 pr-4 font-medium text-right">Cost</th>
                <th class="py-2 pr-0 font-medium text-right">Profit</th>
            </tr></thead>
            <tbody>
                <tr v-for="r in rows" :key="r.label" class="border-b border-border/60">
                    <td class="py-2 pr-4 font-medium">{{ r.label }}</td>
                    <td class="py-2 pr-4 text-right">
                        <span class="font-semibold">{{ r.bottles }}</span>
                        <span v-if="r.packs" class="block text-[11px] text-muted-foreground">{{ r.singles }} single · {{ r.packs }} pack(s)</span>
                    </td>
                    <td class="py-2 pr-4 text-right">{{ format(r.revenue) }}</td>
                    <td class="py-2 pr-4 text-right">{{ r.gross_profit === null ? '—' : format(r.cogs) }}</td>
                    <td class="py-2 pr-0 text-right font-semibold">
                        <span v-if="r.gross_profit === null" class="text-xs font-medium text-warning">Cost not set</span>
                        <span v-else :class="r.gross_profit >= 0 ? 'text-success' : 'text-destructive'">{{ format(r.gross_profit) }}</span>
                    </td>
                </tr>
                <tr v-if="!rows.length"><td colspan="5" class="py-6 text-center text-muted-foreground">No sales.</td></tr>
            </tbody>
            <tfoot v-if="rows.length">
                <tr class="border-t border-border font-semibold">
                    <td class="py-2 pr-4" colspan="2">Total</td>
                    <td class="py-2 pr-4 text-right">{{ format(total.revenue) }}</td>
                    <td class="py-2 pr-4 text-right">{{ format(total.cogs) }}</td>
                    <td class="py-2 pr-0 text-right text-success">{{ format(total.profit) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
