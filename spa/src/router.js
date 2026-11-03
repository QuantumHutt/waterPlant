import { createRouter, createWebHistory } from 'vue-router'
import { h } from 'vue'
import { auth, hasRole } from './lib/auth'

// Placeholder until each page is ported.
const Placeholder = { render: () => h('div', { class: 'p-8 text-center text-muted-foreground' }, 'This page is being ported to the new Netlify version.') }

const routes = [
    { path: '/', redirect: () => (hasRole('manager') ? '/dashboard' : '/sales') },
    { path: '/login', name: 'login', component: Placeholder, meta: { guest: true } },
    { path: '/pending', name: 'pending', component: Placeholder, meta: { pending: true } },
    { path: '/dashboard', name: 'dashboard', component: Placeholder, meta: { role: 'manager' } },
    { path: '/products', name: 'products', component: Placeholder, meta: { role: 'cashier' } },
    { path: '/customers', name: 'customers', component: Placeholder, meta: { role: 'cashier' } },
    { path: '/sales', name: 'sales', component: Placeholder, meta: { role: 'cashier' } },
    { path: '/sales/:id', name: 'invoice', component: Placeholder, meta: { role: 'cashier', bare: true } },
    { path: '/expenses', name: 'expenses', component: Placeholder, meta: { role: 'manager' } },
    { path: '/users', name: 'users', component: Placeholder, meta: { role: 'admin' } },
    { path: '/settings', name: 'settings', component: Placeholder, meta: { role: 'admin' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: Placeholder },
]

export const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to) => {
    const loggedIn = !!auth.user
    if (to.meta.guest) return loggedIn ? '/' : true
    if (!loggedIn) return { name: 'login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} }
    const pending = auth.user.role === 'pending' || !auth.user.isActive
    if (pending) return to.meta.pending ? true : { name: 'pending' }
    if (to.meta.pending) return '/'
    if (to.meta.role && !hasRole(to.meta.role)) return '/'
    return true
})
