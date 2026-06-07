<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { usePermissions } from '@/composables/usePermissions'
import ThemeToggle from '@/Components/ThemeToggle.vue'
import ColorThemePicker from '@/Components/ColorThemePicker.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import { Menu } from 'lucide-vue-next'
import { computed } from 'vue'

defineProps({ onMenuClick: { type: Function, default: () => {} } })

const { user } = usePermissions()
const page = usePage()
const initials = computed(() =>
    (user.value?.name ?? '?').split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase(),
)
</script>

<template>
    <header class="flex items-center justify-between gap-3 pb-5">
        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="onMenuClick"
                class="flex size-9 items-center justify-center rounded-full ring-1 ring-border bg-card hover:bg-muted transition lg:hidden"
                aria-label="Open menu"
            >
                <Menu class="size-4" />
            </button>
            <div>
                <p class="text-sm text-muted-foreground">Welcome back,</p>
                <h1 class="text-lg font-semibold leading-tight">{{ user?.name }}</h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="hidden sm:block"><ColorThemePicker /></div>
            <ThemeToggle />

            <Dropdown align="right" width="48">
                <template #trigger>
                    <button class="flex items-center gap-2 rounded-full ring-1 ring-border bg-card pl-1 pr-3 py-1 hover:bg-muted transition">
                        <span class="flex size-7 items-center justify-center rounded-full bg-brand text-white text-xs font-semibold">{{ initials }}</span>
                        <span class="hidden text-sm font-medium sm:block">{{ user?.roles?.[0] ?? 'user' }}</span>
                    </button>
                </template>
                <template #content>
                    <div class="px-4 py-2 text-xs text-muted-foreground border-b border-border">{{ user?.email }}</div>
                    <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                    <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                </template>
            </Dropdown>
        </div>
    </header>
</template>
