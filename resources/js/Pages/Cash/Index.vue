<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { Banknote, Landmark, ArrowRightLeft, Lock } from 'lucide-vue-next'

const props = defineProps({ date: String, day: Object, closings: Array })
const { can } = usePermissions()
const { format } = useCurrency()

const date = ref(props.date)
function go() { router.get(route('cash.index'), { date: date.value }, { preserveState: true }) }

// Cash → bank deposit (e.g. 1000 sale: 800 to bank, 200 stays in drawer).
const move = useForm({ direction: 'deposit', amount: null, date: props.date, note: '' })
function submitMove() {
    move.date = props.date
    move.post(route('cash.transfer'), { preserveScroll: true, onSuccess: () => move.reset('amount', 'note') })
}

// End-of-day drawer count.
const close = useForm({ date: props.date, counted_cash: props.day.closing?.counted_cash ?? null, note: props.day.closing?.note ?? '' })
const diff = computed(() => (close.counted_cash === null || close.counted_cash === '' ? null : Number(close.counted_cash) - props.day.cash.closing))
function submitClose() { close.date = props.date; close.post(route('cash.close'), { preserveScroll: true }) }

const accounts = [
    { key: 'cash', title: 'Cash Drawer', icon: Banknote, tone: 'text-success' },
    { key: 'bank', title: 'Bank', icon: Landmark, tone: 'text-brand' },
]
</script>

<template>
    <Head title="Cash Book" />
    <AppLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 class="text-2xl font-semibold tracking-tight">Cash Book</h2><p class="text-sm text-muted-foreground">How much should be in the drawer and the bank — and close the day.</p></div>
                <div class="flex items-end gap-2">
                    <input v-model="date" type="date" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                    <button @click="go" class="rounded-full bg-brand px-4 py-2 text-sm font-medium text-white">Show</button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Card v-for="a in accounts" :key="a.key">
                <div class="mb-3 flex items-center gap-2"><component :is="a.icon" class="size-5" :class="a.tone" /><h3 class="text-base font-semibold">{{ a.title }}</h3></div>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><span class="text-muted-foreground">Opening (start of day)</span><span class="font-medium">{{ format(day[a.key].opening) }}</span></div>
                    <div v-for="l in day[a.key].lines" :key="l.kind + l.direction" class="flex justify-between">
                        <span :class="l.direction === 'in' ? 'text-success' : 'text-destructive'">{{ l.direction === 'in' ? '+' : '−' }} {{ l.label }}</span>
                        <span>{{ format(l.amount) }}</span>
                    </div>
                    <p v-if="!day[a.key].lines.length" class="text-xs text-muted-foreground">No money in or out.</p>
                    <div class="flex justify-between border-t border-border pt-2 text-lg font-bold">
                        <span>{{ a.key === 'cash' ? 'Should be in drawer' : 'Bank balance' }}</span>
                        <span :class="a.tone">{{ format(day[a.key].closing) }}</span>
                    </div>
                </div>
            </Card>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Bank deposit / withdraw -->
            <Card v-if="can('cash.transfer')">
                <div class="mb-3 flex items-center gap-2"><ArrowRightLeft class="size-5 text-brand" /><h3 class="text-base font-semibold">Bank Deposit / Withdraw</h3></div>
                <div class="mb-3 grid grid-cols-2 gap-2">
                    <button type="button" @click="move.direction = 'deposit'" class="rounded-xl px-3 py-2 text-sm font-semibold" :class="move.direction === 'deposit' ? 'bg-brand text-white' : 'bg-muted'">Cash → Bank</button>
                    <button type="button" @click="move.direction = 'withdraw'" class="rounded-xl px-3 py-2 text-sm font-semibold" :class="move.direction === 'withdraw' ? 'bg-brand text-white' : 'bg-muted'">Bank → Cash</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <input v-model.number="move.amount" type="number" min="1" placeholder="Amount" class="w-32 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                    <input v-model="move.note" placeholder="Note (slip no.)" class="flex-1 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                    <button type="button" @click="submitMove" :disabled="move.processing || !(move.amount > 0)" class="rounded-full bg-brand px-5 py-2 text-sm font-semibold text-white disabled:opacity-40">Save</button>
                </div>
                <p v-if="move.errors.amount" class="mt-2 text-xs text-destructive">{{ move.errors.amount }}</p>
                <p class="mt-2 text-xs text-muted-foreground">Example: sale 1000 → deposit 800 → drawer shows 200, bank +800.</p>
            </Card>

            <!-- Close the day -->
            <Card v-if="can('cash.close')">
                <div class="mb-3 flex items-center gap-2"><Lock class="size-5 text-warning" /><h3 class="text-base font-semibold">Close the Day</h3></div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-muted-foreground">System says drawer should have</span><span class="font-semibold">{{ format(day.cash.closing) }}</span></div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">Cash you counted</span>
                        <input v-model.number="close.counted_cash" type="number" min="0" class="w-32 rounded-md border-border bg-background text-right text-sm focus:border-brand focus:ring-brand" />
                    </div>
                    <div v-if="diff !== null" class="flex justify-between rounded-lg px-3 py-2 font-semibold" :class="diff === 0 ? 'bg-success/10 text-success' : (diff < 0 ? 'bg-destructive/10 text-destructive' : 'bg-warning/10 text-warning')">
                        <span>{{ diff === 0 ? 'Matches ✓' : (diff < 0 ? 'Short (kam)' : 'Extra (zyada)') }}</span><span>{{ format(Math.abs(diff)) }}</span>
                    </div>
                    <input v-model="close.note" placeholder="Note (why short / extra)" class="w-full rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                    <button type="button" @click="submitClose" :disabled="close.processing || close.counted_cash === null || close.counted_cash === ''" class="w-full rounded-xl bg-warning px-4 py-2.5 font-semibold text-white disabled:opacity-40">
                        {{ day.closing ? 'Update Closing' : 'Close Day' }}
                    </button>
                    <p v-if="day.closing" class="text-xs text-muted-foreground">Closed by {{ day.closing.closer?.name }} — counted {{ format(day.closing.counted_cash) }}, difference {{ format(day.closing.difference) }}.</p>
                </div>
            </Card>
        </div>

        <!-- Every entry of the day -->
        <Card class="mt-4" :title="`All entries — ${date}`">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-2 pr-4 font-medium">Type</th><th class="py-2 pr-4 font-medium">Where</th><th class="py-2 pr-4 font-medium">Reference</th><th class="py-2 pr-4 font-medium">Note</th><th class="py-2 pr-0 font-medium text-right">Amount</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(e, i) in day.entries" :key="i" class="border-b border-border/60">
                            <td class="py-2 pr-4">{{ e.label }}</td>
                            <td class="py-2 pr-4 capitalize">{{ e.account === 'cash' ? 'Drawer' : 'Bank' }}</td>
                            <td class="py-2 pr-4 font-mono text-xs">{{ e.reference }}</td>
                            <td class="py-2 pr-4 text-muted-foreground">{{ e.note }}</td>
                            <td class="py-2 pr-0 text-right font-semibold" :class="e.direction === 'in' ? 'text-success' : 'text-destructive'">{{ e.direction === 'in' ? '+' : '−' }} {{ format(e.amount) }}</td>
                        </tr>
                        <tr v-if="!day.entries.length"><td colspan="5" class="py-6 text-center text-muted-foreground">No entries.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Closing history -->
        <Card class="mt-4" title="Day Closings (last 30)">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-2 pr-4 font-medium">Date</th><th class="py-2 pr-4 font-medium text-right">Expected</th><th class="py-2 pr-4 font-medium text-right">Counted</th><th class="py-2 pr-4 font-medium text-right">Difference</th><th class="py-2 pr-4 font-medium text-right">Bank</th><th class="py-2 pr-0 font-medium">By</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="c in closings" :key="c.id" class="border-b border-border/60">
                            <td class="py-2 pr-4">{{ c.closing_date?.slice(0, 10) }}</td>
                            <td class="py-2 pr-4 text-right">{{ format(c.expected_cash) }}</td>
                            <td class="py-2 pr-4 text-right">{{ format(c.counted_cash) }}</td>
                            <td class="py-2 pr-4 text-right font-semibold" :class="Number(c.difference) === 0 ? 'text-success' : (Number(c.difference) < 0 ? 'text-destructive' : 'text-warning')">{{ format(c.difference) }}</td>
                            <td class="py-2 pr-4 text-right">{{ format(c.bank_balance) }}</td>
                            <td class="py-2 pr-0">{{ c.closer?.name }}<span v-if="c.note" class="block text-xs text-muted-foreground">{{ c.note }}</span></td>
                        </tr>
                        <tr v-if="!closings.length"><td colspan="6" class="py-6 text-center text-muted-foreground">No closings yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
