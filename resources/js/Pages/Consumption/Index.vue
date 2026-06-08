<script setup>
import { Head, useForm } from '@inertiajs/vue3'
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
import { Plus, Home } from 'lucide-vue-next'

const props = defineProps({ consumptions: Object, warehouses: Array, products: Array, summary: Object })
const { can } = usePermissions()
const { format } = useCurrency()

const showModal = ref(false)
const form = useForm({ warehouse_id: '', product_id: '', quantity: 1, pay_at_cost: false, reason: '' })
const selected = computed(() => props.products.find((p) => p.id === form.product_id))
const estCost = computed(() => (selected.value ? Number(selected.value.cost_price) * (Number(form.quantity) || 0) : 0))

function open() {
    form.reset()
    form.warehouse_id = props.warehouses[0]?.id ?? ''
    form.product_id = props.products[0]?.id ?? ''
    showModal.value = true
}
function submit() { form.post(route('consumption.store'), { onSuccess: () => (showModal.value = false), preserveScroll: true }) }
</script>

<template>
    <Head title="Internal Consumption" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">Internal Consumption</h2>
                    <p class="text-sm text-muted-foreground">Owner / internal use — stock minus, <strong>no sale, no profit</strong>. Cost recorded at cost price.</p>
                </div>
                <PrimaryButton v-if="can('consumption.create')" @click="open" class="!rounded-full"><Plus class="size-4 mr-1" /> New Consumption</PrimaryButton>
            </div>
        </template>

        <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Total Consumed (Cost)</p><p class="text-xl font-semibold">{{ format(summary.total_cost) }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Paid Back (at cost)</p><p class="text-xl font-semibold text-success">{{ format(summary.total_paid) }}</p></div>
            <div class="rounded-2xl bg-card ring-1 ring-border p-4"><p class="text-xs text-muted-foreground">Free / Drawings</p><p class="text-xl font-semibold text-warning">{{ format(summary.total_cost - summary.total_paid) }}</p></div>
        </div>

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-3 pr-4 font-medium">Ref</th><th class="py-3 pr-4 font-medium">Date</th><th class="py-3 pr-4 font-medium">Product</th>
                        <th class="py-3 pr-4 font-medium text-right">Qty</th><th class="py-3 pr-4 font-medium text-right">Cost</th>
                        <th class="py-3 pr-4 font-medium text-right">Paid</th><th class="py-3 pr-4 font-medium">Reason</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="c in consumptions.data" :key="c.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-mono text-xs">{{ c.reference }}</td>
                            <td class="py-3 pr-4">{{ c.consumed_at }}</td>
                            <td class="py-3 pr-4 font-medium">{{ c.product?.name }}</td>
                            <td class="py-3 pr-4 text-right">{{ c.quantity }}</td>
                            <td class="py-3 pr-4 text-right">{{ format(c.total_cost) }}</td>
                            <td class="py-3 pr-4 text-right">
                                <span :class="c.is_paid ? 'text-success' : 'text-warning'">{{ c.amount_paid > 0 ? format(c.amount_paid) : 'Free' }}</span>
                            </td>
                            <td class="py-3 pr-4 text-muted-foreground">{{ c.reason }}</td>
                        </tr>
                        <tr v-if="!consumptions.data.length"><td colspan="7" class="py-8 text-center text-muted-foreground">No internal consumption recorded.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold flex items-center gap-2"><Home class="size-5 text-brand" /> Internal / Owner Use</h3>
                <div><InputLabel value="Warehouse" /><select v-model="form.warehouse_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
                <div><InputLabel value="Product" /><select v-model="form.product_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><InputLabel value="Quantity" /><TextInput v-model.number="form.quantity" type="number" step="0.001" class="mt-1 block w-full" required /></div>
                    <div class="flex items-end"><div class="rounded-xl bg-muted px-3 py-2 text-sm w-full text-center">Cost: <span class="font-semibold">{{ format(estCost) }}</span></div></div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.pay_at_cost" class="rounded border-border text-brand focus:ring-brand" />
                    Owner ne cost pay kar diya ({{ format(estCost) }}) — warna free (drawings)
                </label>
                <div><InputLabel value="Reason" /><TextInput v-model="form.reason" placeholder="e.g. ghar ke liye" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="form.processing">Record (No Sale)</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
