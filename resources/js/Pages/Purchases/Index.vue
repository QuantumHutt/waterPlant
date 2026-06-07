<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import Modal from '@/Components/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { Plus, PackageCheck, X } from 'lucide-vue-next'

const props = defineProps({ orders: Object, suppliers: Array, warehouses: Array, products: Array })
const { can } = usePermissions()
const { format } = useCurrency()

const showModal = ref(false)
const form = useForm({
    supplier_id: '', warehouse_id: '', order_date: new Date().toISOString().slice(0, 10),
    discount: 0, tax_total: 0, note: '', items: [{ product_id: '', quantity: 1, unit_cost: 0 }],
})
function open() {
    form.reset()
    form.supplier_id = props.suppliers[0]?.id ?? ''
    form.warehouse_id = props.warehouses[0]?.id ?? ''
    form.items = [{ product_id: props.products[0]?.id ?? '', quantity: 1, unit_cost: props.products[0]?.cost_price ?? 0 }]
    showModal.value = true
}
function addItem() { form.items.push({ product_id: props.products[0]?.id ?? '', quantity: 1, unit_cost: 0 }) }
const subtotal = computed(() => form.items.reduce((s, i) => s + (Number(i.quantity) || 0) * (Number(i.unit_cost) || 0), 0))
const grand = computed(() => subtotal.value - (Number(form.discount) || 0) + (Number(form.tax_total) || 0))
function submit() { form.post(route('purchases.store'), { onSuccess: () => (showModal.value = false), preserveScroll: true }) }
function receive(po) { router.post(route('purchases.receive', po.id), {}, { preserveScroll: true }) }

const statusTone = (s) => ({ draft: 'bg-warning/15 text-warning', received: 'bg-success/10 text-success', cancelled: 'bg-destructive/10 text-destructive' }[s] ?? 'bg-muted')
</script>

<template>
    <Head title="Purchases" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-2xl font-semibold tracking-tight">Purchase Orders</h2><p class="text-sm text-muted-foreground">Order &amp; receive raw materials.</p></div>
                <PrimaryButton v-if="can('purchases.create')" @click="open" class="!rounded-full"><Plus class="size-4 mr-1" /> New PO</PrimaryButton>
            </div>
        </template>
        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-3 pr-4 font-medium">PO #</th><th class="py-3 pr-4 font-medium">Supplier</th><th class="py-3 pr-4 font-medium">Date</th>
                        <th class="py-3 pr-4 font-medium text-right">Total</th><th class="py-3 pr-4 font-medium">Status</th><th class="py-3 pr-0 font-medium text-right">Actions</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="o in orders.data" :key="o.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-mono text-xs">{{ o.po_number }}</td>
                            <td class="py-3 pr-4 font-medium">{{ o.supplier?.name }}</td>
                            <td class="py-3 pr-4">{{ o.order_date }}</td>
                            <td class="py-3 pr-4 text-right font-medium">{{ format(o.grand_total) }}</td>
                            <td class="py-3 pr-4"><span class="rounded-full px-2 py-0.5 text-xs capitalize" :class="statusTone(o.status)">{{ o.status }}</span></td>
                            <td class="py-3 pr-0 text-right">
                                <button v-if="o.status === 'draft' && can('purchases.receive')" @click="receive(o)" class="inline-flex items-center gap-1 rounded-full bg-success/10 text-success px-3 py-1.5 text-xs font-medium hover:bg-success/20">
                                    <PackageCheck class="size-4" /> Receive
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!orders.data.length"><td colspan="6" class="py-8 text-center text-muted-foreground">No purchase orders yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="showModal" max-width="2xl" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold">New Purchase Order</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div><InputLabel value="Supplier" /><select v-model="form.supplier_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                    <div><InputLabel value="Warehouse" /><select v-model="form.warehouse_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
                    <div><InputLabel value="Order Date" /><TextInput v-model="form.order_date" type="date" class="mt-1 block w-full" /></div>
                </div>

                <div class="rounded-2xl bg-muted/50 p-4 space-y-2">
                    <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-2 items-center">
                        <select v-model="it.product_id" class="col-span-6 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand"><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                        <TextInput v-model.number="it.quantity" type="number" step="0.001" placeholder="qty" class="col-span-2 text-sm" />
                        <TextInput v-model.number="it.unit_cost" type="number" step="0.0001" placeholder="cost" class="col-span-3 text-sm" />
                        <button type="button" @click="form.items.splice(i, 1)" class="col-span-1 text-destructive"><X class="size-4" /></button>
                    </div>
                    <button type="button" @click="addItem" class="text-sm text-brand font-medium flex items-center gap-1"><Plus class="size-4" /> Add line</button>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div><InputLabel value="Discount" /><TextInput v-model.number="form.discount" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Tax" /><TextInput v-model.number="form.tax_total" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div class="flex items-end justify-end"><div class="text-right"><p class="text-xs text-muted-foreground">Grand Total</p><p class="text-lg font-semibold">{{ format(grand) }}</p></div></div>
                </div>

                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="form.processing">Create PO</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
