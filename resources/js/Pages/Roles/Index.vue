<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from '@/Components/ui/Card.vue'
import PermissionPicker from '@/Components/Access/PermissionPicker.vue'
import { usePermissions } from '@/composables/usePermissions'

const props = defineProps({ roles: Array, permissionGroups: Object })
const { can } = usePermissions()

const selectedId = ref(props.roles.find((r) => r.name === 'salesman')?.id ?? props.roles[0]?.id)
const role = computed(() => props.roles.find((r) => r.id === selectedId.value))
const form = useForm({ permissions: [...(role.value?.permissions ?? [])] })
function pick(r) { selectedId.value = r.id; form.permissions = [...r.permissions]; form.clearErrors() }
function save() { form.put(route('roles.update', role.value.id), { preserveScroll: true }) }
</script>

<template>
    <Head title="Roles & Permissions" />
    <AppLayout>
        <template #header>
            <div><h2 class="text-2xl font-semibold tracking-tight">Roles &amp; Permissions</h2>
                <p class="text-sm text-muted-foreground">Choose what each role can do. For one person only, give extra permissions on the Users page.</p></div>
        </template>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
            <Card class="lg:col-span-1">
                <div class="space-y-1">
                    <button v-for="r in roles" :key="r.id" type="button" @click="pick(r)"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm"
                        :class="r.id === selectedId ? 'bg-brand text-white' : 'hover:bg-muted'">
                        <span class="font-medium">{{ r.label }}</span>
                        <span class="text-xs opacity-80">{{ r.users_count }} user(s)</span>
                    </button>
                </div>
            </Card>

            <Card v-if="role" class="lg:col-span-3">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold">{{ role.label }}</h3>
                    <button v-if="!role.locked && can('roles.update')" type="button" @click="save" :disabled="form.processing"
                        class="rounded-full bg-brand px-5 py-2 text-sm font-semibold text-white disabled:opacity-40">Save</button>
                </div>
                <p v-if="role.locked" class="mb-3 rounded-lg bg-muted px-3 py-2 text-sm text-muted-foreground">Super Admin always has every permission — it can't be changed.</p>
                <PermissionPicker :groups="permissionGroups" v-model="form.permissions" :locked="role.locked ? role.permissions : []" />
                <p v-if="Object.keys(form.errors).length" class="mt-2 text-xs text-destructive">{{ Object.values(form.errors)[0] }}</p>
            </Card>
        </div>
    </AppLayout>
</template>
