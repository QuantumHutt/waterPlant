<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    showText: { type: Boolean, default: true },  // show company subtitle under the logo
    invert: { type: Boolean, default: false },    // white logo for dark surfaces
    size: { type: String, default: 'md' },        // sm | md | lg
})

const page = usePage()
const companyName = computed(() => page.props.settings?.company?.name ?? 'AL-Noor Water Plant & Filter House')

const heights = { sm: 'h-7', md: 'h-9', lg: 'h-12' }
const logo = computed(() => (props.invert ? '/images/blueroot-logo-white.png' : '/images/blueroot-logo.png'))
</script>

<template>
    <div>
        <img :src="logo" alt="BlueRoot" :class="heights[size]" class="w-auto object-contain" />
        <p v-if="showText" class="mt-1 truncate text-xs" :class="invert ? 'text-white/70' : 'text-muted-foreground'">
            {{ companyName }}
        </p>
    </div>
</template>
