import { toast } from './toast'

async function request(method, path, body) {
    const res = await fetch(`/api/${path.replace(/^\//, '')}`, {
        method,
        credentials: 'same-origin',
        headers: body ? { 'Content-Type': 'application/json' } : undefined,
        body: body ? JSON.stringify(body) : undefined,
    })
    let data = null
    try { data = await res.json() } catch { /* empty */ }
    if (!res.ok) {
        const err = new Error(data?.error || `Request failed (${res.status})`)
        err.status = res.status
        throw err
    }
    return data
}

export const api = {
    get: (p) => request('GET', p),
    post: (p, b = {}) => request('POST', p, b),
    put: (p, b = {}) => request('PUT', p, b),
    del: (p) => request('DELETE', p),
}

/** Run an API call, toasting success / error. Returns the result or null on failure. */
export async function run(promise, successMessage) {
    try {
        const r = await promise
        if (successMessage) toast(successMessage)
        return r ?? true
    } catch (e) {
        toast(e.message, 'error')
        return null
    }
}
