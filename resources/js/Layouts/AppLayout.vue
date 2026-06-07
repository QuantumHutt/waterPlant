<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Sidebar from '@/Components/Sidebar.vue'
import Topbar from '@/Components/Topbar.vue'

const sidebarOpen = ref(false)
const page = usePage()
const flash = ref(null)

watch(
    () => page.props.flash,
    (f) => {
        if (f?.success) flash.value = { type: 'success', message: f.success }
        else if (f?.error) flash.value = { type: 'error', message: f.error }
        if (flash.value) setTimeout(() => (flash.value = null), 3500)
    },
    { immediate: true, deep: true },
)
</script>

<template>
    <div class="min-h-screen bg-background">
        <div class="mx-auto px-2 py-3 sm:px-4 sm:py-6">
            <div class="overflow-hidden rounded-3xl bg-card shadow-sm ring-1 ring-border">
                <!-- Mobile overlay -->
                <div
                    v-if="sidebarOpen"
                    class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    @click="sidebarOpen = false"
                />

                <div class="flex h-[95vh]">
                    <!-- Sidebar -->
                    <div
                        class="fixed inset-y-0 left-0 z-50 transform transition-transform duration-300 ease-in-out lg:relative lg:z-auto"
                        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                    >
                        <Sidebar :on-close="() => (sidebarOpen = false)" />
                    </div>

                    <!-- Main -->
                    <main class="flex-1 w-full overflow-auto rounded-b-3xl bg-muted p-3 sm:p-5 md:px-7 md:py-6 lg:w-auto lg:rounded-r-3xl lg:rounded-bl-none">
                        <Topbar :on-menu-click="() => (sidebarOpen = true)" />

                        <!-- Flash -->
                        <transition name="fade">
                            <div
                                v-if="flash"
                                class="mb-4 rounded-2xl px-4 py-3 text-sm font-medium ring-1"
                                :class="flash.type === 'success'
                                    ? 'bg-success/10 text-success ring-success/20'
                                    : 'bg-destructive/10 text-destructive ring-destructive/20'"
                            >
                                {{ flash.message }}
                            </div>
                        </transition>

                        <div v-if="$slots.header" class="mb-5">
                            <slot name="header" />
                        </div>

                        <slot />
                    </main>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
