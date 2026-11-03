<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import Modal from '@/Components/Modal.vue'
import PermissionPicker from '@/Components/Access/PermissionPicker.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import { usePermissions } from '@/composables/usePermissions'
import { Plus, Pencil, Trash2, Search } from 'lucide-vue-next'

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    permissionGroups: { type: Object, default: () => ({}) },
    rolePermissions: { type: Object, default: () => ({}) },
})

const { can } = usePermissions()

const search = ref(props.filters.search ?? '')
let searchTimer = null
watch(search, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get(route('users.index'), { search: value }, { preserveState: true, replace: true })
    }, 350)
})

const showModal = ref(false)
const editing = ref(null)

const form = useForm({
    name: '', email: '', password: '', password_confirmation: '',
    phone: '', is_active: true, roles: [], permissions: [],
})
// Permissions the selected role(s) already give — extras are ticked on top.
const fromRoles = computed(() => [...new Set(form.roles.flatMap((r) => props.rolePermissions[r] ?? []))])

function openCreate() {
    editing.value = null
    form.reset()
    form.is_active = true
    showModal.value = true
}

function openEdit(user) {
    editing.value = user
    form.reset()
    form.name = user.name
    form.email = user.email
    form.phone = user.phone ?? ''
    form.is_active = user.is_active
    form.roles = [...(user.roles ?? [])]
    form.permissions = [...(user.direct_permissions ?? [])]
    showModal.value = true
}

function submit() {
    if (editing.value) {
        form.put(route('users.update', editing.value.id), {
            onSuccess: () => (showModal.value = false),
        })
    } else {
        form.post(route('users.store'), {
            onSuccess: () => (showModal.value = false),
        })
    }
}

const confirmingDelete = ref(null)
function destroy() {
    router.delete(route('users.destroy', confirmingDelete.value.id), {
        onFinish: () => (confirmingDelete.value = null),
    })
}
</script>

<template>
    <Head title="Users" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">Users</h2>
                    <p class="text-sm text-muted-foreground">Manage system users, roles &amp; access.</p>
                </div>
                <PrimaryButton v-if="can('users.create')" @click="openCreate" class="!rounded-full">
                    <Plus class="size-4 mr-1" /> New User
                </PrimaryButton>
            </div>
        </template>

        <Card>
            <div class="mb-4 relative max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search name, email, phone…"
                    class="w-full rounded-full border-border bg-background pl-9 pr-4 py-2 text-sm focus:border-brand focus:ring-brand"
                />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground border-b border-border">
                            <th class="py-3 pr-4 font-medium">Name</th>
                            <th class="py-3 pr-4 font-medium">Email</th>
                            <th class="py-3 pr-4 font-medium">Roles</th>
                            <th class="py-3 pr-4 font-medium">Status</th>
                            <th class="py-3 pr-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users.data" :key="u.id" class="border-b border-border/60">
                            <td class="py-3 pr-4 font-medium">{{ u.name }}</td>
                            <td class="py-3 pr-4 text-muted-foreground">{{ u.email }}</td>
                            <td class="py-3 pr-4">
                                <span v-for="r in u.roles" :key="r" class="mr-1 inline-block rounded-full bg-brand/10 text-brand px-2 py-0.5 text-xs">{{ r }}</span>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="inline-flex items-center gap-1.5 text-xs">
                                    <span class="size-2 rounded-full" :class="u.is_active ? 'bg-success' : 'bg-muted-foreground'" />
                                    {{ u.is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="py-3 pr-0">
                                <div class="flex items-center justify-end gap-2">
                                    <button v-if="can('users.update')" @click="openEdit(u)" class="rounded-full p-2 hover:bg-muted" title="Edit">
                                        <Pencil class="size-4" />
                                    </button>
                                    <button v-if="can('users.delete')" @click="confirmingDelete = u" class="rounded-full p-2 hover:bg-destructive/10 text-destructive" title="Delete">
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td colspan="5" class="py-8 text-center text-muted-foreground">No users found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Create / Edit modal -->
        <Modal :show="showModal" @close="showModal = false">
            <form @submit.prevent="submit" class="p-6 space-y-4">
                <h3 class="text-lg font-semibold">{{ editing ? 'Edit User' : 'New User' }}</h3>

                <div>
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="password" :value="editing ? 'New Password (optional)' : 'Password'" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" />
                    </div>
                </div>

                <div>
                    <InputLabel for="phone" value="Phone" />
                    <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                </div>

                <div>
                    <InputLabel value="Roles" />
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label v-for="r in roles" :key="r.value" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" :value="r.value" v-model="form.roles" class="rounded border-border text-brand focus:ring-brand" />
                            {{ r.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.roles" class="mt-1" />
                </div>

                <div>
                    <InputLabel value="Extra permissions (on top of the role)" />
                    <p class="mb-2 text-xs text-muted-foreground">Ticked + faded = already given by the role. Tick more to give this user extra access.</p>
                    <div class="max-h-80 overflow-y-auto pr-1">
                        <PermissionPicker :groups="permissionGroups" v-model="form.permissions" :locked="fromRoles" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="form.is_active" class="rounded border-border text-brand focus:ring-brand" />
                    Active
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <SecondaryButton type="button" @click="showModal = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">{{ editing ? 'Update' : 'Create' }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Delete confirm -->
        <Modal :show="!!confirmingDelete" @close="confirmingDelete = null">
            <div class="p-6">
                <h3 class="text-lg font-semibold">Delete user?</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    This will deactivate access for <strong>{{ confirmingDelete?.name }}</strong>. This action can be reversed by restoring the user.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="confirmingDelete = null">Cancel</SecondaryButton>
                    <DangerButton @click="destroy">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
