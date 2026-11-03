<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { Banknote, Landmark } from 'lucide-vue-next'

const props = defineProps({ date: String, day: Object, month: Object, hisaab: Object, history: Array, closings: Array })
const { can } = usePermissions()
const { format } = useCurrency()

const date = ref(props.date)
function go() { router.get(route('cash.index'), { date: date.value }, { preserveState: true }) }

// Step 2 — cash → bank (or bank → cash).
const move = useForm({ direction: 'deposit', amount: null, date: props.date, note: '' })
// Two linked boxes (deposit only): bank + cash always = what's in the drawer now.
const keepCash = ref(null)
function fromBank(v) { move.amount = v === '' ? null : Number(v); keepCash.value = v === '' ? null : Math.max(0, Math.round((props.day.cash.closing - Number(v)) * 100) / 100) }
function fromCash(v) { keepCash.value = v === '' ? null : Number(v); move.amount = v === '' ? null : Math.max(0, Math.round((props.day.cash.closing - Number(v)) * 100) / 100) }
function submitMove() {
    move.date = props.date
    move.post(route('cash.transfer'), { preserveScroll: true, preserveState: true, onSuccess: () => { move.reset('amount', 'note'); keepCash.value = null } })
}

// Hisaab: one tap fills the two boxes with "bank everything above the float".
function useSuggestion() { fromBank(String(props.hisaab.suggested_deposit)); document.getElementById('deposit-box')?.scrollIntoView({ behavior: 'smooth', block: 'center' }) }
const floatForm = useForm({ float: props.hisaab.float })
const editFloat = ref(false)
function saveFloat() { floatForm.post(route('cash.float'), { preserveScroll: true, preserveState: true, onSuccess: () => (editFloat.value = false) }) }
function printHisaab() { window.open(route('cash.hisaab') + '?to=' + props.date, '_blank') }

// Step 3 — count the drawer and close.
const close = useForm({ date: props.date, counted_cash: props.day.closing?.counted_cash ?? null, note: props.day.closing?.note ?? '' })
const diff = computed(() => (close.counted_cash === null || close.counted_cash === '' ? null : Number(close.counted_cash) - props.day.cash.closing))
function submitClose() { close.date = props.date; close.post(route('cash.close'), { preserveScroll: true, preserveState: true }) }
</script>

<template>
    <Head title="Cash Book" />
    <AppLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 class="text-2xl font-semibold tracking-tight">Cash Book</h2><p class="text-sm text-muted-foreground">Drawer aur bank ka hisaab — 3 steps.</p></div>
                <div class="flex items-end gap-2">
                    <input v-model="date" type="date" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                    <button @click="go" class="rounded-full bg-brand px-4 py-2 text-sm font-medium text-white">Show</button>
                </div>
            </div>
        </template>

        <!-- HISAAB: since the last deposit -->
        <Card class="ring-2 ring-brand/30">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-brand">Hisaab — pichle deposit ke baad se</p>
                    <p class="text-sm text-muted-foreground">{{ hisaab.since ? `Last deposit ${hisaab.since} (${format(hisaab.last_deposit.amount)})` : 'Abhi tak koi deposit nahi' }} → {{ date }}</p>
                </div>
                <button type="button" @click="printHisaab" class="rounded-full bg-muted px-4 py-1.5 text-xs font-semibold hover:bg-muted/70">🖨 Print slip</button>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between text-muted-foreground"><span>Drawer mein pehle se (change)</span><span>{{ format(hisaab.opening) }}</span></div>
                    <div v-for="l in hisaab.lines" :key="l.kind + l.direction" class="flex justify-between">
                        <span :class="l.direction === 'in' ? 'text-success' : 'text-destructive'">{{ l.direction === 'in' ? '+' : '−' }} {{ l.label }}</span><span>{{ format(l.amount) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-border pt-1.5 text-lg font-bold"><span>Drawer mein abhi</span><span class="text-success">{{ format(hisaab.drawer_now) }}</span></div>
                </div>
                <div class="rounded-2xl bg-brand/5 p-4 ring-1 ring-brand/20">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Change ke liye drawer mein rakhna hai</span>
                        <span v-if="!editFloat" class="font-semibold">{{ format(hisaab.float) }} <button v-if="can('cash.transfer')" type="button" @click="editFloat = true" class="ml-1 text-xs text-brand underline">change</button></span>
                        <span v-else class="flex items-center gap-1">
                            <input v-model.number="floatForm.float" type="number" min="0" class="w-24 rounded-md border-border bg-background text-sm" />
                            <button type="button" @click="saveFloat" class="rounded-full bg-brand px-3 py-1 text-xs font-semibold text-white">Save</button>
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">Bank mein jama karein</p>
                    <p class="text-3xl font-bold text-brand">{{ format(hisaab.suggested_deposit) }}</p>
                    <p class="text-xs text-muted-foreground">= {{ format(hisaab.drawer_now) }} − {{ format(hisaab.float) }} change</p>
                    <button v-if="can('cash.transfer') && hisaab.suggested_deposit > 0" type="button" @click="useSuggestion"
                        class="mt-3 w-full rounded-xl bg-brand px-4 py-2.5 text-sm font-bold text-white hover:opacity-90">
                        {{ format(hisaab.suggested_deposit) }} bank mein, {{ format(hisaab.float) }} drawer mein →
                    </button>
                </div>
            </div>
        </Card>

        <!-- STEP 1: what should be where -->
        <Card class="mt-4">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-muted-foreground">Step 1 · Aaj ka hisaab ({{ date }})</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-success/10 p-4 ring-1 ring-success/20">
                    <div class="flex items-center gap-2 text-sm font-semibold"><Banknote class="size-5 text-success" /> Cash drawer mein hona chahiye</div>
                    <p class="mt-1 text-3xl font-bold text-success">{{ format(day.cash.closing) }}</p>
                    <div class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between text-muted-foreground"><span>Subah tha (opening)</span><span>{{ format(day.cash.opening) }}</span></div>
                        <div v-for="l in day.cash.lines" :key="l.kind + l.direction" class="flex justify-between">
                            <span :class="l.direction === 'in' ? 'text-success' : 'text-destructive'">{{ l.direction === 'in' ? '+' : '−' }} {{ l.label }}</span><span>{{ format(l.amount) }}</span>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl bg-brand/10 p-4 ring-1 ring-brand/20">
                    <div class="flex items-center gap-2 text-sm font-semibold"><Landmark class="size-5 text-brand" /> Bank balance</div>
                    <p class="mt-1 text-3xl font-bold text-brand">{{ format(day.bank.closing) }}</p>
                    <div class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between text-muted-foreground"><span>Subah tha (opening)</span><span>{{ format(day.bank.opening) }}</span></div>
                        <div v-for="l in day.bank.lines" :key="l.kind + l.direction" class="flex justify-between">
                            <span :class="l.direction === 'in' ? 'text-success' : 'text-destructive'">{{ l.direction === 'in' ? '+' : '−' }} {{ l.label }}</span><span>{{ format(l.amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <!-- STEP 2: move cash to bank -->
        <Card v-if="can('cash.transfer')" id="deposit-box" class="mt-4">
            <p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted-foreground">Step 2 · Bank mein jama karein</p>
            <p class="mb-3 text-sm text-muted-foreground">Misaal: 5,500 ki sale hui, 5,000 bank mein dalne hain → yahan <b>5000</b> likhein. Drawer mein 500 reh jayega.</p>
            <div class="mb-3 flex gap-2">
                <button type="button" @click="move.direction = 'deposit'" class="rounded-full px-4 py-1.5 text-sm font-semibold" :class="move.direction === 'deposit' ? 'bg-brand text-white' : 'bg-muted'">Cash → Bank</button>
                <button type="button" @click="move.direction = 'withdraw'" class="rounded-full px-4 py-1.5 text-sm font-semibold" :class="move.direction === 'withdraw' ? 'bg-brand text-white' : 'bg-muted'">Bank → Cash</button>
            </div>
            <template v-if="move.direction === 'deposit'">
                <p class="mb-2 text-sm">Drawer mein abhi <b>{{ format(day.cash.closing) }}</b> hai. Koi ek box likhein, doosra khud bharega.</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:max-w-lg">
                    <label class="block rounded-xl bg-card p-3 ring-2 ring-brand/40">
                        <span class="text-xs font-bold uppercase tracking-wide text-brand">🏦 Bank mein jama</span>
                        <input :value="move.amount" @input="fromBank($event.target.value)" type="number" min="0" :max="day.cash.closing" placeholder="Bank mein kitne? e.g. 5000"
                            class="mt-1 w-full rounded-lg border-border bg-background text-xl font-bold focus:border-brand focus:ring-brand" />
                    </label>
                    <label class="block rounded-xl bg-card p-3 ring-2 ring-success/40">
                        <span class="text-xs font-bold uppercase tracking-wide text-success">💵 Cash mein (drawer)</span>
                        <input :value="keepCash" @input="fromCash($event.target.value)" type="number" min="0" :max="day.cash.closing" placeholder="Drawer mein kitne rahenge? e.g. 500"
                            class="mt-1 w-full rounded-lg border-border bg-background text-xl font-bold focus:border-success focus:ring-success" />
                    </label>
                </div>
            </template>
            <div v-else class="flex items-center gap-2">
                <label class="block rounded-xl bg-card p-3 ring-2 ring-brand/40">
                    <span class="text-xs font-bold uppercase tracking-wide text-brand">🏦 Bank se nikaal kar cash mein</span>
                    <input v-model.number="move.amount" type="number" min="1" placeholder="Kitne nikaale? e.g. 2000" class="mt-1 w-full rounded-lg border-border bg-background text-xl font-bold focus:border-brand focus:ring-brand" />
                </label>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <input v-model="move.note" placeholder="Bank slip no. (optional)" class="w-48 rounded-xl border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                <button type="button" @click="submitMove" :disabled="move.processing || !(move.amount > 0)" class="rounded-xl bg-brand px-6 py-2.5 text-sm font-bold text-white disabled:opacity-40">
                    {{ move.direction === 'deposit' ? (move.amount > 0 ? `${format(move.amount)} bank mein jama karein` : 'Bank mein jama karein') : 'Bank se nikaalein' }}
                </button>
            </div>
            <p v-if="move.errors.amount" class="mt-2 text-xs text-destructive">{{ move.errors.amount }}</p>
        </Card>

        <!-- STEP 3: count & close -->
        <Card v-if="can('cash.close')" class="mt-4">
            <p class="mb-1 text-xs font-bold uppercase tracking-wide text-muted-foreground">Step 3 · Din close karein</p>
            <p class="mb-3 text-sm text-muted-foreground">Drawer ka cash gin kar likhein. System batayega barabar hai ya kam / zyada.</p>
            <div class="flex flex-wrap items-center gap-3">
                <div class="rounded-xl bg-muted/50 px-4 py-2 text-sm">Hona chahiye: <b>{{ format(day.cash.closing) }}</b></div>
                <input v-model.number="close.counted_cash" type="number" min="0" placeholder="Gina hua cash" class="w-40 rounded-xl border-border bg-background text-lg font-semibold focus:border-brand focus:ring-brand" />
                <div v-if="diff !== null" class="rounded-xl px-4 py-2 text-sm font-semibold" :class="diff === 0 ? 'bg-success/10 text-success' : (diff < 0 ? 'bg-destructive/10 text-destructive' : 'bg-warning/10 text-warning')">
                    {{ diff === 0 ? 'Barabar ✓' : (diff < 0 ? `Kam ${format(-diff)}` : `Zyada ${format(diff)}`) }}
                </div>
                <input v-model="close.note" placeholder="Note (kam / zyada kyun)" class="w-48 rounded-xl border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                <button type="button" @click="submitClose" :disabled="close.processing || close.counted_cash === null || close.counted_cash === ''" class="rounded-xl bg-warning px-6 py-2.5 text-sm font-bold text-white disabled:opacity-40">
                    {{ day.closing ? 'Update' : 'Close Day' }}
                </button>
            </div>
            <p v-if="day.closing" class="mt-2 text-xs text-muted-foreground">Closed by {{ day.closing.closer?.name }} — counted {{ format(day.closing.counted_cash) }}, difference {{ format(day.closing.difference) }}.</p>
        </Card>

        <!-- Hisaab history: every settlement -->
        <Card class="mt-4" title="Hisaab history — har deposit ka hisaab">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-2 pr-4 font-medium">Period</th><th class="py-2 pr-4 font-medium text-right">Cash sales</th><th class="py-2 pr-4 font-medium text-right">Udhaar / adv.</th>
                        <th class="py-2 pr-4 font-medium text-right">Kharcha</th><th class="py-2 pr-4 font-medium text-right">Drawer tha</th><th class="py-2 pr-4 font-medium text-right">Bank mein</th><th class="py-2 pr-0 font-medium text-right">Drawer mein raha</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="h in history" :key="h.reference" class="border-b border-border/60">
                            <td class="py-2 pr-4 whitespace-nowrap">{{ h.from ? `${h.from} → ` : '' }}{{ h.to }}<span v-if="h.note" class="block text-xs text-muted-foreground">{{ h.note }}</span></td>
                            <td class="py-2 pr-4 text-right text-success">{{ format(h.sales) }}</td>
                            <td class="py-2 pr-4 text-right text-success">{{ format(h.udhaar) }}</td>
                            <td class="py-2 pr-4 text-right text-destructive">{{ format(h.expenses) }}</td>
                            <td class="py-2 pr-4 text-right">{{ format(h.drawer_before) }}</td>
                            <td class="py-2 pr-4 text-right font-bold text-brand">{{ format(h.deposited) }}</td>
                            <td class="py-2 pr-0 text-right">{{ format(h.kept) }}</td>
                        </tr>
                        <tr v-if="!history.length"><td colspan="7" class="py-6 text-center text-muted-foreground">Pehla hisaab abhi hona hai.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Bank deposits this month -->
        <Card class="mt-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-base font-semibold">Bank mein jama — is mahine ({{ month.from }} → {{ month.to }})</h3>
                <span class="rounded-full bg-brand/10 px-4 py-1.5 text-base font-bold text-brand">Total {{ format(month.deposited_total) }}</span>
            </div>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-muted-foreground border-b border-border">
                    <th class="py-2 pr-4 font-medium">Date</th><th class="py-2 pr-4 font-medium">Slip / note</th><th class="py-2 pr-4 font-medium text-right">Jama</th><th class="py-2 pr-0 font-medium text-right">Ab tak total</th>
                </tr></thead>
                <tbody>
                    <tr v-for="d in month.deposits" :key="d.reference" class="border-b border-border/60">
                        <td class="py-2 pr-4">{{ d.date }}</td><td class="py-2 pr-4 text-muted-foreground">{{ d.note || d.reference }}</td>
                        <td class="py-2 pr-4 text-right font-semibold text-brand">{{ format(d.amount) }}</td><td class="py-2 pr-0 text-right">{{ format(d.running) }}</td>
                    </tr>
                    <tr v-if="!month.deposits.length"><td colspan="4" class="py-6 text-center text-muted-foreground">Is mahine abhi koi deposit nahi.</td></tr>
                </tbody>
            </table>
            <p v-if="month.withdrawn_total" class="mt-2 text-xs text-muted-foreground">Bank se nikaala: {{ format(month.withdrawn_total) }}</p>
        </Card>

        <!-- Details -->
        <Card class="mt-4" :title="`Sab entries — ${date}`">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-2 pr-4 font-medium">Type</th><th class="py-2 pr-4 font-medium">Where</th><th class="py-2 pr-4 font-medium">Reference</th><th class="py-2 pr-4 font-medium">Note</th><th class="py-2 pr-0 font-medium text-right">Amount</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="(e, i) in day.entries" :key="i" class="border-b border-border/60">
                            <td class="py-2 pr-4">{{ e.label }}</td>
                            <td class="py-2 pr-4">{{ e.account === 'cash' ? 'Drawer' : 'Bank' }}</td>
                            <td class="py-2 pr-4 font-mono text-xs">{{ e.reference }}</td>
                            <td class="py-2 pr-4 text-muted-foreground">{{ e.note }}</td>
                            <td class="py-2 pr-0 text-right font-semibold" :class="e.direction === 'in' ? 'text-success' : 'text-destructive'">{{ e.direction === 'in' ? '+' : '−' }} {{ format(e.amount) }}</td>
                        </tr>
                        <tr v-if="!day.entries.length"><td colspan="5" class="py-6 text-center text-muted-foreground">No entries.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Card class="mt-4" title="Pichle closings (30)">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-2 pr-4 font-medium">Date</th><th class="py-2 pr-4 font-medium text-right">Hona chahiye</th><th class="py-2 pr-4 font-medium text-right">Gina</th><th class="py-2 pr-4 font-medium text-right">Farq</th><th class="py-2 pr-4 font-medium text-right">Bank</th><th class="py-2 pr-0 font-medium">By</th>
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
                        <tr v-if="!closings.length"><td colspan="6" class="py-6 text-center text-muted-foreground">Abhi koi closing nahi.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
