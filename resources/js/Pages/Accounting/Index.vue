<script setup>
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import { useCurrency } from '@/composables/useCurrency'
import { CheckCircle2, AlertCircle } from 'lucide-vue-next'

const props = defineProps({ accounts: Array, journals: Array, trialBalance: Object, profitLoss: Object, balanceSheet: Object, filters: Object })
const { format } = useCurrency()

const tabs = ['Trial Balance', 'Profit & Loss', 'Balance Sheet', 'Chart of Accounts', 'Journals']
const tab = ref('Trial Balance')
</script>

<template>
    <Head title="Accounting" />
    <AppLayout>
        <template #header>
            <div><h2 class="text-2xl font-semibold tracking-tight">Accounting</h2><p class="text-sm text-muted-foreground">Double-entry ledger &amp; financial statements.</p></div>
        </template>

        <div class="mb-4 flex flex-wrap gap-2">
            <button v-for="t in tabs" :key="t" @click="tab = t" class="rounded-full px-4 py-2 text-sm font-medium transition"
                :class="tab === t ? 'bg-brand text-white' : 'bg-card ring-1 ring-border hover:bg-muted'">{{ t }}</button>
        </div>

        <!-- Trial Balance -->
        <Card v-if="tab === 'Trial Balance'">
            <template #header>
                <span class="inline-flex items-center gap-1.5 text-sm font-medium" :class="trialBalance.balanced ? 'text-success' : 'text-destructive'">
                    <component :is="trialBalance.balanced ? CheckCircle2 : AlertCircle" class="size-4" />
                    {{ trialBalance.balanced ? 'Balanced' : 'NOT balanced' }}
                </span>
            </template>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-muted-foreground border-b border-border"><th class="py-2 pr-4 font-medium">Code</th><th class="py-2 pr-4 font-medium">Account</th><th class="py-2 pr-4 font-medium text-right">Debit</th><th class="py-2 pr-0 font-medium text-right">Credit</th></tr></thead>
                <tbody>
                    <tr v-for="r in trialBalance.rows" :key="r.code" class="border-b border-border/60">
                        <td class="py-2 pr-4 font-mono text-xs">{{ r.code }}</td><td class="py-2 pr-4">{{ r.name }}</td>
                        <td class="py-2 pr-4 text-right">{{ r.debit ? format(r.debit) : '—' }}</td><td class="py-2 pr-0 text-right">{{ r.credit ? format(r.credit) : '—' }}</td>
                    </tr>
                    <tr v-if="!trialBalance.rows.length"><td colspan="4" class="py-6 text-center text-muted-foreground">No journal activity yet.</td></tr>
                </tbody>
                <tfoot v-if="trialBalance.rows.length"><tr class="font-semibold border-t-2 border-border"><td class="py-2 pr-4" colspan="2">Total</td><td class="py-2 pr-4 text-right">{{ format(trialBalance.total_debit) }}</td><td class="py-2 pr-0 text-right">{{ format(trialBalance.total_credit) }}</td></tr></tfoot>
            </table>
        </Card>

        <!-- P&L -->
        <Card v-else-if="tab === 'Profit & Loss'">
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <h4 class="font-semibold text-success mb-2">Income</h4>
                    <div v-for="i in profitLoss.income" :key="i.code" class="flex justify-between text-sm py-1"><span>{{ i.name }}</span><span>{{ format(i.amount) }}</span></div>
                    <div class="flex justify-between font-medium border-t border-border mt-1 pt-1"><span>Total Income</span><span>{{ format(profitLoss.income_total) }}</span></div>
                </div>
                <div>
                    <h4 class="font-semibold text-destructive mb-2">Expenses</h4>
                    <div v-for="e in profitLoss.expense" :key="e.code" class="flex justify-between text-sm py-1"><span>{{ e.name }}</span><span>{{ format(e.amount) }}</span></div>
                    <div class="flex justify-between font-medium border-t border-border mt-1 pt-1"><span>Total Expenses</span><span>{{ format(profitLoss.expense_total) }}</span></div>
                </div>
            </div>
            <div class="mt-4 flex justify-between rounded-2xl bg-muted px-4 py-3 text-base font-semibold">
                <span>Net Profit</span><span :class="profitLoss.net_profit >= 0 ? 'text-success' : 'text-destructive'">{{ format(profitLoss.net_profit) }}</span>
            </div>
        </Card>

        <!-- Balance Sheet -->
        <Card v-else-if="tab === 'Balance Sheet'">
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <h4 class="font-semibold mb-2">Assets</h4>
                    <div v-for="a in balanceSheet.assets" :key="a.code" class="flex justify-between text-sm py-1"><span>{{ a.name }}</span><span>{{ format(a.amount) }}</span></div>
                    <div class="flex justify-between font-semibold border-t border-border mt-1 pt-1"><span>Total Assets</span><span>{{ format(balanceSheet.asset_total) }}</span></div>
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Liabilities &amp; Equity</h4>
                    <div v-for="l in balanceSheet.liabilities" :key="l.code" class="flex justify-between text-sm py-1"><span>{{ l.name }}</span><span>{{ format(l.amount) }}</span></div>
                    <div v-for="e in balanceSheet.equity" :key="e.code" class="flex justify-between text-sm py-1"><span>{{ e.name }}</span><span>{{ format(e.amount) }}</span></div>
                    <div class="flex justify-between text-sm py-1"><span>Retained Earnings</span><span>{{ format(balanceSheet.retained_earnings) }}</span></div>
                    <div class="flex justify-between font-semibold border-t border-border mt-1 pt-1"><span>Total L&amp;E</span><span>{{ format(balanceSheet.liability_total + balanceSheet.equity_total) }}</span></div>
                </div>
            </div>
        </Card>

        <!-- Chart of Accounts -->
        <Card v-else-if="tab === 'Chart of Accounts'">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-muted-foreground border-b border-border"><th class="py-2 pr-4 font-medium">Code</th><th class="py-2 pr-4 font-medium">Name</th><th class="py-2 pr-0 font-medium">Type</th></tr></thead>
                <tbody>
                    <tr v-for="a in accounts" :key="a.id" class="border-b border-border/60"><td class="py-2 pr-4 font-mono text-xs">{{ a.code }}</td><td class="py-2 pr-4">{{ a.name }}</td><td class="py-2 pr-0 capitalize">{{ a.type }}</td></tr>
                </tbody>
            </table>
        </Card>

        <!-- Journals -->
        <Card v-else>
            <div v-for="j in journals" :key="j.id" class="mb-3 rounded-2xl ring-1 ring-border p-3">
                <div class="flex justify-between text-sm"><span class="font-mono text-xs text-muted-foreground">{{ j.entry_number }}</span><span>{{ j.entry_date }}</span></div>
                <p class="text-sm font-medium">{{ j.narration }}</p>
                <table class="mt-2 w-full text-xs">
                    <tr v-for="l in j.lines" :key="l.id"><td class="py-0.5">{{ l.account?.name }}</td><td class="py-0.5 text-right">{{ l.debit > 0 ? format(l.debit) : '' }}</td><td class="py-0.5 text-right">{{ l.credit > 0 ? format(l.credit) : '' }}</td></tr>
                </table>
            </div>
            <p v-if="!journals.length" class="py-6 text-center text-muted-foreground">No journal entries yet.</p>
        </Card>
    </AppLayout>
</template>
