import { auth } from './auth'

export function money(amount, decimals = 0) {
    const symbol = auth.settings?.currency?.symbol ?? 'Rs'
    const v = Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: 2 })
    return `${symbol} ${v}`
}

export function qty(n) {
    return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 3 })
}

export function date(d) {
    if (!d) return ''
    return new Date(d.length === 10 ? d + 'T00:00:00' : d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

export const todayISO = () => new Date().toISOString().slice(0, 10)
