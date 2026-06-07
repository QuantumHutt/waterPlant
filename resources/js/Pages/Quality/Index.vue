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
import { Plus, Wrench, CheckCircle2, XCircle } from 'lucide-vue-next'

const props = defineProps({ tests: Array, schedules: Array, batches: Array })
const { can } = usePermissions()

const testModal = ref(false)
const testForm = useForm({ production_batch_id: '', tds_value: null, ph_value: null, temperature: null, passed: true, tested_at: new Date().toISOString().slice(0, 10), remarks: '' })
function submitTest() { testForm.post(route('quality.test'), { onSuccess: () => (testModal.value = false), preserveScroll: true }) }

const schModal = ref(false)
const schForm = useForm({ asset: 'filter', title: '', frequency_days: 30 })
function submitSch() { schForm.post(route('quality.schedule'), { onSuccess: () => (schModal.value = false), preserveScroll: true }) }

function logMaint(s) { router.post(route('quality.log', s.id), {}, { preserveScroll: true }) }
</script>

<template>
    <Head title="Water Quality" />
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-2xl font-semibold tracking-tight">Water Quality</h2><p class="text-sm text-muted-foreground">TDS / pH tests &amp; maintenance.</p></div>
                <div class="flex gap-2">
                    <PrimaryButton v-if="can('quality.create')" @click="testModal = true" class="!rounded-full"><Plus class="size-4 mr-1" /> Test</PrimaryButton>
                    <button v-if="can('quality.create')" @click="schModal = true" class="rounded-full bg-card ring-1 ring-border px-4 py-2 text-sm font-medium hover:bg-muted">+ Schedule</button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Card title="Recent Tests">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-muted-foreground border-b border-border"><th class="py-2 pr-4 font-medium">Date</th><th class="py-2 pr-4 font-medium">Batch</th><th class="py-2 pr-4 font-medium text-right">TDS</th><th class="py-2 pr-4 font-medium text-right">pH</th><th class="py-2 pr-0 font-medium text-center">Result</th></tr></thead>
                    <tbody>
                        <tr v-for="t in tests" :key="t.id" class="border-b border-border/60">
                            <td class="py-2 pr-4">{{ t.tested_at }}</td><td class="py-2 pr-4">{{ t.batch?.batch_number ?? '—' }}</td>
                            <td class="py-2 pr-4 text-right">{{ t.tds_value ?? '—' }}</td><td class="py-2 pr-4 text-right">{{ t.ph_value ?? '—' }}</td>
                            <td class="py-2 pr-0 text-center"><component :is="t.passed ? CheckCircle2 : XCircle" class="inline size-4" :class="t.passed ? 'text-success' : 'text-destructive'" /></td>
                        </tr>
                        <tr v-if="!tests.length"><td colspan="5" class="py-6 text-center text-muted-foreground">No tests.</td></tr>
                    </tbody>
                </table>
            </Card>

            <Card title="Maintenance Schedules">
                <ul class="space-y-2 text-sm">
                    <li v-for="s in schedules" :key="s.id" class="flex items-center justify-between border-b border-border/60 pb-2">
                        <div><p class="font-medium">{{ s.title }}</p><p class="text-xs text-muted-foreground capitalize">{{ s.asset.replace('_', ' ') }} · due {{ s.next_due_at ?? '—' }}</p></div>
                        <button v-if="can('quality.update')" @click="logMaint(s)" class="rounded-full bg-success/10 text-success px-3 py-1.5 text-xs font-medium hover:bg-success/20 flex items-center gap-1"><Wrench class="size-3.5" /> Done</button>
                    </li>
                    <li v-if="!schedules.length" class="py-6 text-center text-muted-foreground">No schedules.</li>
                </ul>
            </Card>
        </div>

        <Modal :show="testModal" @close="testModal = false">
            <form @submit.prevent="submitTest" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Water Quality Test</h3>
                <div><InputLabel value="Batch (optional)" /><select v-model="testForm.production_batch_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option value="">—</option><option v-for="b in batches" :key="b.id" :value="b.id">{{ b.batch_number }}</option></select></div>
                <div class="grid grid-cols-3 gap-4">
                    <div><InputLabel value="TDS (ppm)" /><TextInput v-model.number="testForm.tds_value" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="pH" /><TextInput v-model.number="testForm.ph_value" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Temp °C" /><TextInput v-model.number="testForm.temperature" type="number" step="0.01" class="mt-1 block w-full" /></div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="testForm.passed" class="rounded border-border text-brand focus:ring-brand" /> Passed</label>
                <div><InputLabel value="Remarks" /><TextInput v-model="testForm.remarks" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="testModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="testForm.processing">Save</PrimaryButton></div>
            </form>
        </Modal>

        <Modal :show="schModal" @close="schModal = false">
            <form @submit.prevent="submitSch" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">Maintenance Schedule</h3>
                <div><InputLabel value="Asset" /><select v-model="schForm.asset" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand"><option value="filter">Filter</option><option value="ro_membrane">RO Membrane</option><option value="uv">UV</option><option value="pump">Pump</option></select></div>
                <div><InputLabel value="Title" /><TextInput v-model="schForm.title" class="mt-1 block w-full" required /></div>
                <div><InputLabel value="Frequency (days)" /><TextInput v-model.number="schForm.frequency_days" type="number" class="mt-1 block w-full" /></div>
                <div class="flex justify-end gap-3 pt-2"><SecondaryButton type="button" @click="schModal = false">Cancel</SecondaryButton><PrimaryButton :disabled="schForm.processing">Save</PrimaryButton></div>
            </form>
        </Modal>
    </AppLayout>
</template>
