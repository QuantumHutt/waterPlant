<script setup>
import { onMounted, onUnmounted } from 'vue'
const props = defineProps({ show: Boolean, title: String, wide: Boolean })
const emit = defineEmits(['close'])
const onKey = (e) => { if (e.key === 'Escape' && props.show) emit('close') }
onMounted(() => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="fixed inset-0 bg-black/60" @click="emit('close')" />
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full rounded-2xl bg-card text-card-foreground shadow-xl ring-1 ring-border" :class="wide ? 'sm:max-w-3xl' : 'sm:max-w-lg'">
                    <div v-if="title" class="border-b border-border px-6 py-4"><h3 class="text-lg font-semibold">{{ title }}</h3></div>
                    <div class="p-6"><slot /></div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
