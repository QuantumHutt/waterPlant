<script setup>
import { Droplets } from 'lucide-vue-next'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
    showText: { type: Boolean, default: true },
    invert: { type: Boolean, default: false }, // light text (for dark surfaces)
    size: { type: String, default: 'md' },     // sm | md | lg
})

const page = usePage()
const companyName = computed(() => page.props.settings?.company?.name ?? 'AL-Noor Water Plant & Filter House')

const box = { sm: 'size-8', md: 'size-10', lg: 'size-12' }
const icon = { sm: 'size-4', md: 'size-5', lg: 'size-6' }
</script>

<template>
    <div class="flex items-center gap-3">
        <span :class="['flex items-center justify-center rounded-2xl bg-sidebar-gradient text-white shadow-sm', box[size]]">
            <Droplets :class="icon[size]" />
        </span>
        <div v-if="showText" class="min-w-0 leading-tight">
            <p class="truncate font-semibold tracking-tight" :class="invert ? 'text-white' : ''">BlueRoot</p>
            <p class="truncate text-xs" :class="invert ? 'text-white/70' : 'text-muted-foreground'">{{ companyName }}</p>
        </div>
    </div>
</template>
