<script setup>
// Grouped permission checkboxes. `locked` = already given by the role (shown ticked, can't untick here).
import { computed } from 'vue'

const props = defineProps({
    groups: { type: Object, required: true },          // { sales: ['view', 'create', …], … }
    modelValue: { type: Array, default: () => [] },
    locked: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

// Plain-language names for the permissions that matter most in the shop.
const LABELS = {
    'sales.create': 'Use POS (sell)', 'sales.view': 'See sales', 'sales.return': 'Return items', 'sales.delete': 'Cancel bill',
    'sales.discount': 'Change price / give discount', 'sales.update': 'Take invoice payment',
    'customers.collect': 'Receive udhaar / advance', 'customers.create': 'Add customer',
    'expenses.create': 'Add expense', 'cash.view': 'See cash book', 'cash.transfer': 'Bank deposit / withdraw', 'cash.close': 'Close the day',
    'reports.view': 'Reports, profit & dashboard', 'inventory.view': 'See stock', 'inventory.create': 'Stock in / out',
    'purchases.create': 'Make purchase', 'purchases.receive': 'Receive purchase', 'suppliers.update': 'Pay supplier',
}
const GROUP_NAMES = {
    sales: 'Sales / POS', customers: 'Customers & Udhaar', cash: 'Cash Book', expenses: 'Expenses', reports: 'Reports',
    products: 'Products', inventory: 'Stock', purchases: 'Purchases', suppliers: 'Suppliers', gallons: '19L Bottles',
    users: 'Users', roles: 'Roles', settings: 'Settings', consumption: 'Internal use', production: 'Production',
    delivery: 'Delivery', accounting: 'Accounting', employees: 'Employees', payroll: 'Payroll', quality: 'Water quality',
    audit: 'Audit log', notifications: 'Notifications',
}
const label = (name) => LABELS[name] ?? name.split('.')[1].replace(/^./, (c) => c.toUpperCase())
const lockedSet = computed(() => new Set(props.locked))

function toggle(name, on) {
    const next = new Set(props.modelValue)
    on ? next.add(name) : next.delete(name)
    emit('update:modelValue', [...next])
}
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div v-for="(actions, group) in groups" :key="group" class="rounded-xl bg-muted/40 p-3">
            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ GROUP_NAMES[group] ?? group }}</p>
            <label v-for="a in actions" :key="a" class="flex items-center gap-2 py-0.5 text-sm" :class="lockedSet.has(`${group}.${a}`) ? 'opacity-60' : ''">
                <input type="checkbox" class="rounded border-border text-brand focus:ring-brand"
                    :checked="lockedSet.has(`${group}.${a}`) || modelValue.includes(`${group}.${a}`)"
                    :disabled="lockedSet.has(`${group}.${a}`)"
                    @change="toggle(`${group}.${a}`, $event.target.checked)" />
                {{ label(`${group}.${a}`) }}
                <span v-if="lockedSet.has(`${group}.${a}`)" class="text-[10px] text-muted-foreground">(role)</span>
            </label>
        </div>
    </div>
</template>
