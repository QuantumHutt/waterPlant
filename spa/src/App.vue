<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from './components/AppLayout.vue'
import { auth } from './lib/auth'
import { toastState } from './lib/toast'

const route = useRoute()
const withLayout = computed(() => auth.user && auth.user.role !== 'pending' && !route.meta.guest && !route.meta.bare && !route.meta.pending)
</script>

<template>
    <AppLayout v-if="withLayout"><RouterView /></AppLayout>
    <RouterView v-else />

    <transition name="fade">
        <div
            v-if="toastState"
            class="no-print fixed left-1/2 top-5 z-[130] max-w-[calc(100vw-2rem)] -translate-x-1/2 rounded-full px-6 py-3 text-sm font-semibold text-white shadow-lg"
            :class="toastState.type === 'error' ? 'bg-destructive' : 'bg-success'"
        >
            {{ toastState.message }}
        </div>
    </transition>
</template>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
