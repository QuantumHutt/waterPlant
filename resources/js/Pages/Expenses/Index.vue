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
import { Plus } from 'lucide-vue-next'

const props = defineProps({ expenses: Object, categories: Array })
const { can } = usePermissions()
const { format } = useCurrency()

const showModal = ref(false)
const form = useForm({ category_id: '', amount: null, paid_via: 'cash', vendor: '', spent_at: new Date().toISOString().slice(0, 10), note: '' })
function open() { form.reset(); form.category_id = props.categories[0]?.id ?? ''; showModal.value = true }
function submit() { form.post(route('expenses.store'), { onSuccess: () => (showModal.value = false), preserveScroll: true }) }
</script>

<template>
    <Head title="Expenses" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-2xl font-semibold tracking-tight">Expenses</h2><p class="text-sm text-muted-foreground">Electricity, diesel, salaries &amp; more — auto-posted to ledger.</p></div>
                <PrimaryButton v-if="can('expenses.create')" @click="open" class="!rounded-full"><Plus class="size-4 mr-1" /> New Expense</PrimaryButton>
            </div>
        </template>
        <Card>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-muted-foreground border-b border-border">
                    <th class="py-3 pr-4 font-medium">Ref</th><th class="py-3 pr-4 font-medium">Category</th><th class="py-3 pr-4 font-medium">Vendor</th>
                    <th class="py-3 pr-4 font-medium">Date</th><th class="py-3 pr-4 font-medium">Paid Via</th><th class="py-3 pr-0 font-medium text-right">Amount</th>
                </tr></thead>
                <tbody>
                    <tr v-for="e in expenses.data" :key="e.id" class="border-b border-border/60">
                        <td class="py-3 pr-4 font-mono text-xs">{{ e.reference }}</td><td class="py-3 pr-4">{{ e.category?.name }}</td>
                        <td class="py-3 pr-4 text-muted-foreground">{{ e.vendor ?? '—' }}</td><td class="py-3 pr-4">{{ e.spent_at }}</td>
                        <td class="py-3 pr-4 capitalize">{{ e.paid_via }}</td><td class="py-3 pr-0 text-right font-medium">{{ format(e.amount) }}</td>
                    </tr>
                    <tr v-if="!expenses.data.length"><td colspan="6" class="py-8 text-center text-muted-foreground">No expenses recorded.</td></tr>
                </tbody>
            </table>
        </Card>

        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">New Expense</h3>
                <div><InputLabel value="Category" /><select v-model="form.category_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><InputLabel value="Amount" /><TextInput v-model.number="form.amount" type="number" step="0.01" class="mt-1 block w-full" required /></div>
                    <div><InputLabel value="Paid Via" /><select v-model="form.paid_via" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option value="cash">Cash</option><option value="bank">Bank</option></select></div>
                    <div><InputLabel value="Vendor" /><TextInput v-model="form.vendor" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Date" /><TextInput v-model="form.spent_at" type="date" class="mt-1 block w-full" /></div>
                </div>
                <div><InputLabel value="Note" /><TextInput v-model="form.note" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="form.processing">Save</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
