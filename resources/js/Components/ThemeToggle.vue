<script setup>
import { useTheme } from '@/composables/useTheme'
import { Moon, Sun } from 'lucide-vue-next'

const { isDark, toggleDark } = useTheme()
</script>

<template>
    <button
        type="button"
        @click="toggleDark"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        class="flex size-9 items-center justify-center rounded-full ring-1 ring-border bg-card hover:bg-muted transition"
    >
        <Sun v-if="isDark" class="size-4" />
        <Moon v-else class="size-4" />
    </button>
</template>
