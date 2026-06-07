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
import { useCurrency } from '@/composables/useCurrency'
import { Plus, Wallet, CalendarCheck, Receipt } from 'lucide-vue-next'

const props = defineProps({ employees: Object, payrolls: Array })
const { can } = usePermissions()
const { format } = useCurrency()

const showModal = ref(false)
const form = useForm({ name: '', designation: '', department: '', phone: '', cnic: '', join_date: '', basic_salary: 0 })
function open() { form.reset(); showModal.value = true }
function submit() { form.post(route('employees.store'), { onSuccess: () => (showModal.value = false), preserveScroll: true }) }

const advModal = ref(null)
const advForm = useForm({ employee_id: '', amount: null, note: '' })
function openAdv(e) { advModal.value = e; advForm.reset(); advForm.employee_id = e.id }
function submitAdv() { advForm.post(route('employees.advance'), { onSuccess: () => (advModal.value = null), preserveScroll: true }) }

const month = ref(new Date().toISOString().slice(0, 7))
function genPayroll(e) { router.post(route('employees.payroll', e.id), { month: month.value }, { preserveScroll: true }) }
</script>

<template>
    <Head title="Employees" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-2xl font-semibold tracking-tight">Employees</h2><p class="text-sm text-muted-foreground">HR, advances &amp; payroll.</p></div>
                <PrimaryButton v-if="can('employees.create')" @click="open" class="!rounded-full"><Plus class="size-4 mr-1" /> New Employee</PrimaryButton>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2" title="Staff">
                <div class="mb-3 flex items-center gap-2 text-sm">
                    <span class="text-muted-foreground">Payroll month:</span>
                    <input v-model="month" type="month" class="rounded-md border-border bg-background text-sm focus:border-brand focus:ring-brand" />
                </div>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border">
                        <th class="py-3 pr-4 font-medium">Code</th><th class="py-3 pr-4 font-medium">Name</th><th class="py-3 pr-4 font-medium">Designation</th>
                        <th class="py-3 pr-4 font-medium text-right">Salary</th><th class="py-3 pr-0 font-medium text-right">Actions</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="e in employees.data" :key="e.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-mono text-xs">{{ e.code }}</td>
                            <td class="py-3 pr-4 font-medium">{{ e.name }}</td>
                            <td class="py-3 pr-4 text-muted-foreground">{{ e.designation ?? '—' }}</td>
                            <td class="py-3 pr-4 text-right">{{ format(e.basic_salary) }}</td>
                            <td class="py-3 pr-0"><div class="flex items-center justify-end gap-1">
                                <button v-if="can('employees.update')" @click="openAdv(e)" class="rounded-full p-2 hover:bg-muted" title="Advance"><Wallet class="size-4" /></button>
                                <button v-if="can('payroll.process')" @click="genPayroll(e)" class="rounded-full p-2 hover:bg-success/10 text-success" title="Generate payroll"><Receipt class="size-4" /></button>
                            </div></td>
                        </tr>
                        <tr v-if="!employees.data.length"><td colspan="5" class="py-8 text-center text-muted-foreground">No employees yet.</td></tr>
                    </tbody>
                </table>
            </Card>

            <Card title="Recent Payroll">
                <ul class="space-y-2 text-sm">
                    <li v-for="p in payrolls" :key="p.id" class="flex items-center justify-between border-b border-border/60 pb-2">
                        <div><p class="font-medium">{{ p.employee?.name }}</p><p class="text-xs text-muted-foreground">{{ p.month }} · {{ p.status }}</p></div>
                        <span class="font-medium">{{ format(p.net) }}</span>
                    </li>
                    <li v-if="!payrolls.length" class="py-6 text-center text-muted-foreground">No payroll generated.</li>
                </ul>
            </Card>
        </div>

        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">New Employee</h3>
                <div><InputLabel value="Name" /><TextInput v-model="form.name" class="mt-1 block w-full" required /></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><InputLabel value="Designation" /><TextInput v-model="form.designation" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Department" /><TextInput v-model="form.department" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Phone" /><TextInput v-model="form.phone" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="CNIC" /><TextInput v-model="form.cnic" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Join Date" /><TextInput v-model="form.join_date" type="date" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Basic Salary" /><TextInput v-model.number="form.basic_salary" type="number" step="0.01" class="mt-1 block w-full" required /></div>
                </div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="form.processing">Create</PrimaryButton></div>
            </form>
        </Modal>

        <Modal :show="!!advModal" @close="advModal = null">
            <form @submit.prevent="submitAdv" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Advance — {{ advModal?.name }}</h3>
                <div><InputLabel value="Amount" /><TextInput v-model.number="advForm.amount" type="number" step="0.01" class="mt-1 block w-full" required /></div>
                <div><InputLabel value="Note" /><TextInput v-model="advForm.note" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="advModal = null">Cancel</SecondaryButton><PrimaryButton :disabled="advForm.processing">Save</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
