<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { Plus, Minus, Trash2, RotateCcw, Container, Printer } from 'lucide-vue-next'

const props = defineProps({ invoices: Object, customers: Array, warehouses: Array, products: Array, refillService: Object, bottleDeposit: { type: Number, default: 1000 } })
const { can } = usePermissions()
const { format } = useCurrency()
const page = usePage()

// Receipt printer width (58mm / 80mm) — remembered per device.
const receiptSize = ref(Number(localStorage.getItem('receipt_size')) || 80)
watch(receiptSize, (v) => localStorage.setItem('receipt_size', v))

function openReceipt(id) {
    window.open(route('sales.receipt', id) + '?size=' + receiptSize.value, '_blank', 'width=420,height=640')
}

// Candela-style: as soon as a sale completes, pop the thermal receipt to print.
watch(
    () => page.props.flash?.print_invoice_id,
    (id) => { if (id) openReceipt(id) },
)

// 19L REFILL: customer brings own bottle. Sell ONE refill service (deducts 19L water, no bottle).
function addRefill() {
    if (!props.refillService) return
    form.type = 'refill'
    form.new_bottle = false
    form.deposit = 0
    const existing = form.items.find((i) => i.product_id === props.refillService.id)
    if (existing) { existing.quantity++; return }
    form.items.push({ product_id: props.refillService.id, name: '19L Refill Service', quantity: 1, unit_price: Number(props.refillService.sale_price), discount: 0, tax: 0 })
}

// NEW 19L BOTTLE: sell water (refill service) + issue a company bottle on a refundable deposit.
function addNewBottle() {
    if (!props.refillService) return
    form.type = 'new_bottle'
    form.new_bottle = true
    form.deposit = props.bottleDeposit
    if (!form.items.find((i) => i.product_id === props.refillService.id)) {
        form.items.push({ product_id: props.refillService.id, name: '19L Water (new bottle)', quantity: 1, unit_price: Number(props.refillService.sale_price), discount: 0, tax: 0 })
    }
}

const search = ref('')
const filtered = computed(() => {
    const q = search.value.toLowerCase()
    return props.products.filter((p) => !q || p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q)).slice(0, 24)
})

const form = useForm({
    customer_id: '', warehouse_id: '', type: 'pos', discount: 0, paid_total: 0,
    payment_method: 'cash', new_bottle: false, deposit: 0, items: [],
})
form.warehouse_id = props.warehouses[0]?.id ?? ''

function addToCart(p, qty = 1, label = null) {
    const existing = form.items.find((i) => i.product_id === p.id && !label)
    if (existing && !label) { existing.quantity += qty; return }
    form.items.push({ product_id: p.id, name: label ?? p.name, quantity: qty, unit_price: Number(p.sale_price), discount: 0, tax: 0 })
}
// Add a full carton/"pat": one line of pack_size bottles (stock deducts pack_size).
function addPack(p) {
    addToCart(p, p.pack_size, `${p.name} — Pack (${p.pack_size})`)
}
function inc(it) { it.quantity++ }
function dec(it) { it.quantity = Math.max(1, it.quantity - 1) }
function removeItem(i) { form.items.splice(i, 1) }

const subtotal = computed(() => form.items.reduce((s, i) => s + i.quantity * i.unit_price, 0))
const grand = computed(() => Math.max(0, subtotal.value - (Number(form.discount) || 0)))
const deposit = computed(() => (form.new_bottle ? Number(form.deposit) || 0 : 0))
const collectable = computed(() => grand.value + deposit.value)

function complete() {
    if (!form.items.length) return
    if (form.new_bottle && !form.customer_id) { alert('Select a customer to track the bottle deposit.'); return }
    if (form.type !== 'credit') form.paid_total = grand.value
    form.post(route('sales.store'), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); form.warehouse_id = props.warehouses[0]?.id ?? ''; form.items = [] },
    })
}

function returnInvoice(inv) {
    if (confirm(`Return invoice ${inv.invoice_number}? Stock will be restored.`)) {
        router.post(route('sales.return', inv.id), {}, { preserveScroll: true })
    }
}
const statusTone = (s) => ({ paid: 'bg-success/10 text-success', partial: 'bg-warning/15 text-warning', unpaid: 'bg-destructive/10 text-destructive', returned: 'bg-muted text-muted-foreground' }[s] ?? 'bg-muted')
</script>

<template>
    <Head title="Sales / POS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-2xl font-semibold tracking-tight">Sales / POS</h2><p class="text-sm text-muted-foreground">Billing, credit sales &amp; returns. Receipt auto-prints after each sale.</p></div>
                <label class="flex items-center gap-2 text-sm">
                    <span class="text-muted-foreground">Receipt</span>
                    <select v-model.number="receiptSize" class="rounded-full border-border bg-background text-sm focus:border-brand focus:ring-brand">
                        <option :value="58">58mm</option>
                        <option :value="80">80mm</option>
                    </select>
                </label>
            </div>
        </template>

        <div v-if="can('sales.create')" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Product picker -->
            <Card class="lg:col-span-2" title="Products">
                <div v-if="refillService" class="mb-3 grid grid-cols-2 gap-2">
                    <button type="button" @click="addRefill"
                        class="flex items-center justify-center gap-2 rounded-2xl bg-brand/10 text-brand px-3 py-2.5 text-sm font-semibold hover:bg-brand/20 transition">
                        <Container class="size-4" /> 19L Refill (own bottle)
                    </button>
                    <button type="button" @click="addNewBottle"
                        class="flex items-center justify-center gap-2 rounded-2xl bg-warning/15 text-warning px-3 py-2.5 text-sm font-semibold hover:bg-warning/25 transition">
                        <Container class="size-4" /> New 19L Bottle + Deposit
                    </button>
                </div>
                <input v-model="search" placeholder="Search product…" class="mb-3 w-full rounded-full border-border bg-background px-4 py-2 text-sm focus:border-brand focus:ring-brand" />
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <div v-for="p in filtered" :key="p.id" class="rounded-2xl ring-1 ring-border p-3 hover:ring-brand transition">
                        <button type="button" @click="addToCart(p)" class="block w-full text-left">
                            <p class="text-sm font-medium truncate">{{ p.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ format(p.sale_price) }}</p>
                        </button>
                        <button v-if="p.pack_size > 1" type="button" @click="addPack(p)"
                            class="mt-2 w-full rounded-full bg-muted px-2 py-1 text-[11px] font-medium hover:bg-brand/10 hover:text-brand">
                            + Pack ×{{ p.pack_size }}
                        </button>
                    </div>
                </div>
            </Card>

            <!-- Cart -->
            <Card title="Cart">
                <div class="space-y-2 max-h-72 overflow-y-auto">
                    <div v-for="(it, i) in form.items" :key="i" class="flex items-center gap-2 text-sm">
                        <div class="flex-1 min-w-0"><p class="truncate font-medium">{{ it.name }}</p><p class="text-xs text-muted-foreground">{{ format(it.unit_price) }}</p></div>
                        <button @click="dec(it)" class="rounded-full p-1 hover:bg-muted"><Minus class="size-3.5" /></button>
                        <span class="w-6 text-center">{{ it.quantity }}</span>
                        <button @click="inc(it)" class="rounded-full p-1 hover:bg-muted"><Plus class="size-3.5" /></button>
                        <button @click="removeItem(i)" class="rounded-full p-1 text-destructive"><Trash2 class="size-3.5" /></button>
                    </div>
                    <p v-if="!form.items.length" class="py-6 text-center text-sm text-muted-foreground">Tap products to add.</p>
                </div>

                <div class="mt-3 space-y-2 border-t border-border pt-3">
                    <select v-model="form.customer_id" class="block w-full rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                        <option value="">Walk-in</option>
                        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model="form.type" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                            <option value="pos">POS</option><option value="wholesale">Wholesale</option><option value="credit">Credit</option>
                        </select>
                        <select v-model="form.warehouse_id" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">Subtotal</span><span>{{ format(subtotal) }}</span></div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Discount</span>
                        <input v-model.number="form.discount" type="number" step="0.01" class="w-24 rounded-md border-border bg-background text-right text-sm focus:border-brand focus:ring-brand" />
                    </div>
                    <div v-if="form.new_bottle" class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Bottle Deposit (refundable)</span>
                        <input v-model.number="form.deposit" type="number" step="0.01" class="w-24 rounded-md border-border bg-background text-right text-sm focus:border-brand focus:ring-brand" />
                    </div>
                    <div class="flex items-center justify-between text-sm"><span class="text-muted-foreground">Water / Sale</span><span>{{ format(grand) }}</span></div>
                    <div class="flex items-center justify-between text-base font-semibold"><span>{{ form.new_bottle ? 'Total Collectable' : 'Total' }}</span><span class="text-brand">{{ format(collectable) }}</span></div>
                    <PrimaryButton class="w-full justify-center" :disabled="form.processing || !form.items.length" @click="complete">
                        {{ form.type === 'credit' ? 'Save Credit Sale' : (form.new_bottle ? 'Sell Bottle + Deposit' : 'Complete Sale') }}
                    </PrimaryButton>
                </div>
            </Card>
        </div>

        <!-- Recent invoices -->
        <Card class="mt-4" title="Recent Invoices">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-3 pr-4 font-medium">Invoice #</th><th class="py-3 pr-4 font-medium">Customer</th><th class="py-3 pr-4 font-medium">Type</th>
                        <th class="py-3 pr-4 font-medium text-right">Total</th><th class="py-3 pr-4 font-medium text-right">Balance</th>
                        <th class="py-3 pr-4 font-medium">Status</th><th class="py-3 pr-0 font-medium text-right">Actions</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="inv in invoices.data" :key="inv.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-mono text-xs">{{ inv.invoice_number }}</td>
                            <td class="py-3 pr-4">{{ inv.customer?.name ?? 'Walk-in' }}</td>
                            <td class="py-3 pr-4 capitalize">{{ inv.type }}</td>
                            <td class="py-3 pr-4 text-right font-medium">{{ format(inv.grand_total) }}</td>
                            <td class="py-3 pr-4 text-right" :class="inv.balance > 0 ? 'text-warning' : ''">{{ format(inv.balance) }}</td>
                            <td class="py-3 pr-4"><span class="rounded-full px-2 py-0.5 text-xs capitalize" :class="statusTone(inv.status)">{{ inv.status }}</span></td>
                            <td class="py-3 pr-0 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openReceipt(inv.id)" class="rounded-full p-2 hover:bg-muted text-muted-foreground" title="Print receipt"><Printer class="size-4" /></button>
                                    <button v-if="inv.status !== 'returned' && can('sales.return')" @click="returnInvoice(inv)" class="rounded-full p-2 hover:bg-muted text-muted-foreground" title="Return"><RotateCcw class="size-4" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!invoices.data.length"><td colspan="7" class="py-8 text-center text-muted-foreground">No invoices yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
