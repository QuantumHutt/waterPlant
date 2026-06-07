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
import { Plus, Container } from 'lucide-vue-next'

const props = defineProps({ transactions: Object, balances: Array, customers: Array })
const { can } = usePermissions()
const { format } = useCurrency()

const showModal = ref(false)
const form = useForm({ customer_id: '', type: 'issue', quantity: 1, deposit_amount: 0, note: '' })
function open() { form.reset(); form.customer_id = props.customers[0]?.id ?? ''; showModal.value = true }
function submit() { form.post(route('gallons.store'), { onSuccess: () => (showModal.value = false), preserveScroll: true }) }
const typeTone = (t) => ({ issue: 'bg-brand/10 text-brand', return: 'bg-success/10 text-success', deposit: 'bg-success/10 text-success', missing: 'bg-destructive/10 text-destructive', damaged: 'bg-destructive/10 text-destructive' }[t] ?? 'bg-muted')
</script>

<template>
    <Head title="Gallons" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-2xl font-semibold tracking-tight">Gallon Tracking</h2><p class="text-sm text-muted-foreground">Issues, returns, deposits &amp; losses.</p></div>
                <PrimaryButton v-if="can('gallons.manage')" @click="open" class="!rounded-full"><Plus class="size-4 mr-1" /> New Transaction</PrimaryButton>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2" title="Recent Transactions">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-muted-foreground border-b border-border">
                            <th class="py-3 pr-4 font-medium">Date</th><th class="py-3 pr-4 font-medium">Customer</th>
                            <th class="py-3 pr-4 font-medium">Type</th><th class="py-3 pr-4 font-medium text-right">Qty</th><th class="py-3 pr-0 font-medium text-right">Deposit</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="t in transactions.data" :key="t.id" class="border-b border-border/60">
                                <td class="py-3 pr-4">{{ t.transacted_at }}</td>
                                <td class="py-3 pr-4 font-medium">{{ t.customer?.name }}</td>
                                <td class="py-3 pr-4"><span class="rounded-full px-2 py-0.5 text-xs capitalize" :class="typeTone(t.type)">{{ t.type.replace('_', ' ') }}</span></td>
                                <td class="py-3 pr-4 text-right">{{ t.quantity }}</td>
                                <td class="py-3 pr-0 text-right">{{ t.deposit_amount > 0 ? format(t.deposit_amount) : '—' }}</td>
                            </tr>
                            <tr v-if="!transactions.data.length"><td colspan="5" class="py-8 text-center text-muted-foreground">No transactions yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card title="Outstanding Gallons">
                <ul class="space-y-2 text-sm">
                    <li v-for="b in balances" :key="b.id" class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><Container class="size-4 text-brand" /> {{ b.customer?.name }}</span>
                        <span class="font-medium">{{ b.held_qty }}</span>
                    </li>
                    <li v-if="!balances.length" class="py-6 text-center text-muted-foreground">No gallons currently out.</li>
                </ul>
            </Card>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Gallon Transaction</h3>
                <div><InputLabel value="Customer" /><select v-model="form.customer_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><InputLabel value="Type" />
                        <select v-model="form.type" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option value="issue">Issue</option><option value="return">Return</option>
                            <option value="deposit">Deposit</option><option value="deposit_refund">Deposit Refund</option>
                            <option value="missing">Missing</option><option value="damaged">Damaged</option>
                        </select>
                    </div>
                    <div><InputLabel value="Quantity" /><TextInput v-model.number="form.quantity" type="number" class="mt-1 block w-full" /></div>
                    <div class="col-span-2"><InputLabel value="Deposit Amount" /><TextInput v-model.number="form.deposit_amount" type="number" step="0.01" class="mt-1 block w-full" /></div>
                </div>
                <div><InputLabel value="Note" /><TextInput v-model="form.note" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="form.processing">Record</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
