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
import DangerButton from '@/Components/DangerButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { useCurrency } from '@/composables/useCurrency'
import { Plus, Pencil, Trash2, Search, X } from 'lucide-vue-next'

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Object, required: true },
    types: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    singleProducts: { type: Array, default: () => [] },
})

const { can } = usePermissions()
const { format } = useCurrency()

const search = ref(props.filters.search ?? '')
let t = null
watch(search, (v) => {
    clearTimeout(t)
    t = setTimeout(() => router.get(route('products.index'), { search: v }, { preserveState: true, replace: true }), 350)
})

const showModal = ref(false)
const editing = ref(null)

const form = useForm({
    category_id: '', sku: '', barcode: '', name: '', description: '',
    type: 'finished', unit: 'pcs', unit_type: 'single', pack_size: 1, pack_of_product_id: null,
    cost_price: 0, sale_price: 0,
    wholesale_price: null, reorder_level: 0, is_active: true, variants: [],
})

function openCreate() {
    editing.value = null
    form.reset()
    form.category_id = props.categories.data?.[0]?.id ?? ''
    showModal.value = true
}

function openEdit(p) {
    editing.value = p
    form.reset()
    Object.assign(form, {
        category_id: p.category_id, sku: p.sku, barcode: p.barcode, name: p.name,
        description: p.description, type: p.type, unit: p.unit,
        unit_type: p.unit_type ?? 'single', pack_size: p.pack_size ?? 1, pack_of_product_id: p.pack_of_product_id,
        cost_price: p.cost_price,
        sale_price: p.sale_price, wholesale_price: p.wholesale_price,
        reorder_level: p.reorder_level, is_active: p.is_active,
        variants: (p.variants ?? []).map((v) => ({ ...v })),
    })
    showModal.value = true
}

function addVariant() {
    form.variants.push({ name: '', capacity_ml: null, cost_price: 0, sale_price: 0, reorder_level: 0, is_active: true })
}
function removeVariant(i) {
    form.variants.splice(i, 1)
}

function submit() {
    const opts = { onSuccess: () => (showModal.value = false), preserveScroll: true }
    editing.value
        ? form.put(route('products.update', editing.value.id), opts)
        : form.post(route('products.store'), opts)
}

const confirmingDelete = ref(null)
function destroy() {
    router.delete(route('products.destroy', confirmingDelete.value.id), {
        onFinish: () => (confirmingDelete.value = null),
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Products" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">Products</h2>
                    <p class="text-sm text-muted-foreground">Catalog, variants, pricing &amp; reorder levels.</p>
                </div>
                <PrimaryButton v-if="can('products.create')" @click="openCreate" class="!rounded-full">
                    <Plus class="size-4 mr-1" /> New Product
                </PrimaryButton>
            </div>
        </template>

        <Card>
            <div class="mb-4 relative max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input v-model="search" type="text" placeholder="Search name, SKU, barcode…"
                    class="w-full rounded-full border-border bg-background pl-9 pr-4 py-2 text-sm focus:border-brand focus:ring-brand" />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground border-b border-border">
                            <th class="py-3 pr-4 font-medium">Product</th>
                            <th class="py-3 pr-4 font-medium">SKU</th>
                            <th class="py-3 pr-4 font-medium">Category</th>
                            <th class="py-3 pr-4 font-medium">Type</th>
                            <th class="py-3 pr-4 font-medium text-right">Cost</th>
                            <th class="py-3 pr-4 font-medium text-right">Sale</th>
                            <th class="py-3 pr-4 font-medium text-center">Variants</th>
                            <th class="py-3 pr-0 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in products.data" :key="p.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-medium">{{ p.name }}</td>
                            <td class="py-3 pr-4 text-muted-foreground font-mono text-xs">{{ p.sku }}</td>
                            <td class="py-3 pr-4">{{ p.category?.name }}</td>
                            <td class="py-3 pr-4 capitalize">{{ p.type }}</td>
                            <td class="py-3 pr-4 text-right">{{ format(p.cost_price) }}</td>
                            <td class="py-3 pr-4 text-right font-medium">{{ format(p.sale_price) }}</td>
                            <td class="py-3 pr-4 text-center">{{ p.variants?.length || '—' }}</td>
                            <td class="py-3 pr-0">
                                <div class="flex items-center justify-end gap-2">
                                    <button v-if="can('products.update')" @click="openEdit(p)" class="rounded-full p-2 hover:bg-muted"><Pencil class="size-4" /></button>
                                    <button v-if="can('products.delete')" @click="confirmingDelete = p" class="rounded-full p-2 hover:bg-destructive/10 text-destructive"><Trash2 class="size-4" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!products.data.length"><td colspan="8" class="py-8 text-center text-muted-foreground">No products found.</td></tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal :show="showModal" max-width="2xl" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
                <h3 class="text-lg font-semibold">{{ editing ? 'Edit Product' : 'New Product' }}</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <InputLabel value="Name" />
                        <TextInput v-model="form.name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Category" />
                        <select v-model="form.category_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option v-for="c in categories.data" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <InputError :message="form.errors.category_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="SKU (auto if blank)" />
                        <TextInput v-model="form.sku" class="mt-1 block w-full" />
                        <InputError :message="form.errors.sku" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Type" />
                        <select v-model="form.type" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option v-for="ty in types" :key="ty.value" :value="ty.value">{{ ty.label }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Unit" />
                        <select v-model="form.unit" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option v-for="u in units" :key="u.value" :value="u.value">{{ u.label }}</option>
                        </select>
                    </div>
                    <div><InputLabel value="Barcode" /><TextInput v-model="form.barcode" class="mt-1 block w-full" /><InputError :message="form.errors.barcode" class="mt-1" /></div>
                    <div>
                        <InputLabel value="Unit Type" />
                        <select v-model="form.unit_type" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                            <option value="single">Single</option>
                            <option value="pack">Pack / Pat</option>
                        </select>
                    </div>
                    <div><InputLabel value="Reorder Level" /><TextInput v-model.number="form.reorder_level" type="number" step="0.001" class="mt-1 block w-full" /></div>
                    <template v-if="form.unit_type === 'pack'">
                        <div>
                            <InputLabel value="Base Single Bottle" />
                            <select v-model="form.pack_of_product_id" class="mt-1 block w-full rounded-md border-border bg-background focus:border-brand focus:ring-brand">
                                <option :value="null">— select —</option>
                                <option v-for="s in singleProducts" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <InputError :message="form.errors.pack_of_product_id" class="mt-1" />
                        </div>
                        <div><InputLabel value="Bottles per Pack" /><TextInput v-model.number="form.pack_size" type="number" min="1" class="mt-1 block w-full" /><p class="mt-1 text-xs text-muted-foreground">Selling 1 pack deducts this many bottles from the base stock.</p></div>
                    </template>
                    <div><InputLabel value="Cost Price" /><TextInput v-model.number="form.cost_price" type="number" step="0.0001" class="mt-1 block w-full" required /></div>
                    <div><InputLabel value="Sale Price" /><TextInput v-model.number="form.sale_price" type="number" step="0.01" class="mt-1 block w-full" required /></div>
                    <div><InputLabel value="Wholesale Price" /><TextInput v-model.number="form.wholesale_price" type="number" step="0.01" class="mt-1 block w-full" /></div>
                </div>

                <!-- Variants -->
                <div class="rounded-2xl bg-muted/50 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-medium">Variants (bottle sizes)</p>
                        <button type="button" @click="addVariant" class="text-sm text-brand font-medium flex items-center gap-1"><Plus class="size-4" /> Add</button>
                    </div>
                    <div v-for="(v, i) in form.variants" :key="i" class="mb-2 grid grid-cols-12 gap-2 items-center">
                        <TextInput v-model="v.name" placeholder="250ml" class="col-span-3 text-sm" />
                        <TextInput v-model.number="v.capacity_ml" type="number" placeholder="ml" class="col-span-2 text-sm" />
                        <TextInput v-model.number="v.cost_price" type="number" step="0.01" placeholder="cost" class="col-span-3 text-sm" />
                        <TextInput v-model.number="v.sale_price" type="number" step="0.01" placeholder="sale" class="col-span-3 text-sm" />
                        <button type="button" @click="removeVariant(i)" class="col-span-1 text-destructive"><X class="size-4" /></button>
                    </div>
                    <p v-if="!form.variants.length" class="text-xs text-muted-foreground">No variants — a simple product.</p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_active" class="rounded border-border text-brand focus:ring-brand" /> Active
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">{{ editing ? 'Update' : 'Create' }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="!!confirmingDelete" @close="confirmingDelete = null">
            <div class="p-6">
                <h3 class="text-lg font-semibold">Delete product?</h3>
                <p class="mt-2 text-sm text-muted-foreground">Delete <strong>{{ confirmingDelete?.name }}</strong>? This soft-deletes the product.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="confirmingDelete = null">Cancel</SecondaryButton>
                    <DangerButton @click="destroy">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
