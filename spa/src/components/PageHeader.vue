<script setup>
defineProps({ title: String, subtitle: String })
</script>

<template>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight">{{ title }}</h2>
            <p v-if="subtitle" class="text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
