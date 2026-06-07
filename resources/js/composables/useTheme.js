import { ref } from 'vue'

const BRANDS = [
    { key: 'purple', label: 'Purple', color: 'oklch(0.61 0.24 300)' },
    { key: 'blue', label: 'Blue', color: 'oklch(0.65 0.17 240)' },
    { key: 'teal', label: 'Teal', color: 'oklch(0.7 0.12 180)' },
    { key: 'orange', label: 'Orange', color: 'oklch(0.73 0.19 60)' },
    { key: 'pink', label: 'Pink', color: 'oklch(0.71 0.28 360)' },
]

const isDark = ref(false)
const brand = ref('purple')

function syncFromDom() {
    if (typeof document === 'undefined') return
    isDark.value = document.documentElement.classList.contains('dark')
    brand.value = document.documentElement.getAttribute('data-brand') || 'purple'
}

export function useTheme() {
    syncFromDom()

    function toggleDark() {
        isDark.value = !isDark.value
        document.documentElement.classList.toggle('dark', isDark.value)
        localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
    }

    function setBrand(key) {
        brand.value = key
        document.documentElement.setAttribute('data-brand', key)
        localStorage.setItem('brand', key)
    }

    return { isDark, brand, brands: BRANDS, toggleDark, setBrand }
}
