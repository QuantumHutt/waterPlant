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
import { Plus, Pencil, Wallet, Search } from 'lucide-vue-next'

const props = defineProps({ suppliers: Object, filters: { type: Object, default: () => ({}) } })
const { can } = usePermissions()
const { format } = useCurrency()

const search = ref(props.filters.search ?? '')
let t = null
watch(search, (v) => { clearTimeout(t); t = setTimeout(() => router.get(route('suppliers.index'), { search: v }, { preserveState: true, replace: true }), 350) })

const showModal = ref(false)
const editing = ref(null)
const form = useForm({ code: '', name: '', phone: '', email: '', address: '', opening_balance: 0, is_active: true })
function openCreate() { editing.value = null; form.reset(); showModal.value = true }
function openEdit(s) { editing.value = s; form.reset(); Object.assign(form, { code: s.code, name: s.name, phone: s.phone, email: s.email, address: s.address, is_active: s.is_active }); showModal.value = true }
function submit() { const o = { onSuccess: () => (showModal.value = false), preserveScroll: true }; editing.value ? form.put(route('suppliers.update', editing.value.id), o) : form.post(route('suppliers.store'), o) }

const payModal = ref(null)
const payForm = useForm({ amount: null, method: 'cash', note: '' })
function openPay(s) { payModal.value = s; payForm.reset() }
function submitPay() { payForm.post(route('suppliers.payment', payModal.value.id), { onSuccess: () => (payModal.value = null), preserveScroll: true }) }
</script>

<template>
    <Head title="Suppliers" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-2xl font-semibold tracking-tight">Suppliers</h2><p class="text-sm text-muted-foreground">Vendors &amp; payables.</p></div>
                <PrimaryButton v-if="can('suppliers.create')" @click="openCreate" class="!rounded-full"><Plus class="size-4 mr-1" /> New Supplier</PrimaryButton>
            </div>
        </template>
        <Card>
            <div class="mb-4 relative max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input v-model="search" placeholder="Search…" class="w-full rounded-full border-border bg-background pl-9 pr-4 py-2 text-sm focus:border-brand focus:ring-brand" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-3 pr-4 font-medium">Code</th><th class="py-3 pr-4 font-medium">Name</th><th class="py-3 pr-4 font-medium">Phone</th>
                        <th class="py-3 pr-4 font-medium text-right">Payable</th><th class="py-3 pr-0 font-medium text-right">Actions</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="s in suppliers.data" :key="s.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-mono text-xs">{{ s.code }}</td>
                            <td class="py-3 pr-4 font-medium">{{ s.name }}</td>
                            <td class="py-3 pr-4 text-muted-foreground">{{ s.phone }}</td>
                            <td class="py-3 pr-4 text-right font-medium" :class="s.current_balance > 0 ? 'text-warning' : ''">{{ format(s.current_balance) }}</td>
                            <td class="py-3 pr-0"><div class="flex items-center justify-end gap-1">
                                <button v-if="can('suppliers.update')" @click="openPay(s)" class="rounded-full p-2 hover:bg-success/10 text-success" title="Pay"><Wallet class="size-4" /></button>
                                <button v-if="can('suppliers.update')" @click="openEdit(s)" class="rounded-full p-2 hover:bg-muted"><Pencil class="size-4" /></button>
                            </div></td>
                        </tr>
                        <tr v-if="!suppliers.data.length"><td colspan="5" class="py-8 text-center text-muted-foreground">No suppliers found.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">{{ editing ? 'Edit Supplier' : 'New Supplier' }}</h3>
                <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 block w-full" required /><InputError :message="form.errors.name" class="mt-1" /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><InputLabel value="Phone" /><TextInput v-model="form.phone" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Email" /><TextInput v-model="form.email" type="email" class="mt-1 block w-full" /></div>
                    <div v-if="!editing" class="col-span-2"><InputLabel value="Opening Balance (payable)" /><TextInput v-model.number="form.opening_balance" type="number" step="0.01" class="mt-1 block w-full" /></div>
                </div>
                <div><InputLabel value="Address" /><TextInput v-model="form.address" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="form.processing">{{ editing ? 'Update' : 'Create' }}</PrimaryButton></div>
            </form>
        </Modal>

        <Modal :show="!!payModal" @close="payModal = null">
            <form @submit.prevent="submitPay" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Pay Supplier — {{ payModal?.name }}</h3>
                <p class="text-sm text-muted-foreground">We owe {{ format(payModal?.current_balance ?? 0) }}</p>
                <div><InputLabel value="Amount" /><TextInput v-model.number="payForm.amount" type="number" step="0.01" min="0.01" :max="payModal?.current_balance" class="mt-1 block w-full" required /><p v-if="payForm.errors.amount" class="mt-1 text-xs text-destructive">{{ payForm.errors.amount }}</p></div>
                <div><InputLabel value="Paid from" />
                    <select v-model="payForm.method" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                        <option value="cash">Cash drawer</option><option value="bank">Bank</option><option value="cheque">Cheque</option>
                    </select>
                </div>
                <div><InputLabel value="Note" /><TextInput v-model="payForm.note" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="payModal = null">Cancel</SecondaryButton><PrimaryButton :disabled="payForm.processing">Save</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
