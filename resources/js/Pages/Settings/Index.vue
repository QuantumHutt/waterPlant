<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
    settings: { type: Object, required: true },
    taxRates: { type: Array, default: () => [] },
})

const form = useForm({
    company: {
        name: props.settings.company?.name ?? '',
        legal_name: props.settings.company?.legal_name ?? '',
        phone: props.settings.company?.phone ?? '',
        email: props.settings.company?.email ?? '',
        address: props.settings.company?.address ?? '',
        ntn: props.settings.company?.ntn ?? '',
    },
    currency: {
        code: props.settings.currency?.code ?? 'PKR',
        symbol: props.settings.currency?.symbol ?? 'Rs',
        position: props.settings.currency?.position ?? 'before',
        decimals: props.settings.currency?.decimals ?? 2,
        abbreviation: props.settings.currency?.abbreviation ?? true,
    },
    tax: {
        enabled: props.settings.tax?.enabled ?? true,
        default_rate: props.settings.tax?.default_rate ?? 0,
        inclusive: props.settings.tax?.inclusive ?? false,
    },
    invoice: {
        prefix: props.settings.invoice?.prefix ?? 'INV-',
        next_number: props.settings.invoice?.next_number ?? 1001,
        terms: props.settings.invoice?.terms ?? '',
        footer: props.settings.invoice?.footer ?? '',
        show_logo: props.settings.invoice?.show_logo ?? true,
    },
})

function save() {
    form.put(route('settings.update'), { preserveScroll: true })
}
</script>

<template>
    <Head title="Settings" />

    <AppLayout>
        <template #header>
            <div>
                <h2 class="text-2xl font-semibold tracking-tight">Settings</h2>
                <p class="text-sm text-muted-foreground">Company, currency, tax &amp; invoice configuration.</p>
            </div>
        </template>

        <form @submit.prevent="save" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <Card title="Company">
                <div class="space-y-3">
                    <div><InputLabel value="Name" /><TextInput v-model="form.company.name" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Legal Name" /><TextInput v-model="form.company.legal_name" class="mt-1 block w-full" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><InputLabel value="Phone" /><TextInput v-model="form.company.phone" class="mt-1 block w-full" /></div>
                        <div><InputLabel value="Email" /><TextInput v-model="form.company.email" type="email" class="mt-1 block w-full" /></div>
                    </div>
                    <div><InputLabel value="Address" /><TextInput v-model="form.company.address" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="NTN" /><TextInput v-model="form.company.ntn" class="mt-1 block w-full" /></div>
                </div>
            </Card>

            <Card title="Currency">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><InputLabel value="Code" /><TextInput v-model="form.currency.code" class="mt-1 block w-full" /></div>
                        <div><InputLabel value="Symbol" /><TextInput v-model="form.currency.symbol" class="mt-1 block w-full" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Symbol Position" />
                            <select v-model="form.currency.position" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                                <option value="before">Before (Rs 100)</option>
                                <option value="after">After (100 Rs)</option>
                            </select>
                        </div>
                        <div><InputLabel value="Decimals" /><TextInput v-model.number="form.currency.decimals" type="number" min="0" max="4" class="mt-1 block w-full" /></div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.currency.abbreviation" class="rounded border-border text-brand focus:ring-brand" />
                        Use K / Lac / Crore in summaries
                    </label>
                </div>
            </Card>

            <Card title="Tax">
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.tax.enabled" class="rounded border-border text-brand focus:ring-brand" /> Tax enabled
                    </label>
                    <div><InputLabel value="Default Rate (%)" /><TextInput v-model.number="form.tax.default_rate" type="number" step="0.01" class="mt-1 block w-full" /></div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.tax.inclusive" class="rounded border-border text-brand focus:ring-brand" /> Prices are tax-inclusive
                    </label>
                    <div class="pt-2">
                        <p class="text-xs font-medium text-muted-foreground mb-1">Configured rates</p>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="t in taxRates" :key="t.id" class="rounded-full bg-muted px-3 py-1 text-xs">{{ t.name }} — {{ t.rate }}%</span>
                        </div>
                    </div>
                </div>
            </Card>

            <Card title="Invoice">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><InputLabel value="Prefix" /><TextInput v-model="form.invoice.prefix" class="mt-1 block w-full" /></div>
                        <div><InputLabel value="Next Number" /><TextInput v-model.number="form.invoice.next_number" type="number" class="mt-1 block w-full" /></div>
                    </div>
                    <div><InputLabel value="Terms" /><TextInput v-model="form.invoice.terms" class="mt-1 block w-full" /></div>
                    <div><InputLabel value="Footer" /><TextInput v-model="form.invoice.footer" class="mt-1 block w-full" /></div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.invoice.show_logo" class="rounded border-border text-brand focus:ring-brand" /> Show logo on invoices
                    </label>
                </div>
            </Card>

            <div class="lg:col-span-2 flex justify-end">
                <PrimaryButton :disabled="form.processing">Save Settings</PrimaryButton>
            </div>
        </form>
    </AppLayout>
</template>
