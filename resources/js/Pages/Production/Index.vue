<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import Modal from '@/Components/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { Plus, X, FlaskConical } from 'lucide-vue-next'

const props = defineProps({ batches: Object, warehouses: Array, finishedProducts: Array, rawProducts: Array })
const { can } = usePermissions()
const { format } = useCurrency()

const showModal = ref(false)
const form = useForm({
    warehouse_id: '', production_date: new Date().toISOString().slice(0, 10), expiry_date: '',
    raw_water_litres: 0, filtered_water_litres: 0, wastage_litres: 0, bottles_produced: 0, note: '',
    outputs: [{ product_id: '', quantity: null }],
    consumptions: [{ product_id: '', quantity: null }],
})
function open() {
    form.reset()
    form.warehouse_id = props.warehouses[0]?.id ?? ''
    form.outputs = [{ product_id: props.finishedProducts[0]?.id ?? '', quantity: null }]
    form.consumptions = [{ product_id: props.rawProducts[0]?.id ?? '', quantity: null }]
    showModal.value = true
}
function submit() { form.post(route('production.store'), { onSuccess: () => (showModal.value = false), preserveScroll: true }) }
</script>

<template>
    <Head title="Production" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-2xl font-semibold tracking-tight">Production</h2><p class="text-sm text-muted-foreground">Batches — consume raw, produce finished.</p></div>
                <PrimaryButton v-if="can('production.create')" @click="open" class="!rounded-full"><Plus class="size-4 mr-1" /> New Batch</PrimaryButton>
            </div>
        </template>

        <Card>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-3 pr-4 font-medium">Batch #</th><th class="py-3 pr-4 font-medium">Date</th>
                        <th class="py-3 pr-4 font-medium text-right">Raw (L)</th><th class="py-3 pr-4 font-medium text-right">Filtered (L)</th>
                        <th class="py-3 pr-4 font-medium text-right">Bottles</th><th class="py-3 pr-0 font-medium text-right">Cost</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="b in batches.data" :key="b.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-mono text-xs">{{ b.batch_number }}</td>
                            <td class="py-3 pr-4">{{ b.production_date }}</td>
                            <td class="py-3 pr-4 text-right">{{ b.raw_water_litres }}</td>
                            <td class="py-3 pr-4 text-right">{{ b.filtered_water_litres }}</td>
                            <td class="py-3 pr-4 text-right">{{ b.bottles_produced }}</td>
                            <td class="py-3 pr-0 text-right font-medium">{{ format(b.production_cost) }}</td>
                        </tr>
                        <tr v-if="!batches.data.length"><td colspan="6" class="py-8 text-center text-muted-foreground">No batches yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="showModal" max-width="2xl" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold flex items-center gap-2"><FlaskConical class="size-5 text-brand" /> New Production Batch</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div><InputLabel value="Warehouse" /><select v-model="form.warehouse_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
                    <div><InputLabel value="Production Date" /><TextInput v-model="form.production_date" type="date" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Expiry Date" /><TextInput v-model="form.expiry_date" type="date" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Raw Water (L)" /><TextInput v-model.number="form.raw_water_litres" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Filtered (L)" /><TextInput v-model.number="form.filtered_water_litres" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Wastage (L)" /><TextInput v-model.number="form.wastage_litres" type="number" step="0.01" class="mt-1 block w-full" /></div>
                </div>

                <div class="rounded-2xl bg-muted/50 p-4 space-y-2">
                    <p class="text-sm font-medium">Finished Output</p>
                    <div v-for="(o, i) in form.outputs" :key="i" class="grid grid-cols-12 gap-2 items-center">
                        <select v-model="o.product_id" class="col-span-8 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand"><option v-for="p in finishedProducts" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                        <TextInput v-model.number="o.quantity" type="number" step="0.001" placeholder="qty" class="col-span-3 text-sm" />
                        <button type="button" @click="form.outputs.splice(i, 1)" class="col-span-1 text-destructive"><X class="size-4" /></button>
                    </div>
                    <button type="button" @click="form.outputs.push({ product_id: finishedProducts[0]?.id, quantity: null })" class="text-sm text-brand font-medium flex items-center gap-1"><Plus class="size-4" /> Add output</button>
                </div>

                <div class="rounded-2xl bg-muted/50 p-4 space-y-2">
                    <p class="text-sm font-medium">Raw / Consumable Used</p>
                    <div v-for="(c, i) in form.consumptions" :key="i" class="grid grid-cols-12 gap-2 items-center">
                        <select v-model="c.product_id" class="col-span-8 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand"><option v-for="p in rawProducts" :key="p.id" :value="p.id">{{ p.name }}</option></select>
                        <TextInput v-model.number="c.quantity" type="number" step="0.001" placeholder="qty" class="col-span-3 text-sm" />
                        <button type="button" @click="form.consumptions.splice(i, 1)" class="col-span-1 text-destructive"><X class="size-4" /></button>
                    </div>
                    <button type="button" @click="form.consumptions.push({ product_id: rawProducts[0]?.id, quantity: null })" class="text-sm text-brand font-medium flex items-center gap-1"><Plus class="size-4" /> Add material</button>
                </div>

                <div><InputLabel value="Bottles Produced" /><TextInput v-model.number="form.bottles_produced" type="number" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="form.processing">Record Batch</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
