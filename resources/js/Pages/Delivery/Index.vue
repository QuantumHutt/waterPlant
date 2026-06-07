<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import Modal from '@/Components/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { Plus, Truck, CheckCircle2, X } from 'lucide-vue-next'

const props = defineProps({ assignments: Array, routes: Array, drivers: Array, customers: Array })
const { can } = usePermissions()

const routeModal = ref(false)
const routeForm = useForm({ name: '', area: '' })
function submitRoute() { routeForm.post(route('delivery.routes.store'), { onSuccess: () => (routeModal.value = false), preserveScroll: true }) }

const asgModal = ref(false)
const asgForm = useForm({ route_id: '', driver_id: '', vehicle: '', assigned_date: new Date().toISOString().slice(0, 10), items: [{ customer_id: '', gallons_out: 1 }] })
function openAsg() {
    asgForm.reset()
    asgForm.driver_id = props.drivers[0]?.id ?? ''
    asgForm.assigned_date = new Date().toISOString().slice(0, 10)
    asgForm.items = [{ customer_id: props.customers[0]?.id ?? '', gallons_out: 1 }]
    asgModal.value = true
}
function submitAsg() { asgForm.post(route('delivery.assignments.store'), { onSuccess: () => (asgModal.value = false), preserveScroll: true }) }

function confirmItem(item) { router.post(route('delivery.confirm', item.id), { gallons_in: item.gallons_out }, { preserveScroll: true }) }
const tone = (s) => ({ assigned: 'bg-warning/15 text-warning', in_transit: 'bg-brand/10 text-brand', completed: 'bg-success/10 text-success', partial: 'bg-warning/15 text-warning' }[s] ?? 'bg-muted')
</script>

<template>
    <Head title="Delivery" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-2xl font-semibold tracking-tight">Delivery</h2><p class="text-sm text-muted-foreground">Routes, assignments &amp; confirmation.</p></div>
                <div class="flex gap-2">
                    <PrimaryButton v-if="can('delivery.create')" @click="openAsg" class="!rounded-full"><Plus class="size-4 mr-1" /> Assign</PrimaryButton>
                    <button v-if="can('delivery.create')" @click="routeModal = true" class="rounded-full bg-card ring-1 ring-border px-4 py-2 text-sm font-medium hover:bg-muted">+ Route</button>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <Card v-for="a in assignments" :key="a.id">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <Truck class="size-5 text-brand" />
                        <div><p class="font-medium">{{ a.reference }} · {{ a.driver?.name }}</p><p class="text-xs text-muted-foreground">{{ a.route?.name ?? 'No route' }} · {{ a.assigned_date }}</p></div>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs capitalize" :class="tone(a.status)">{{ a.status.replace('_', ' ') }}</span>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="it in a.items" :key="it.id" class="border-t border-border/60">
                            <td class="py-2">{{ it.customer?.name }}</td>
                            <td class="py-2 text-right text-muted-foreground">Out: {{ it.gallons_out }} / In: {{ it.gallons_in }}</td>
                            <td class="py-2 text-right">
                                <span v-if="it.status === 'delivered'" class="text-success inline-flex items-center gap-1 text-xs"><CheckCircle2 class="size-4" /> Delivered</span>
                                <button v-else-if="can('delivery.confirm')" @click="confirmItem(it)" class="rounded-full bg-success/10 text-success px-3 py-1 text-xs font-medium hover:bg-success/20">Confirm</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>
            <Card v-if="!assignments.length"><p class="py-8 text-center text-muted-foreground">No delivery assignments.</p></Card>
        </div>

        <Modal :show="routeModal" @close="routeModal = false">
            <form @submit.prevent="submitRoute" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">New Route</h3>
                <div><InputLabel value="Name" /><TextInput v-model="routeForm.name" class="mt-1 block w-full" required /></div>
                <div><InputLabel value="Area" /><TextInput v-model="routeForm.area" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="routeModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="routeForm.processing">Save</PrimaryButton></div>
            </form>
        </Modal>

        <Modal :show="asgModal" @close="asgModal = false">
            <form @submit.prevent="submitAsg" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">New Assignment</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div><InputLabel value="Driver" /><select v-model="asgForm.driver_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option></select></div>
                    <div><InputLabel value="Route" /><select v-model="asgForm.route_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option value="">—</option><option v-for="r in routes" :key="r.id" :value="r.id">{{ r.name }}</option></select></div>
                    <div><InputLabel value="Vehicle" /><TextInput v-model="asgForm.vehicle" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Date" /><TextInput v-model="asgForm.assigned_date" type="date" class="mt-1 block w-full" /></div>
                </div>
                <div class="rounded-2xl bg-muted/50 p-3 space-y-2">
                    <div v-for="(it, i) in asgForm.items" :key="i" class="grid grid-cols-12 gap-2 items-center">
                        <select v-model="it.customer_id" class="col-span-8 rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand"><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                        <TextInput v-model.number="it.gallons_out" type="number" placeholder="gallons" class="col-span-3 text-sm" />
                        <button type="button" @click="asgForm.items.splice(i, 1)" class="col-span-1 text-destructive"><X class="size-4" /></button>
                    </div>
                    <button type="button" @click="asgForm.items.push({ customer_id: customers[0]?.id, gallons_out: 1 })" class="text-sm text-brand font-medium flex items-center gap-1"><Plus class="size-4" /> Add stop</button>
                </div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="asgModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="asgForm.processing">Assign</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
