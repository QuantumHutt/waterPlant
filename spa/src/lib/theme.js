import { ref } from 'vue'

export const BRANDS = [
    { key: 'blue', color: '#012b94' },
    { key: 'purple', color: 'oklch(0.61 0.24 300)' },
    { key: 'teal', color: 'oklch(0.7 0.12 180)' },
    { key: 'orange', color: 'oklch(0.73 0.19 60)' },
    { key: 'pink', color: 'oklch(0.71 0.28 360)' },
]

export const isDark = ref(document.documentElement.classList.contains('dark'))
export const brand = ref(document.documentElement.getAttribute('data-brand') || 'blue')

export function toggleDark() {
    isDark.value = !isDark.value
    document.documentElement.classList.toggle('dark', isDark.value)
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
}

export function setBrand(key) {
    brand.value = key
    document.documentElement.setAttribute('data-brand', key)
    localStorage.setItem('brand', key)
}
