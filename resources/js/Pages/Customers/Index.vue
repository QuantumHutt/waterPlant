<script setup>
import { Head, useForm, router, Link } from '@inertiajs/vue3'
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
import { Plus, Pencil, Wallet, BookText, Search } from 'lucide-vue-next'

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    ledgerCustomer: { type: Object, default: null },
    ledger: { type: Array, default: () => [] },
})

const { can } = usePermissions()
const { format } = useCurrency()

const search = ref(props.filters.search ?? '')
let t = null
watch(search, (v) => { clearTimeout(t); t = setTimeout(() => router.get(route('customers.index'), { search: v }, { preserveState: true, replace: true }), 350) })

const showModal = ref(false)
const editing = ref(null)
const form = useForm({ code: '', name: '', type: 'retail', phone: '', email: '', address: '', credit_limit: 0, opening_balance: 0, is_active: true })

function openCreate() { editing.value = null; form.reset(); showModal.value = true }
function openEdit(c) { editing.value = c; form.reset(); Object.assign(form, { code: c.code, name: c.name, type: c.type, phone: c.phone, email: c.email, address: c.address, credit_limit: c.credit_limit, is_active: c.is_active }); showModal.value = true }
function submit() {
    const opts = { onSuccess: () => (showModal.value = false), preserveScroll: true }
    editing.value ? form.put(route('customers.update', editing.value.id), opts) : form.post(route('customers.store'), opts)
}

const payModal = ref(null)
const payForm = useForm({ amount: null, method: 'cash', note: '' })
function openPay(c) { payModal.value = c; payForm.reset() }
// Never more than the udhaar owed.
watch(() => payForm.amount, (v) => { const max = Number(payModal.value?.current_balance ?? 0); if (Number(v) > max) payForm.amount = max })
function submitPay() { payForm.post(route('customers.payment', payModal.value.id), { onSuccess: () => (payModal.value = null), preserveScroll: true }) }
</script>

<template>
    <Head title="Customers" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">Customers</h2>
                    <p class="text-sm text-muted-foreground">Profiles, balances &amp; payments.</p>
                </div>
                <PrimaryButton v-if="can('customers.create')" @click="openCreate" class="!rounded-full"><Plus class="size-4 mr-1" /> New Customer</PrimaryButton>
            </div>
        </template>

        <Card>
            <div class="mb-4 relative max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input v-model="search" type="text" placeholder="Search name, code, phone…" class="w-full rounded-full border-border bg-background pl-9 pr-4 py-2 text-sm focus:border-brand focus:ring-brand" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-3 pr-4 font-medium">Code</th><th class="py-3 pr-4 font-medium">Name</th>
                        <th class="py-3 pr-4 font-medium">Type</th><th class="py-3 pr-4 font-medium">Phone</th>
                        <th class="py-3 pr-4 font-medium text-right">Balance</th><th class="py-3 pr-0 font-medium text-right">Actions</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="c in customers.data" :key="c.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-mono text-xs">{{ c.code }}</td>
                            <td class="py-3 pr-4 font-medium">{{ c.name }}</td>
                            <td class="py-3 pr-4 capitalize">{{ c.type.replace('_', ' ') }}</td>
                            <td class="py-3 pr-4 text-muted-foreground">{{ c.phone }}</td>
                            <td class="py-3 pr-4 text-right font-medium" :class="c.current_balance > 0 ? 'text-warning' : ''">{{ format(c.current_balance) }}</td>
                            <td class="py-3 pr-0">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="route('customers.ledger', c.id)" class="rounded-full p-2 hover:bg-muted" title="Ledger"><BookText class="size-4" /></Link>
                                    <button v-if="can('customers.collect')" @click="openPay(c)" class="rounded-full p-2 hover:bg-success/10 text-success" title="Record payment"><Wallet class="size-4" /></button>
                                    <button v-if="can('customers.update')" @click="openEdit(c)" class="rounded-full p-2 hover:bg-muted" title="Edit"><Pencil class="size-4" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!customers.data.length"><td colspan="6" class="py-8 text-center text-muted-foreground">No customers found.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Card v-if="ledgerCustomer" class="mt-4" :title="`Ledger — ${ledgerCustomer.name}`" :subtitle="`Current balance: ${format(ledgerCustomer.current_balance)}`">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-2 pr-4 font-medium">Date</th><th class="py-2 pr-4 font-medium">Narration</th>
                        <th class="py-2 pr-4 font-medium text-right">Debit</th><th class="py-2 pr-4 font-medium text-right">Credit</th>
                        <th class="py-2 pr-0 font-medium text-right">Balance</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="e in ledger" :key="e.id" class="border-b border-border/60">
                            <td class="py-2 pr-4">{{ e.entry_date }}</td><td class="py-2 pr-4">{{ e.narration }}</td>
                            <td class="py-2 pr-4 text-right">{{ e.debit > 0 ? format(e.debit) : '—' }}</td>
                            <td class="py-2 pr-4 text-right">{{ e.credit > 0 ? format(e.credit) : '—' }}</td>
                            <td class="py-2 pr-0 text-right font-medium">{{ format(e.balance) }}</td>
                        </tr>
                        <tr v-if="!ledger.length"><td colspan="5" class="py-6 text-center text-muted-foreground">No ledger entries.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">{{ editing ? 'Edit Customer' : 'New Customer' }}</h3>
                <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 block w-full" required /><InputError :message="form.errors.name" class="mt-1" /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><InputLabel value="Type" />
                        <select v-model="form.type" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option value="walk_in">Walk-in</option><option value="retail">Retail</option><option value="wholesale">Wholesale</option><option value="corporate">Corporate</option>
                        </select>
                    </div>
                    <div><InputLabel value="Phone" /><TextInput v-model="form.phone" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Email" /><TextInput v-model="form.email" type="email" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Credit Limit" /><TextInput v-model.number="form.credit_limit" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div v-if="!editing"><InputLabel value="Opening Balance" /><TextInput v-model.number="form.opening_balance" type="number" step="0.01" class="mt-1 block w-full" /></div>
                </div>
                <div><InputLabel value="Address" /><TextInput v-model="form.address" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">{{ editing ? 'Update' : 'Create' }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="!!payModal" @close="payModal = null">
            <form @submit.prevent="submitPay" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Receive Udhaar — {{ payModal?.name }}</h3>
                <p class="text-sm text-muted-foreground">Owes {{ format(payModal?.current_balance ?? 0) }}. Oldest unpaid invoices are settled first.</p>
                <div><InputLabel value="Amount" /><TextInput v-model.number="payForm.amount" type="number" step="0.01" min="0.01" :max="payModal?.current_balance" class="mt-1 block w-full" required /><InputError :message="payForm.errors.amount" class="mt-1" /></div>
                <div><InputLabel value="Received in" />
                    <select v-model="payForm.method" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                        <option value="cash">Cash</option><option value="bank">Bank / Easypaisa / JazzCash</option><option value="cheque">Cheque</option>
                    </select>
                </div>
                <div><InputLabel value="Note" /><TextInput v-model="payForm.note" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="payModal = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="payForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
