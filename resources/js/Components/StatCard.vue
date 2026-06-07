<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
    tone: { type: String, default: 'brand' }, // brand | success | warning | destructive
})

const toneClasses = computed(() => ({
    brand: 'bg-brand/10 text-brand',
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/15 text-warning',
    destructive: 'bg-destructive/10 text-destructive',
}[props.tone] ?? 'bg-brand/10 text-brand'))
</script>

<template>
    <div class="rounded-3xl bg-card text-card-foreground shadow-sm ring-1 ring-border p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground truncate">{{ label }}</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight">{{ value }}</p>
                <p v-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
            </div>
            <span v-if="icon" class="flex size-11 shrink-0 items-center justify-center rounded-2xl" :class="toneClasses">
                <component :is="icon" class="size-5" />
            </span>
        </div>
    </div>
</template>
