import { reactive } from 'vue'
import {
    getUser, login as idLogin, logout as idLogout, signup as idSignup, handleAuthCallback,
    requestPasswordRecovery, updateUser, acceptInvite,
} from '@netlify/identity'

export const auth = reactive({
    ready: false,
    identityUser: null, // Netlify Identity user
    user: null,         // app profile (role, approval) from /api/me
    settings: null,
    error: null,
    callback: null,     // { type: 'recovery' | 'invite' | 'confirmation', token? } from email links
})

const ROLE_RANK = { pending: 0, cashier: 1, manager: 2, admin: 3 }

/** True if the current user's role is at least `role`. */
export function hasRole(role) {
    return (ROLE_RANK[auth.user?.role] ?? -1) >= ROLE_RANK[role]
}

export async function loadProfile() {
    auth.error = null
    try {
        const res = await fetch('/api/me', { credentials: 'same-origin' })
        if (res.status === 401) { auth.user = null; return }
        const data = await res.json()
        auth.user = data.user
        auth.settings = data.settings
    } catch (e) {
        auth.error = 'Could not reach the server.'
    }
}

export async function initAuth() {
    try {
        const result = await handleAuthCallback()
        if (result) auth.callback = { type: result.type, token: result.token }
    } catch (e) {
        auth.callback = { type: 'error', message: e?.message || 'This link is invalid or has expired.' }
    }
    auth.identityUser = await getUser()
    if (auth.identityUser) await loadProfile()
    auth.ready = true
}

export async function login(email, password) {
    auth.identityUser = await idLogin(email, password)
    await loadProfile()
}

export async function signup(email, password, name) {
    return idSignup(email, password, { full_name: name })
}

export async function logout() {
    try { await idLogout() } catch { /* ignore */ }
    auth.identityUser = null
    auth.user = null
}

export const recoverPassword = (email) => requestPasswordRecovery(email)

export async function setNewPassword(password) {
    await updateUser({ password })
    auth.callback = null
    auth.identityUser = await getUser()
    await loadProfile()
}

export async function acceptInviteWithPassword(token, password) {
    auth.identityUser = await acceptInvite(token, password)
    auth.callback = null
    await loadProfile()
}
