<script setup>
defineProps({ title: String, subtitle: String })
</script>

<template>
    <div class="rounded-3xl bg-card text-card-foreground shadow-sm ring-1 ring-border">
        <div v-if="title || $slots.header" class="flex items-center justify-between gap-3 px-5 pt-5">
            <div>
                <h3 v-if="title" class="text-base font-semibold">{{ title }}</h3>
                <p v-if="subtitle" class="text-sm text-muted-foreground">{{ subtitle }}</p>
            </div>
            <slot name="header" />
        </div>
        <div class="p-5"><slot /></div>
    </div>
</template>
