import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const THOUSAND = 1_000
const LAC = 100_000
const CRORE = 10_000_000

function trim(n, decimals) {
    let s = Number(n).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    })
    return s
}

/**
 * PKR currency helper mirroring App\Support\Money.
 *   1,000      => Rs 1K
 *   100,000    => Rs 1 Lac
 *   10,000,000 => Rs 1 Crore
 */
export function useCurrency() {
    const page = usePage()

    const symbol = computed(() => page.props.settings?.currency?.symbol ?? 'Rs')

    function format(amount, decimals = 2, withSymbol = true) {
        const value = Number(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        })
        return withSymbol ? `${symbol.value} ${value}` : value
    }

    function short(amount, decimals = 2, withSymbol = true) {
        const value = Number(amount || 0)
        const abs = Math.abs(value)
        const sign = value < 0 ? '-' : ''
        let out

        if (abs >= CRORE) out = `${trim(abs / CRORE, decimals)} Crore`
        else if (abs >= LAC) out = `${trim(abs / LAC, decimals)} Lac`
        else if (abs >= THOUSAND) out = `${trim(abs / THOUSAND, decimals)}K`
        else out = trim(abs, decimals)

        const prefix = withSymbol ? `${symbol.value} ` : ''
        return `${prefix}${sign}${out}`
    }

    return { symbol, format, short }
}
