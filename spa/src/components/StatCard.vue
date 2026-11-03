<script setup>
import { computed } from 'vue'
const props = defineProps({ label: String, value: String, hint: String, icon: [Object, Function], tone: { type: String, default: 'brand' } })
const toneClasses = computed(() => ({
    brand: 'bg-brand/10 text-brand', success: 'bg-success/10 text-success',
    warning: 'bg-warning/15 text-warning', destructive: 'bg-destructive/10 text-destructive',
}[props.tone]))
</script>

<template>
    <div class="rounded-3xl bg-card p-5 text-card-foreground shadow-sm ring-1 ring-border">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-sm text-muted-foreground">{{ label }}</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight">{{ value }}</p>
                <p v-if="hint" class="mt-1 text-xs text-muted-foreground">{{ hint }}</p>
            </div>
            <span v-if="icon" class="flex size-11 shrink-0 items-center justify-center rounded-2xl" :class="toneClasses">
                <component :is="icon" class="size-5" />
            </span>
        </div>
    </div>
</template>
