import { ref } from 'vue'

export const toastState = ref(null)
let timer = null

export function toast(message, type = 'success') {
    toastState.value = { message, type }
    clearTimeout(timer)
    timer = setTimeout(() => (toastState.value = null), 3500)
}
