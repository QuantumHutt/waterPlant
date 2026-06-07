<script setup>
import { useTheme } from '@/composables/useTheme'

const { brand, brands, setBrand } = useTheme()
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            v-for="b in brands"
            :key="b.key"
            type="button"
            :aria-label="`Use ${b.label} theme`"
            @click="setBrand(b.key)"
            class="size-6 rounded-full ring-2 outline-none transition"
            :class="brand === b.key ? 'ring-ring' : 'ring-transparent'"
            :style="{ backgroundColor: b.color }"
        />
    </div>
</template>
