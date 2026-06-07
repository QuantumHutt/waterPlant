import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

/** Front-end permission/role checks backed by the shared auth prop. */
export function usePermissions() {
    const page = usePage()

    const user = computed(() => page.props.auth?.user ?? null)
    const permissions = computed(() => user.value?.permissions ?? [])
    const roles = computed(() => user.value?.roles ?? [])

    const isSuperAdmin = computed(() => roles.value.includes('super-admin'))

    function can(permission) {
        if (isSuperAdmin.value) return true
        return permissions.value.includes(permission)
    }

    function canAny(list) {
        return list.some((p) => can(p))
    }

    function hasRole(role) {
        return roles.value.includes(role)
    }

    return { user, permissions, roles, isSuperAdmin, can, canAny, hasRole }
}
