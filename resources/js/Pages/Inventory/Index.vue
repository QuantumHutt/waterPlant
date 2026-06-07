<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import Modal from '@/Components/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { ArrowDownToLine, ArrowUpFromLine, ArrowLeftRight, SlidersHorizontal, Search, AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
    stock: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    warehouses: { type: Object, required: true },
    products: { type: Array, default: () => [] },
})

const { can } = usePermissions()
const { format } = useCurrency()

const search = ref(props.filters.search ?? '')
let t = null
watch(search, (v) => {
    clearTimeout(t)
    t = setTimeout(() => router.get(route('inventory.index'), { search: v }, { preserveState: true, replace: true }), 350)
})

const wh = () => props.warehouses.data?.[0]?.id ?? ''

// --- Stock In / Out ---
const moveModal = ref(null) // 'in' | 'out' | null
const moveForm = useForm({ warehouse_id: '', product_id: '', quantity: null, unit_cost: 0, note: '' })
function openMove(dir) {
    moveModal.value = dir
    moveForm.reset()
    moveForm.warehouse_id = wh()
    moveForm.product_id = props.products[0]?.id ?? ''
}
function submitMove() {
    const url = moveModal.value === 'in' ? route('inventory.stock-in') : route('inventory.stock-out')
    moveForm.post(url, { onSuccess: () => (moveModal.value = null), preserveScroll: true })
}

// --- Adjust ---
const adjustModal = ref(false)
const adjustForm = useForm({ warehouse_id: '', type: 'adjustment', reason: '', items: [{ product_id: '', quantity: null }] })
function openAdjust() {
    adjustForm.reset()
    adjustForm.warehouse_id = wh()
    adjustForm.items = [{ product_id: props.products[0]?.id ?? '', quantity: null }]
    adjustModal.value = true
}
function submitAdjust() {
    adjustForm.post(route('inventory.adjust'), { onSuccess: () => (adjustModal.value = false), preserveScroll: true })
}

// --- Transfer ---
const transferModal = ref(false)
const transferForm = useForm({ from_warehouse_id: '', to_warehouse_id: '', note: '', items: [{ product_id: '', quantity: null }] })
function openTransfer() {
    transferForm.reset()
    transferForm.from_warehouse_id = wh()
    transferForm.to_warehouse_id = props.warehouses.data?.[1]?.id ?? ''
    transferForm.items = [{ product_id: props.products[0]?.id ?? '', quantity: null }]
    transferModal.value = true
}
function submitTransfer() {
    transferForm.post(route('inventory.transfer'), { onSuccess: () => (transferModal.value = false), preserveScroll: true })
}

const isLow = (row) => row.product && Number(row.quantity) <= Number(row.product.reorder_level)
</script>

<template>
    <Head title="Inventory" />

    <AppLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">Inventory</h2>
                    <p class="text-sm text-muted-foreground">Stock on hand, movements, transfers &amp; adjustments.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-if="can('inventory.create')" @click="openMove('in')" class="flex items-center gap-1.5 rounded-full bg-success/10 text-success px-3 py-2 text-sm font-medium hover:bg-success/20"><ArrowDownToLine class="size-4" /> Stock In</button>
                    <button v-if="can('inventory.create')" @click="openMove('out')" class="flex items-center gap-1.5 rounded-full bg-warning/15 text-warning px-3 py-2 text-sm font-medium hover:bg-warning/25"><ArrowUpFromLine class="size-4" /> Stock Out</button>
                    <button v-if="can('inventory.transfer')" @click="openTransfer" class="flex items-center gap-1.5 rounded-full bg-brand/10 text-brand px-3 py-2 text-sm font-medium hover:bg-brand/20"><ArrowLeftRight class="size-4" /> Transfer</button>
                    <button v-if="can('inventory.adjust')" @click="openAdjust" class="flex items-center gap-1.5 rounded-full bg-muted px-3 py-2 text-sm font-medium hover:bg-muted/70"><SlidersHorizontal class="size-4" /> Adjust</button>
                </div>
            </div>
        </template>

        <Card>
            <div class="mb-4 relative max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input v-model="search" type="text" placeholder="Search product…" class="w-full rounded-full border-border bg-background pl-9 pr-4 py-2 text-sm focus:border-brand focus:ring-brand" />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground border-b border-border">
                            <th class="py-3 pr-4 font-medium">Product</th>
                            <th class="py-3 pr-4 font-medium">Warehouse</th>
                            <th class="py-3 pr-4 font-medium">Variant</th>
                            <th class="py-3 pr-4 font-medium text-right">On Hand</th>
                            <th class="py-3 pr-4 font-medium text-right">Avg Cost</th>
                            <th class="py-3 pr-0 font-medium text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in stock.data" :key="row.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-medium">
                                <span class="flex items-center gap-2">
                                    {{ row.product?.name }}
                                    <AlertTriangle v-if="isLow(row)" class="size-4 text-destructive" title="Low stock" />
                                </span>
                                <span class="text-xs text-muted-foreground font-mono">{{ row.product?.sku }}</span>
                            </td>
                            <td class="py-3 pr-4">{{ row.warehouse }}</td>
                            <td class="py-3 pr-4">{{ row.variant ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right font-medium" :class="isLow(row) ? 'text-destructive' : ''">{{ row.quantity }}</td>
                            <td class="py-3 pr-4 text-right">{{ format(row.avg_cost) }}</td>
                            <td class="py-3 pr-0 text-right">{{ format(row.quantity * row.avg_cost) }}</td>
                        </tr>
                        <tr v-if="!stock.data.length"><td colspan="6" class="py-8 text-center text-muted-foreground">No stock records yet. Use “Stock In” to add inventory.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Stock In / Out modal -->
        <Modal :show="!!moveModal" @close="moveModal = null">
            <form @submit.prevent="submitMove" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">{{ moveModal === 'in' ? 'Stock In' : 'Stock Out' }}</h3>
                <div>
                    <InputLabel value="Warehouse" />
                    <select v-model="moveForm.warehouse_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                        <option v-for="w in warehouses.data" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Product" />
                    <select v-model="moveForm.product_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><InputLabel value="Quantity" /><TextInput v-model.number="moveForm.quantity" type="number" step="0.001" class="mt-1 block w-full" required /><InputError :message="moveForm.errors.quantity" class="mt-1" /></div>
                    <div v-if="moveModal === 'in'"><InputLabel value="Unit Cost" /><TextInput v-model.number="moveForm.unit_cost" type="number" step="0.0001" class="mt-1 block w-full" /></div>
                </div>
                <div><InputLabel value="Note" /><TextInput v-model="moveForm.note" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="moveModal = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="moveForm.processing">Confirm</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Adjust modal -->
        <Modal :show="adjustModal" @close="adjustModal = false">
            <form @submit.prevent="submitAdjust" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Stock Adjustment</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Warehouse" />
                        <select v-model="adjustForm.warehouse_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option v-for="w in warehouses.data" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Type" />
                        <select v-model="adjustForm.type" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option value="adjustment">Adjustment</option>
                            <option value="damage">Damage</option>
                            <option value="wastage">Wastage</option>
                            <option value="audit">Audit (set count)</option>
                        </select>
                    </div>
                </div>
                <div v-for="(it, i) in adjustForm.items" :key="i" class="grid grid-cols-12 gap-2 items-end">
                    <div class="col-span-7">
                        <InputLabel value="Product" />
                        <select v-model="it.product_id" class="mt-1 block w-full rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-span-4">
                        <InputLabel :value="adjustForm.type === 'audit' ? 'Counted Qty' : 'Delta (+/-)'" />
                        <TextInput v-if="adjustForm.type === 'audit'" v-model.number="it.counted_qty" type="number" step="0.001" class="mt-1 block w-full text-sm" />
                        <TextInput v-else v-model.number="it.quantity" type="number" step="0.001" class="mt-1 block w-full text-sm" />
                    </div>
                    <button type="button" class="col-span-1 pb-2 text-brand text-sm" @click="adjustForm.items.push({ product_id: products[0]?.id, quantity: null })">+</button>
                </div>
                <div><InputLabel value="Reason" /><TextInput v-model="adjustForm.reason" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="adjustModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="adjustForm.processing">Apply</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Transfer modal -->
        <Modal :show="transferModal" @close="transferModal = false">
            <form @submit.prevent="submitTransfer" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Stock Transfer</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="From" />
                        <select v-model="transferForm.from_warehouse_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option v-for="w in warehouses.data" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="To" />
                        <select v-model="transferForm.to_warehouse_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option v-for="w in warehouses.data" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                        <InputError :message="transferForm.errors.from_warehouse_id" class="mt-1" />
                    </div>
                </div>
                <div v-for="(it, i) in transferForm.items" :key="i" class="grid grid-cols-12 gap-2 items-end">
                    <div class="col-span-8">
                        <InputLabel value="Product" />
                        <select v-model="it.product_id" class="mt-1 block w-full rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand">
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-span-3"><InputLabel value="Qty" /><TextInput v-model.number="it.quantity" type="number" step="0.001" class="mt-1 block w-full text-sm" /></div>
                    <button type="button" class="col-span-1 pb-2 text-brand text-sm" @click="transferForm.items.push({ product_id: products[0]?.id, quantity: null })">+</button>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="transferModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="transferForm.processing">Transfer</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
