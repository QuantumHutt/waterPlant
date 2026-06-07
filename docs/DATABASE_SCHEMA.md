# Water Filter Plant ERP — Database Schema

> The **migrations in `database/migrations/` are the source of truth**. This document is the
> human-readable column reference. Types: `id`=BIGINT UNSIGNED PK, money=`DECIMAL(18,2)`,
> qty=`DECIMAL(18,3)`, cost=`DECIMAL(18,4)`. Every operational table has
> `branch_id`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`.

## Phase 1 — Foundation tables (built this session)

### branches
| column | type | notes |
|---|---|---|
| id | id | |
| code | string(20) | unique |
| name | string | |
| address, city | string ◇ | |
| phone, email | string ◇ | |
| is_head_office | bool | default false |
| is_active | bool | default true |
| opening_balance | decimal(18,2) | default 0 |
| timestamps, soft deletes | | |

### users (extends Breeze default)
| column | type | notes |
|---|---|---|
| id | id | |
| branch_id | FK→branches ◇ | null = global/super-admin |
| employee_id | FK→employees ◇ | added in HR module |
| name, email | string | email unique |
| password | string | hashed |
| phone, avatar | string ◇ | |
| is_active | bool | default true |
| last_login_at | timestamp ◇ | |
| last_login_ip | string(45) ◇ | |
| remember_token, timestamps, soft deletes | | |

### Spatie permission tables
`roles` (+`branch_id ◇`), `permissions`, `model_has_roles`, `model_has_permissions`,
`role_has_permissions` — published from `spatie/laravel-permission`.

### settings
| column | type | notes |
|---|---|---|
| id | id | |
| branch_id | FK→branches ◇ | null = global default |
| group | string | company\|currency\|tax\|invoice |
| key | string | |
| value | json ◇ | |
| type | string | string\|int\|bool\|json |
| unique (branch_id, group, key) | | |

### tax_rates
`id, branch_id ◇, name, rate decimal(5,2), is_inclusive bool, is_active bool, timestamps`

### chart_of_accounts (built in Phase 1 so accounting bindings exist)
| column | type | notes |
|---|---|---|
| id | id | |
| branch_id | FK ◇ | null = shared CoA |
| parent_id | FK→self ◇ | grouping |
| code | string(20) | unique |
| name | string | |
| type | enum | asset\|liability\|equity\|income\|expense |
| sub_type | string ◇ | current_asset, fixed_asset, … |
| is_group | bool | header account |
| opening_balance | decimal(18,2) | |
| is_active | bool | |

### Audit / activity / queue (published by packages)
- `audits` (owen-it): `user_id, user_type, event, auditable_type, auditable_id, old_values json, new_values json, url, ip_address, user_agent, tags, created_at`
- `activity_log` (spatie): `log_name, description, subject_*, causer_*, properties json, batch_uuid`
- `personal_access_tokens` (sanctum)
- `jobs, failed_jobs, job_batches, cache, sessions` (framework)

## Phase 2+ tables (defined in ERD.md, migrated per module)

product_categories, products, product_variants, warehouses, stock_items, stock_movements,
stock_transfers(+items), stock_adjustments(+items), stock_audits(+items),
production_batches, production_outputs, production_consumptions,
gallons, gallon_transactions, customer_gallon_balances,
suppliers, purchase_orders(+items), goods_receipts(+items), supplier_payments,
customers, invoices(+items), sales_returns(+items), payments, ledger_entries,
delivery_routes, route_customer, delivery_assignments, delivery_items,
journal_entries, journal_lines,
expense_categories, expenses, incomes,
employees, attendances, payrolls, employee_advances, overtimes,
water_quality_tests, maintenance_schedules, maintenance_logs,
forecasts (AI), notifications.

## Integrity guarantees
1. **Double-entry:** trigger-free, enforced in `JournalService::post()` — rejects any entry
   where `Σ debit ≠ Σ credit`. A `Unit\AccountingBalanceTest` asserts the whole Trial Balance
   nets to `0.00` so a Rs 1 discrepancy fails the suite.
2. **Stock:** `stock_items.quantity` is only ever mutated through `stock_movements` inside a
   transaction (`InventoryService::move()`), keeping the ledger and on-hand in sync.
3. **Branch isolation:** global `HasBranch` scope filters every query to the active branch
   unless the user has a cross-branch permission.
