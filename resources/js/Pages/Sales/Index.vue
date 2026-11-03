<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { Plus, Minus, Trash2, RotateCcw, Container, Printer, Barcode, FileText, User, Store, Banknote, NotebookPen, ChevronDown, Droplets, Package } from 'lucide-vue-next'

const props = defineProps({ invoices: Object, customers: Array, warehouses: Array, products: Array, refillService: Object, coolerRefill: Object, todayCounts: { type: [Object, Array], default: () => ({}) }, bottleDeposit: { type: Number, default: 1000 } })
const { can, isSuperAdmin } = usePermissions()
const { format } = useCurrency()
const page = usePage()

// Owner-only: bill at cost price (counts as a normal sale at cost).
const atCost = ref(false)
// Price tier: walk-in / retail → own-shop price; shop customers (wholesale) → market price.
function priceFor(p) {
    if (atCost.value && p.cost_price !== null) return Number(p.cost_price)
    if (form.type === 'wholesale' && p.wholesale_price !== null) return Number(p.wholesale_price)
    return Number(p.sale_price)
}
function costFor(it) {
    const p = props.products.find((x) => x.id === it.product_id)
    return p?.cost_price ?? null
}
function lineNet(it) { return Math.max(0, it.quantity * it.unit_price - (Number(it.discount) || 0)) }
// Warn the owner when a manual price / discount sells a line below its cost.
function belowCost(it) {
    const cost = costFor(it)
    return cost !== null && lineNet(it) < cost * it.quantity
}

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
// "Printer connected" — per device. Off = no print dialog after a sale (reprint from Recent Invoices).
// With Chrome started as `--kiosk-printing` the receipt goes straight to the printer, no dialog.
const printerOn = ref(localStorage.getItem('pos_printer') === '1')
watch(printerOn, (v) => localStorage.setItem('pos_printer', v ? '1' : '0'))

// One-click print via a hidden iframe (no popup, no blocker, no extra tab).
function printFrame(url) {
    let frame = document.getElementById('print-frame')
    if (frame) frame.remove()
    frame = document.createElement('iframe')
    frame.id = 'print-frame'
    Object.assign(frame.style, { position: 'fixed', right: '0', bottom: '0', width: '0', height: '0', border: '0' })
    frame.onload = () => {
        try { frame.contentWindow.focus(); frame.contentWindow.print() } catch (e) { /* noop */ }
    }
    frame.src = url
    document.body.appendChild(frame)
}

function openReceipt(id) { printFrame(route('sales.receipt', id) + '?size=' + receiptSize.value) }
function openInvoice(id) { printFrame(route('sales.invoice', id)) } // colorful A4, prints directly

// After a sale: auto-print the receipt only on a device with a printer.
watch(
    () => page.props.flash?.print_invoice_id,
    (id) => { if (id && printerOn.value) openReceipt(id) },
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
    // Shop (wholesale) customers buy at the market price; walk-in / retail at the shop price.
    if (c?.type === 'wholesale') form.type = 'wholesale'
    else if (form.type === 'wholesale') form.type = 'pos'
    customerSearch.value = ''
    showCustomerList.value = false
}

// ---- Receive udhaar right at the POS (salesman too: customers.collect) ----
const owes = computed(() => Number(selectedCustomer.value?.current_balance ?? 0))
const collect = useForm({ amount: null, method: 'cash' })
// Never more than the udhaar owed.
watch(() => collect.amount, (v) => { if (Number(v) > owes.value) collect.amount = owes.value })
function receiveUdhaar() {
    if (!selectedCustomer.value || !(Number(collect.amount) > 0)) return
    collect.post(route('customers.payment', selectedCustomer.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => collect.reset(),
    })
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
    if (form.type !== 'wholesale') form.type = 'refill' // keep a shop customer on market price
    form.new_bottle = false
    form.deposit = 0
    const existing = form.items.find((i) => i.product_id === props.refillService.id)
    if (existing) { existing.quantity++; return }
    form.items.push({ product_id: props.refillService.id, name: '19L Refill Service', quantity: 1, unit_price: Number(props.refillService.sale_price), discount: 0, tax: 0 })
}

// NEW 19L BOTTLE: sell water (refill service) + issue a company bottle on a refundable deposit.
function addNewBottle() {
    if (!props.refillService) return
    if (form.type !== 'wholesale') form.type = 'new_bottle'
    form.new_bottle = true
    form.deposit = props.bottleDeposit
    if (!form.items.find((i) => i.product_id === props.refillService.id)) {
        form.items.push({ product_id: props.refillService.id, name: '19L Water (new bottle)', quantity: 1, unit_price: Number(props.refillService.sale_price), discount: 0, tax: 0 })
    }
}

// COOLER REFILL: amount differs per customer (20, 30, …) — typed in, any POS user.
const coolerOpen = ref(false)
const coolerCustom = ref(null)
function addCoolerRefill(amount) {
    amount = Number(amount)
    if (!props.coolerRefill || !amount || amount <= 0) return
    coolerOpen.value = false
    coolerCustom.value = null
    form.items.push({ product_id: props.coolerRefill.id, name: 'Cooler Refill', quantity: 1, unit_price: amount, discount: 0, tax: 0, manual: true })
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
    payment_method: 'cash', new_bottle: false, deposit: 0, bottles_returned: null, items: [],
})
form.warehouse_id = props.warehouses[0]?.id ?? ''

// 19L exchange: empties returned default to the refills given (plain swap). Enter fewer when the
// customer keeps bottles — the difference goes on their bottle balance.
const refillQty = computed(() => form.items.filter((i) => i.product_id === props.refillService?.id).reduce((s, i) => s + Number(i.quantity || 0), 0))
watch(refillQty, (q) => { form.bottles_returned = q > 0 ? q : null })
function stepReturned(d) { form.bottles_returned = Math.max(0, (Number(form.bottles_returned) || 0) + d) }
const bottlesOwed = computed(() => refillQty.value - (Number(form.bottles_returned) || 0))

// Re-price cart lines when the tier changes (cost / market / shop).
watch([atCost, () => form.type], () => {
    form.items.forEach((i) => {
        const p = props.products.find((x) => x.id === i.product_id)
        if (p) i.unit_price = priceFor(p)
    })
})

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
function inc(it) {
    const p = props.products.find((x) => x.id === it.product_id)
    if (p && it.quantity + 1 > Number(p.stock)) { notify(`${p.name}: sirf ${p.stock} available`); return }
    it.quantity++
}
function dec(it) { it.quantity = Math.max(1, it.quantity - 1) }
function removeItem(i) { form.items.splice(i, 1) }

const subtotal = computed(() => form.items.reduce((s, i) => s + lineNet(i), 0)) // after line discounts
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

// ---- One-click helpers ----
// Customer mode: walk-in → shop price · shop customer → market price.
const isShop = computed(() => form.type === 'wholesale')
function setShop(on) {
    form.type = on ? 'wholesale' : 'pos'
    if (!on && selectedCustomer.value?.type === 'wholesale') pickCustomer(null)
}
const showMore = ref(false)
// Paid in full in cash (or what was typed in "Cash received") → complete + print.
function payCash() { complete() }
// Nothing paid now → whole bill on the customer's udhaar.
function payUdhaar() {
    if (!form.customer_id) { notify('Udhaar ke liye pehle customer select karein'); showCustomerList.value = true; return }
    received.value = 0
    complete()
}
function lineFor(id) { return form.items.find((i) => i.product_id === id) }
function tileQty(id) { return cartQtyFor(id) }
function soldToday(id) { return Number(props.todayCounts?.[id] ?? 0) }

function resetCart() {
    form.reset()
    received.value = null
    form.warehouse_id = props.warehouses[0]?.id ?? ''
    form.items = []
    setTimeout(focusScan, 100) // ready for the next customer immediately
}

function complete() {
    if (!form.items.length) return
    if (form.new_bottle && !form.customer_id) { notify('Bottle deposit ke liye customer select karein'); return }
    if (refillQty.value > 0 && bottlesOwed.value !== 0 && !form.customer_id) {
        notify('Khali bottles kam wapas aayin — customer select karein'); return
    }
    // Udhaar / partial payment requires a named customer so the balance can be tracked.
    if (dueAmount.value > 0 && !form.customer_id) {
        notify('Baqaya (udhaar) ke liye customer select karein'); return
    }
    form.paid_total = paidNow.value
    // Empty the bill only when the sale really saved (a stock error comes back as flash.error).
    form.post(route('sales.store'), { preserveScroll: true, onSuccess: (pg) => { if (!pg.props.flash?.error) resetCart() } })
}

function returnInvoice(inv) {
    if (confirm(`Return invoice ${inv.invoice_number}? Stock will be restored.`)) {
        router.post(route('sales.return', inv.id), {}, { preserveScroll: true })
    }
}
const statusTone = (s) => ({ paid: 'bg-success/10 text-success', partial: 'bg-warning/15 text-warning', unpaid: 'bg-destructive/10 text-destructive', returned: 'bg-muted text-muted-foreground' }[s] ?? 'bg-muted')
</script>

<template>
    <Head title="POS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-2xl font-semibold tracking-tight">POS</h2><p class="text-sm text-muted-foreground">Tap a product → tap Cash. Done.</p></div>
                <div class="flex items-center gap-3 text-sm">
                    <label class="flex cursor-pointer items-center gap-2 rounded-full bg-card px-3 py-1.5 ring-1 ring-border">
                        <input type="checkbox" v-model="printerOn" class="rounded border-border text-brand focus:ring-brand" />
                        <Printer class="size-4" :class="printerOn ? 'text-brand' : 'text-muted-foreground'" />
                        <span>{{ printerOn ? 'Auto-print ON' : 'No printer' }}</span>
                    </label>
                    <select v-if="printerOn" v-model.number="receiptSize" class="rounded-full border-border bg-background text-sm focus:border-brand focus:ring-brand">
                        <option :value="58">58mm</option>
                        <option :value="80">80mm</option>
                    </select>
                </div>
            </div>
        </template>

        <!-- Warning toast -->
        <transition name="fade">
            <div v-if="toast" class="fixed left-1/2 top-6 z-[120] -translate-x-1/2 rounded-full bg-destructive px-5 py-2.5 text-sm font-medium text-white shadow-lg">
                {{ toast }}
            </div>
        </transition>

        <div v-if="can('sales.create')" class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            <!-- LEFT: customer + products -->
            <div class="space-y-4 lg:col-span-3">
                <!-- 1. Who is buying? -->
                <Card>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="setShop(false)"
                            class="flex items-center justify-center gap-2 rounded-2xl px-3 py-3 text-base font-semibold transition"
                            :class="!isShop ? 'bg-brand text-white shadow' : 'bg-muted text-muted-foreground hover:bg-brand/10'">
                            <User class="size-5" /> Walk-in
                        </button>
                        <button type="button" @click="setShop(true)"
                            class="flex items-center justify-center gap-2 rounded-2xl px-3 py-3 text-base font-semibold transition"
                            :class="isShop ? 'bg-warning text-white shadow' : 'bg-muted text-muted-foreground hover:bg-warning/10'">
                            <Store class="size-5" /> Shop Customer
                        </button>
                    </div>
                    <p class="mt-2 text-center text-xs text-muted-foreground">{{ isShop ? 'Market price (e.g. 6L = 70)' : 'Own shop price (e.g. 6L = 100)' }}</p>

                    <!-- Optional: name a customer (needed for udhaar / bottle balance) -->
                    <div class="relative mt-3">
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <input
                                    :value="selectedCustomer ? selectedCustomer.name : customerSearch"
                                    @input="(e) => { customerSearch = e.target.value; form.customer_id = ''; showCustomerList = true }"
                                    @focus="showCustomerList = true"
                                    placeholder="Customer name (optional) — search…"
                                    class="w-full rounded-full border-border bg-background px-4 pr-8 text-sm focus:border-brand focus:ring-brand"
                                />
                                <button v-if="form.customer_id" type="button" @click="pickCustomer(null)" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-destructive">×</button>
                            </div>
                            <button type="button" @click="showAddCustomer = !showAddCustomer" class="rounded-full bg-brand/10 px-3 py-2 text-sm font-semibold text-brand hover:bg-brand/20" title="Add new customer">+ New</button>
                        </div>
                        <div v-if="showCustomerList && !form.customer_id" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-card shadow-lg ring-1 ring-border">
                            <button type="button" @click="pickCustomer(null)" class="block w-full px-3 py-2 text-left text-sm hover:bg-muted">Walk-in (no name)</button>
                            <button v-for="c in filteredCustomers" :key="c.id" type="button" @click="pickCustomer(c)" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-muted">
                                <span>{{ c.name }} <span v-if="c.current_balance > 0" class="text-xs font-semibold text-warning">· owes {{ format(c.current_balance) }}</span></span>
                                <span v-if="c.type === 'wholesale'" class="rounded-full bg-warning/15 px-2 py-0.5 text-[10px] font-semibold text-warning">SHOP</span>
                            </button>
                            <p v-if="!filteredCustomers.length" class="px-3 py-2 text-xs text-muted-foreground">No match — tap “+ New”.</p>
                        </div>
                    </div>
                    <!-- Selected customer's udhaar: see it before selling, collect it in one tap -->
                    <div v-if="selectedCustomer && owes !== 0" class="mt-2 rounded-xl p-3 ring-1" :class="owes > 0 ? 'bg-warning/10 ring-warning/30' : 'bg-success/10 ring-success/30'">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-semibold">{{ owes > 0 ? 'Pehle ka udhaar' : 'Advance (paid extra)' }}</span>
                            <span class="text-base font-bold" :class="owes > 0 ? 'text-warning' : 'text-success'">{{ format(Math.abs(owes)) }}</span>
                        </div>
                        <div v-if="owes > 0 && can('customers.collect')" class="mt-2 flex flex-wrap items-center gap-1.5">
                            <button type="button" @click="collect.amount = owes" class="rounded-full px-3 py-1 text-xs font-semibold" :class="collect.amount === owes ? 'bg-warning text-white' : 'bg-background ring-1 ring-border'">Full</button>
                            <input v-model.number="collect.amount" type="number" min="1" :max="owes" placeholder="Amount" class="w-24 rounded-full border-border bg-background px-3 py-1 text-xs focus:border-warning focus:ring-warning" />
                            <select v-model="collect.method" class="rounded-full border-border bg-background py-1 text-xs focus:border-warning focus:ring-warning">
                                <option value="cash">Cash</option><option value="bank">Bank / Easypaisa</option>
                            </select>
                            <button type="button" @click="receiveUdhaar" :disabled="collect.processing || !(collect.amount > 0)"
                                class="ml-auto rounded-full bg-warning px-4 py-1.5 text-xs font-bold text-white disabled:opacity-40">Receive</button>
                            <p v-if="collect.errors.amount" class="w-full text-xs text-destructive">{{ collect.errors.amount }}</p>
                        </div>
                    </div>

                    <div v-if="showAddCustomer" class="mt-2 space-y-2 rounded-xl bg-muted/50 p-3">
                        <input v-model="newCustomer.name" placeholder="Customer name *" class="w-full rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                        <div class="flex gap-2">
                            <input v-model="newCustomer.phone" placeholder="Phone" class="flex-1 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                            <select v-model="newCustomer.type" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                                <option value="retail">Walk-in / Retail</option><option value="wholesale">Shop (market price)</option>
                            </select>
                        </div>
                        <button type="button" @click="saveCustomer" :disabled="newCustomer.processing || !newCustomer.name" class="w-full rounded-md bg-brand px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50">Save Customer</button>
                    </div>
                </Card>

                <!-- 2. Tap to add -->
                <Card>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        <!-- 19L refill -->
                        <button v-if="refillService" type="button" @click="addRefill"
                            class="relative flex min-h-28 flex-col justify-between rounded-2xl bg-brand/10 p-3 text-left ring-1 ring-brand/30 transition hover:bg-brand/20 active:scale-95">
                            <Droplets class="size-6 text-brand" />
                            <div>
                                <p class="text-sm font-semibold leading-tight">19L Refill</p>
                                <p class="text-lg font-bold text-brand">{{ format(refillService.sale_price) }}</p>
                                <p class="text-[11px] text-muted-foreground">Unlimited · Today {{ soldToday(refillService.id) }}</p>
                            </div>
                            <span v-if="tileQty(refillService.id)" class="absolute right-2 top-2 rounded-full bg-brand px-2 py-0.5 text-xs font-bold text-white">{{ tileQty(refillService.id) }}</span>
                        </button>

                        <!-- Cooler refill: pick an amount -->
                        <div v-if="coolerRefill" class="relative flex min-h-28 flex-col justify-between rounded-2xl bg-success/10 p-3 ring-1 ring-success/30">
                            <button type="button" @click="coolerOpen = !coolerOpen" class="text-left">
                                <Droplets class="size-6 text-success" />
                                <p class="mt-1 text-sm font-semibold leading-tight">Cooler Refill</p>
                                <p class="text-xs text-muted-foreground">Tap → choose amount · Today {{ soldToday(coolerRefill.id) }}</p>
                            </button>
                            <div v-if="coolerOpen" class="mt-2 space-y-1.5">
                                <div class="grid grid-cols-3 gap-1">
                                    <button v-for="a in [20, 30, 40, 50, 60, 100]" :key="a" type="button" @click="addCoolerRefill(a)"
                                        class="rounded-lg bg-success px-1 py-1.5 text-xs font-bold text-white hover:opacity-90 active:scale-95">{{ a }}</button>
                                </div>
                                <div class="flex gap-1">
                                    <input v-model.number="coolerCustom" type="number" min="1" placeholder="Other" @keyup.enter="addCoolerRefill(coolerCustom)"
                                        class="w-full rounded-lg border-border bg-background py-1 text-xs focus:border-success focus:ring-success" />
                                    <button type="button" @click="addCoolerRefill(coolerCustom)" class="rounded-lg bg-success/20 px-2 text-xs font-bold text-success">Add</button>
                                </div>
                            </div>
                        </div>

                        <!-- Bottles, packs, 19L new bottle -->
                        <button v-for="p in filtered" :key="p.id" type="button" @click="addToCart(p)" :disabled="p.stock <= 0"
                            class="relative flex min-h-28 flex-col justify-between rounded-2xl p-3 text-left ring-1 transition active:scale-95 disabled:cursor-not-allowed"
                            :class="p.stock <= 0 ? 'bg-destructive/5 ring-destructive/30 opacity-60' : 'bg-card ring-border hover:ring-2 hover:ring-brand'">
                            <Package class="size-6 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-semibold leading-tight">{{ p.name }}</p>
                                <p class="text-lg font-bold" :class="isShop ? 'text-warning' : 'text-brand'">{{ format(priceFor(p)) }}</p>
                                <p class="text-[11px]" :class="p.stock <= 0 ? 'text-destructive' : (p.stock <= 10 ? 'text-warning' : 'text-muted-foreground')">
                                    {{ p.stock <= 0 ? 'Out of stock' : `Stock ${p.stock}${p.is_pack ? ' pk' : ''}` }} · Today {{ soldToday(p.id) }}
                                </p>
                            </div>
                            <span v-if="tileQty(p.id)" class="absolute right-2 top-2 rounded-full bg-brand px-2 py-0.5 text-xs font-bold text-white">{{ tileQty(p.id) }}</span>
                        </button>
                    </div>

                    <!-- Scanner / search (optional) -->
                    <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div class="flex items-center gap-2 rounded-full bg-muted/60 px-3 py-1.5">
                            <Barcode class="size-4 text-muted-foreground" />
                            <input ref="scanBox" v-model="scan" @keyup.enter="onScan" placeholder="Scan barcode…" class="w-full border-0 bg-transparent p-0 text-sm focus:ring-0" />
                        </div>
                        <input v-model="search" @keyup.enter="addFirstMatch" placeholder="Search product…" class="w-full rounded-full border-border bg-background px-4 py-1.5 text-sm focus:border-brand focus:ring-brand" />
                    </div>
                </Card>
            </div>

            <!-- RIGHT: bill -->
            <div class="lg:col-span-2">
                <Card class="lg:sticky lg:top-4">
                    <div class="space-y-2">
                        <div v-for="(it, i) in form.items" :key="i" class="rounded-xl bg-muted/40 p-2.5">
                            <div class="flex items-center gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold">{{ it.name }}</p>
                                    <!-- Owner / managers: any price + line discount (sasta dena). Cooler: amount typed by anyone. -->
                                    <div v-if="can('sales.discount')" class="mt-0.5 flex items-center gap-1">
                                        <input v-model.number="it.unit_price" type="number" step="1" min="0" title="Price per unit"
                                            class="w-20 rounded border-border bg-background py-0.5 text-xs focus:border-brand focus:ring-brand" />
                                        <input v-model.number="it.discount" type="number" step="1" min="0" placeholder="Disc" title="Discount on this line (Rs)"
                                            class="w-16 rounded border-border bg-background py-0.5 text-xs focus:border-brand focus:ring-brand" />
                                    </div>
                                    <input v-else-if="it.manual" v-model.number="it.unit_price" type="number" step="1" min="0" title="Amount (Rs)"
                                        class="mt-0.5 w-20 rounded border-border bg-background py-0.5 text-xs focus:border-brand focus:ring-brand" />
                                    <p v-else class="text-xs text-muted-foreground">{{ format(it.unit_price) }} each</p>
                                    <p v-if="belowCost(it)" class="text-[11px] font-medium text-destructive">Below cost ({{ format(costFor(it)) }}/unit)</p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button @click="dec(it)" class="flex size-8 items-center justify-center rounded-full bg-background ring-1 ring-border hover:bg-muted"><Minus class="size-4" /></button>
                                    <span class="w-7 text-center text-base font-bold">{{ it.quantity }}</span>
                                    <button @click="inc(it)" class="flex size-8 items-center justify-center rounded-full bg-background ring-1 ring-border hover:bg-muted"><Plus class="size-4" /></button>
                                </div>
                                <div class="w-20 text-right text-sm font-semibold">{{ format(lineNet(it)) }}</div>
                                <button @click="removeItem(i)" class="p-1 text-destructive"><Trash2 class="size-4" /></button>
                            </div>

                            <!-- 19L: empties returned, right on the refill line -->
                            <div v-if="it.product_id === refillService?.id && lineFor(refillService.id) === it" class="mt-2 flex items-center justify-between border-t border-border/60 pt-2 text-sm">
                                <span class="text-muted-foreground">Empty bottles returned</span>
                                <div class="flex items-center gap-1">
                                    <button @click="stepReturned(-1)" class="flex size-7 items-center justify-center rounded-full bg-background ring-1 ring-border"><Minus class="size-3.5" /></button>
                                    <span class="w-7 text-center font-bold">{{ form.bottles_returned ?? 0 }}</span>
                                    <button @click="stepReturned(1)" class="flex size-7 items-center justify-center rounded-full bg-background ring-1 ring-border"><Plus class="size-3.5" /></button>
                                </div>
                            </div>
                            <p v-if="it.product_id === refillService?.id && lineFor(refillService.id) === it && bottlesOwed > 0" class="mt-1 text-xs font-medium text-warning">Customer will owe {{ bottlesOwed }} bottle(s)</p>
                            <p v-else-if="it.product_id === refillService?.id && lineFor(refillService.id) === it && bottlesOwed < 0" class="mt-1 text-xs font-medium text-success">{{ -bottlesOwed }} old bottle(s) coming back</p>
                        </div>
                        <p v-if="!form.items.length" class="py-10 text-center text-sm text-muted-foreground">Tap a product to start the bill.</p>
                    </div>

                    <!-- Total -->
                    <div class="mt-4 rounded-2xl bg-brand/5 p-4 ring-1 ring-brand/20">
                        <div v-if="form.discount > 0 || deposit > 0" class="mb-1 space-y-0.5 text-sm text-muted-foreground">
                            <div class="flex justify-between"><span>Items</span><span>{{ format(subtotal) }}</span></div>
                            <div v-if="form.discount > 0" class="flex justify-between"><span>Discount</span><span>− {{ format(form.discount) }}</span></div>
                            <div v-if="deposit > 0" class="flex justify-between"><span>Bottle deposit</span><span>+ {{ format(deposit) }}</span></div>
                        </div>
                        <div class="flex items-end justify-between">
                            <span class="text-sm font-medium text-muted-foreground">Total</span>
                            <span class="text-3xl font-bold text-brand">{{ format(collectable) }}</span>
                        </div>
                        <!-- Change calculator: tap the note the customer gave -->
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <button v-for="n in [100, 500, 1000, 5000]" :key="n" type="button" @click="quickCash(n)"
                                class="rounded-full px-3 py-1 text-xs font-semibold" :class="received === n ? 'bg-brand text-white' : 'bg-background ring-1 ring-border hover:bg-brand/10'">{{ n }}</button>
                            <input v-model.number="received" type="number" min="0" placeholder="Cash given"
                                class="w-24 rounded-full border-border bg-background px-3 py-1 text-xs focus:border-brand focus:ring-brand" />
                        </div>
                        <div v-if="change > 0" class="mt-2 flex justify-between text-base font-semibold text-success"><span>Change to return</span><span>{{ format(change) }}</span></div>
                        <div v-if="dueAmount > 0" class="mt-2 flex justify-between text-sm font-semibold text-warning"><span>Udhaar (baqaya)</span><span>{{ format(dueAmount) }}</span></div>
                    </div>

                    <!-- One-click finish -->
                    <button type="button" @click="payCash" :disabled="form.processing || !form.items.length"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-2xl bg-success px-4 py-4 text-lg font-bold text-white shadow transition hover:opacity-90 active:scale-[0.98] disabled:opacity-40">
                        <Banknote class="size-6" />
                        {{ dueAmount > 0 ? `Paid ${format(paidNow)} — Udhaar ${format(dueAmount)}` : `Cash ${format(collectable)}` }}
                    </button>
                    <button type="button" @click="payUdhaar" :disabled="form.processing || !form.items.length"
                        class="mt-2 flex w-full items-center justify-center gap-2 rounded-2xl bg-warning/15 px-4 py-2.5 text-sm font-semibold text-warning hover:bg-warning/25 disabled:opacity-40">
                        <NotebookPen class="size-4" /> Full Udhaar{{ selectedCustomer ? ` — ${selectedCustomer.name}` : ' (select customer)' }}
                    </button>

                    <!-- Everything else, out of the way -->
                    <button type="button" @click="showMore = !showMore" class="mt-3 flex w-full items-center justify-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                        More options <ChevronDown class="size-3.5 transition" :class="showMore ? 'rotate-180' : ''" />
                    </button>
                    <div v-if="showMore" class="mt-2 space-y-2 rounded-xl bg-muted/40 p-3 text-sm">
                        <div v-if="can('sales.discount')" class="flex items-center justify-between">
                            <span class="text-muted-foreground">Discount on whole bill (Rs)</span>
                            <input v-model.number="form.discount" type="number" step="1" min="0" class="w-24 rounded-md border-border bg-background text-right text-sm focus:border-brand focus:ring-brand" />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Payment</span>
                            <select v-model="form.payment_method" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                                <option value="cash">Cash</option><option value="bank">Bank / Easypaisa / JazzCash</option><option value="card">Card</option>
                            </select>
                        </div>
                        <div v-if="warehouses.length > 1" class="flex items-center justify-between">
                            <span class="text-muted-foreground">Stock from</span>
                            <select v-model="form.warehouse_id" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <button v-if="refillService" type="button" @click="addNewBottle" class="flex w-full items-center justify-center gap-2 rounded-lg bg-warning/15 px-3 py-1.5 text-xs font-semibold text-warning hover:bg-warning/25">
                            <Container class="size-4" /> Lend company 19L bottle on deposit
                        </button>
                        <div v-if="form.new_bottle" class="flex items-center justify-between">
                            <span class="text-muted-foreground">Bottle deposit (refundable)</span>
                            <input v-model.number="form.deposit" type="number" step="1" class="w-24 rounded-md border-border bg-background text-right text-sm focus:border-brand focus:ring-brand" />
                        </div>
                        <!-- Owner-only -->
                        <template v-if="isSuperAdmin">
                            <label class="flex items-center gap-2 text-xs font-medium">
                                <input type="checkbox" v-model="atCost" class="rounded border-border text-brand focus:ring-brand" />
                                Bill at cost price (owner)
                            </label>
                            <button type="button" @click="internalUseFree" class="w-full rounded-lg bg-muted px-3 py-1.5 text-xs font-semibold hover:bg-muted/70">
                                Internal / home use — FREE (stock only, no sale)
                            </button>
                        </template>
                        <button type="button" @click="resetCart" class="w-full rounded-lg px-3 py-1.5 text-xs font-medium text-destructive hover:bg-destructive/10">Clear bill</button>
                    </div>
                </Card>
            </div>
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
                            <td class="py-3 pr-4 capitalize">{{ inv.type === 'wholesale' ? 'Shop customer' : inv.type }}</td>
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
