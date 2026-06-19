<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { Plus, Minus, Trash2, RotateCcw, Container, Printer, Barcode, FileText } from 'lucide-vue-next'

const props = defineProps({ invoices: Object, customers: Array, warehouses: Array, products: Array, refillService: Object, bottleDeposit: { type: Number, default: 1000 } })
const { can, isSuperAdmin } = usePermissions()
const { format } = useCurrency()
const page = usePage()

// Owner-only: bill at cost price (counts as a normal sale at cost).
const atCost = ref(false)
function priceFor(p) { return Number(atCost.value ? p.cost_price : p.sale_price) }
watch(atCost, () => {
    // Re-price current cart lines to cost / sale.
    form.items.forEach((i) => {
        const p = props.products.find((x) => x.id === i.product_id)
        if (p) i.unit_price = priceFor(p)
    })
})

// Owner-only: record the cart as FREE internal use (no sale, no profit).
function internalUseFree() {
    if (!form.items.length) return
    if (!confirm('Record this cart as FREE internal/owner use? Stock kategi, koi sale nahi banegi.')) return
    router.post(route('consumption.pos'), {
        warehouse_id: form.warehouse_id,
        reason: 'Owner internal use (POS)',
        items: form.items.map((i) => ({ product_id: i.product_id, quantity: i.quantity })),
    }, { preserveScroll: true, onSuccess: resetCart })
}

// Receipt printer width (58mm / 80mm) — remembered per device.
const receiptSize = ref(Number(localStorage.getItem('receipt_size')) || 80)
watch(receiptSize, (v) => localStorage.setItem('receipt_size', v))

// One-click thermal print via a hidden iframe (no popup, no blocker, no extra tab).
function openReceipt(id) {
    const url = route('sales.receipt', id) + '?size=' + receiptSize.value
    let frame = document.getElementById('receipt-frame')
    if (frame) frame.remove()
    frame = document.createElement('iframe')
    frame.id = 'receipt-frame'
    Object.assign(frame.style, { position: 'fixed', right: '0', bottom: '0', width: '0', height: '0', border: '0' })
    frame.onload = () => {
        try { frame.contentWindow.focus(); frame.contentWindow.print() } catch (e) { /* blade auto-prints as fallback */ }
    }
    frame.src = url
    document.body.appendChild(frame)
}

// Colorful A4 invoice (new tab → print / save as PDF).
function openInvoice(id) {
    window.open(route('sales.invoice', id), '_blank')
}

// Candela-style: as soon as a sale completes, pop the thermal receipt to print.
watch(
    () => page.props.flash?.print_invoice_id,
    (id) => { if (id) openReceipt(id) },
)

// ---- Customer search + inline add ----
const customerSearch = ref('')
const showCustomerList = ref(false)
const selectedCustomer = computed(() => props.customers.find((c) => c.id === form.customer_id) || null)
const filteredCustomers = computed(() => {
    const q = customerSearch.value.toLowerCase().trim()
    const list = q ? props.customers.filter((c) => c.name.toLowerCase().includes(q) || (c.phone ?? '').includes(q)) : props.customers
    return list.slice(0, 8)
})
function pickCustomer(c) {
    form.customer_id = c ? c.id : ''
    customerSearch.value = ''
    showCustomerList.value = false
}

const showAddCustomer = ref(false)
const newCustomer = useForm({ name: '', phone: '', type: 'retail', opening_balance: 0 })
function saveCustomer() {
    newCustomer.post(route('customers.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { showAddCustomer.value = false; newCustomer.reset() },
    })
}
// Auto-select a customer added from the POS.
watch(
    () => page.props.flash?.new_customer_id,
    (id) => { if (id) { form.customer_id = id } },
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

// ---- Barcode scanner: scan (ends with Enter) → instant add ----
const scan = ref('')
const scanBox = ref(null)
function onScan() {
    const code = scan.value.trim()
    if (!code) return
    const p = props.products.find((x) => (x.barcode && x.barcode === code) || (x.sku && x.sku.toLowerCase() === code.toLowerCase()))
    if (p) {
        addToCart(p)
    } else {
        // not a code — treat as search term
        search.value = code
    }
    scan.value = ''
}
function focusScan() {
    scanBox.value?.focus()
}
onMounted(focusScan)

// Enter in the search box adds the first match.
function addFirstMatch() {
    if (filtered.value.length) addToCart(filtered.value[0])
    search.value = ''
}

// Quick cash: exact amount or a tendered note.
function quickCash(amount) {
    received.value = amount
}

const form = useForm({
    customer_id: '', warehouse_id: '', type: 'pos', discount: 0, paid_total: 0,
    payment_method: 'cash', new_bottle: false, deposit: 0, items: [],
})
form.warehouse_id = props.warehouses[0]?.id ?? ''

// Lightweight in-page toast for stock warnings.
const toast = ref(null)
function notify(msg) {
    toast.value = msg
    setTimeout(() => { if (toast.value === msg) toast.value = null }, 2500)
}
function cartQtyFor(id) {
    return form.items.filter((i) => i.product_id === id).reduce((s, i) => s + Number(i.quantity || 0), 0)
}

function addToCart(p, qty = 1, label = null) {
    const stock = Number(p.stock ?? 0)
    if (stock <= 0) { notify(`${p.name}: stock 0 hai — add nahi hoga`); return }
    if (cartQtyFor(p.id) + qty > stock) { notify(`${p.name}: sirf ${stock} ${p.is_pack ? 'packs' : 'available'}`); return }
    const existing = form.items.find((i) => i.product_id === p.id && !label)
    if (existing && !label) { existing.quantity += qty; return }
    form.items.push({ product_id: p.id, name: label ?? p.name, quantity: qty, unit_price: priceFor(p), discount: 0, tax: 0 })
}
// Add a full carton/"pat": one line of pack_size bottles (stock deducts pack_size).
function addPack(p) {
    addToCart(p, p.pack_size, `${p.name} — Pack (${p.pack_size})`)
}
function inc(it) {
    const p = props.products.find((x) => x.id === it.product_id)
    if (p && it.quantity + 1 > Number(p.stock)) { notify(`${p.name}: sirf ${p.stock} available`); return }
    it.quantity++
}
function dec(it) { it.quantity = Math.max(1, it.quantity - 1) }
function removeItem(i) { form.items.splice(i, 1) }

const subtotal = computed(() => form.items.reduce((s, i) => s + i.quantity * i.unit_price, 0))
const grand = computed(() => Math.max(0, subtotal.value - (Number(form.discount) || 0)))
const deposit = computed(() => (form.new_bottle ? Number(form.deposit) || 0 : 0))
const collectable = computed(() => grand.value + deposit.value)

// Cash the customer actually handed over (blank = paying in full).
const received = ref(null)
const paidNow = computed(() => {
    if (received.value === null || received.value === '') return collectable.value
    return Math.min(Number(received.value) || 0, grand.value) // revenue paid (deposit handled separately)
})
const change = computed(() => {
    if (received.value === null || received.value === '') return 0
    return Math.max(0, (Number(received.value) || 0) - collectable.value)
})
const dueAmount = computed(() => Math.max(0, grand.value - paidNow.value)) // udhaar / baqaya

function resetCart() {
    form.reset()
    received.value = null
    form.warehouse_id = props.warehouses[0]?.id ?? ''
    form.items = []
    setTimeout(focusScan, 100) // ready for the next customer immediately
}

function complete() {
    if (!form.items.length) return
    if (form.new_bottle && !form.customer_id) { alert('Bottle deposit ke liye customer select karein.'); return }
    // Udhaar / partial payment requires a named customer so the balance can be tracked.
    if (dueAmount.value > 0 && !form.customer_id) {
        alert('Customer ne poora paisa nahi diya — baqaya (udhaar) ke liye customer select karna zaroori hai.')
        return
    }
    form.paid_total = paidNow.value
    form.post(route('sales.store'), { preserveScroll: true, onSuccess: resetCart })
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

        <!-- Stock warning toast -->
        <transition name="fade">
            <div v-if="toast" class="fixed left-1/2 top-6 z-[120] -translate-x-1/2 rounded-full bg-destructive px-5 py-2.5 text-sm font-medium text-white shadow-lg">
                {{ toast }}
            </div>
        </transition>

        <div v-if="can('sales.create')" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Product picker -->
            <Card class="lg:col-span-2" title="Products">
                <!-- Barcode scanner: cursor stays here; scan → auto-add -->
                <div class="mb-3 flex items-center gap-2 rounded-2xl bg-brand/5 ring-1 ring-brand/20 px-3 py-2">
                    <Barcode class="size-5 text-brand" />
                    <input ref="scanBox" v-model="scan" @keyup.enter="onScan"
                        placeholder="Scan barcode / SKU and press Enter…"
                        class="w-full border-0 bg-transparent p-0 text-sm focus:ring-0" />
                </div>
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
                <input v-model="search" @keyup.enter="addFirstMatch" placeholder="Search product… (Enter to add first match)" class="mb-3 w-full rounded-full border-border bg-background px-4 py-2 text-sm focus:border-brand focus:ring-brand" />
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <div v-for="p in filtered" :key="p.id" class="rounded-2xl ring-1 p-3 transition"
                        :class="p.stock <= 0 ? 'ring-destructive/30 bg-destructive/5 opacity-70' : 'ring-border hover:ring-brand'">
                        <button type="button" @click="addToCart(p)" class="block w-full text-left">
                            <p class="text-sm font-medium truncate">{{ p.name }}</p>
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-muted-foreground">{{ format(p.sale_price) }}</p>
                                <span class="text-[11px] font-medium" :class="p.stock <= 0 ? 'text-destructive' : (p.stock <= 10 ? 'text-warning' : 'text-success')">
                                    {{ p.stock <= 0 ? 'Out of stock' : `Stock: ${p.stock}${p.is_pack ? ' pk' : ''}` }}
                                </span>
                            </div>
                        </button>
                        <button v-if="!p.is_pack && p.pack_size > 1" type="button" @click="addPack(p)" :disabled="p.stock <= 0"
                            class="mt-2 w-full rounded-full bg-muted px-2 py-1 text-[11px] font-medium hover:bg-brand/10 hover:text-brand disabled:opacity-50">
                            + Pack ×{{ p.pack_size }}
                        </button>
                    </div>
                </div>
            </Card>

            <!-- Cart -->
            <Card title="Cart">
                <div class="space-y-2 max-h-72 overflow-y-auto">
                    <div v-for="(it, i) in form.items" :key="i" class="flex items-center gap-2 text-sm">
                        <div class="flex-1 min-w-0">
                            <p class="truncate font-medium">{{ it.name }}</p>
                            <!-- Super Admin can set any price (owner gives whatever he wants); cashier sees fixed price -->
                            <input v-if="isSuperAdmin" v-model.number="it.unit_price" type="number" step="0.01" min="0"
                                class="mt-0.5 w-24 rounded border-border bg-background py-0.5 text-xs focus:border-brand focus:ring-brand" />
                            <p v-else class="text-xs text-muted-foreground">{{ format(it.unit_price) }}</p>
                        </div>
                        <button @click="dec(it)" class="rounded-full p-1 hover:bg-muted"><Minus class="size-3.5" /></button>
                        <span class="w-6 text-center">{{ it.quantity }}</span>
                        <button @click="inc(it)" class="rounded-full p-1 hover:bg-muted"><Plus class="size-3.5" /></button>
                        <button @click="removeItem(i)" class="rounded-full p-1 text-destructive"><Trash2 class="size-3.5" /></button>
                    </div>
                    <p v-if="!form.items.length" class="py-6 text-center text-sm text-muted-foreground">Tap products to add.</p>
                </div>

                <div class="mt-3 space-y-2 border-t border-border pt-3">
                    <!-- Customer search / select -->
                    <div class="relative">
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <input
                                    :value="selectedCustomer ? selectedCustomer.name : customerSearch"
                                    @input="(e) => { customerSearch = e.target.value; form.customer_id = ''; showCustomerList = true }"
                                    @focus="showCustomerList = true"
                                    placeholder="Walk-in — search customer…"
                                    class="w-full rounded-md border-border bg-background pr-7 text-sm focus:border-brand focus:ring-brand"
                                />
                                <button v-if="form.customer_id" type="button" @click="pickCustomer(null)" class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-destructive">×</button>
                            </div>
                            <button type="button" @click="showAddCustomer = !showAddCustomer" class="rounded-md bg-brand/10 px-2.5 py-1.5 text-sm font-medium text-brand hover:bg-brand/20" title="Add new customer">+</button>
                        </div>

                        <!-- Results dropdown -->
                        <div v-if="showCustomerList && !form.customer_id" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-card shadow-lg ring-1 ring-border">
                            <button type="button" @click="pickCustomer(null)" class="block w-full px-3 py-2 text-left text-sm hover:bg-muted">Walk-in (no customer)</button>
                            <button v-for="c in filteredCustomers" :key="c.id" type="button" @click="pickCustomer(c)" class="block w-full px-3 py-2 text-left text-sm hover:bg-muted">
                                {{ c.name }} <span class="text-xs text-muted-foreground">{{ c.phone }}</span>
                            </button>
                            <p v-if="!filteredCustomers.length" class="px-3 py-2 text-xs text-muted-foreground">No match — use “+” to add.</p>
                        </div>
                    </div>

                    <!-- Inline add customer -->
                    <div v-if="showAddCustomer" class="rounded-xl bg-muted/50 p-3 space-y-2">
                        <input v-model="newCustomer.name" placeholder="Customer name *" class="w-full rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                        <div class="flex gap-2">
                            <input v-model="newCustomer.phone" placeholder="Phone" class="flex-1 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                            <select v-model="newCustomer.type" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                                <option value="retail">Retail</option><option value="wholesale">Wholesale</option><option value="corporate">Corporate</option>
                            </select>
                        </div>
                        <button type="button" @click="saveCustomer" :disabled="newCustomer.processing || !newCustomer.name" class="w-full rounded-md bg-brand px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50">Save Customer</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model="form.type" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                            <option value="pos">POS</option><option value="wholesale">Wholesale</option><option value="credit">Credit</option>
                        </select>
                        <select v-model="form.warehouse_id" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <!-- Owner-only controls (hidden from cashiers) -->
                    <div v-if="isSuperAdmin" class="rounded-xl bg-brand/5 ring-1 ring-brand/20 p-2.5 space-y-2">
                        <label class="flex items-center gap-2 text-xs font-medium">
                            <input type="checkbox" v-model="atCost" class="rounded border-border text-brand focus:ring-brand" />
                            Bill at Cost (owner) — counts as a sale at cost price
                        </label>
                        <button type="button" @click="internalUseFree" class="w-full rounded-lg bg-warning/15 px-3 py-1.5 text-xs font-semibold text-warning hover:bg-warning/25">
                            Internal Use — FREE (no sale, stock only)
                        </button>
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

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Cash Received</span>
                        <input v-model.number="received" type="number" step="0.01" :placeholder="format(collectable)"
                            class="w-28 rounded-md border-border bg-background text-right text-sm focus:border-brand focus:ring-brand" />
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" @click="quickCash(collectable)" class="rounded-full bg-brand/10 px-2.5 py-1 text-xs font-medium text-brand hover:bg-brand/20">Exact</button>
                        <button v-for="n in [50,100,500,1000,5000]" :key="n" type="button" @click="quickCash(n)" class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium hover:bg-brand/10 hover:text-brand">{{ n }}</button>
                    </div>
                    <div v-if="change > 0" class="flex items-center justify-between text-sm text-success">
                        <span>Return / Change</span><span class="font-medium">{{ format(change) }}</span>
                    </div>
                    <div v-if="dueAmount > 0" class="flex items-center justify-between text-sm text-warning">
                        <span>Balance (Udhaar)</span><span class="font-medium">{{ format(dueAmount) }}</span>
                    </div>
                    <p v-if="dueAmount > 0 && !form.customer_id" class="text-xs text-destructive">⚠ Udhaar ke liye customer select karein.</p>

                    <PrimaryButton class="w-full justify-center" :disabled="form.processing || !form.items.length" @click="complete">
                        {{ dueAmount > 0 ? `Complete — Udhaar ${format(dueAmount)}` : (form.new_bottle ? 'Sell Bottle + Deposit' : 'Complete Sale') }}
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
                                    <button @click="openReceipt(inv.id)" class="rounded-full p-2 hover:bg-muted text-muted-foreground" title="Thermal receipt"><Printer class="size-4" /></button>
                                    <button @click="openInvoice(inv.id)" class="rounded-full p-2 hover:bg-brand/10 text-brand" title="Colorful invoice (A4 / PDF)"><FileText class="size-4" /></button>
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
