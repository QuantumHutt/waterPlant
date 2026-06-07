# Water Filter Plant ERP — Entity Relationship Diagram

> Conventions: every business table carries `branch_id` (nullable only for global config),
> `created_by` / `updated_by`, timestamps and `deleted_at` (soft deletes). Audit columns are
> handled by `owen-it/laravel-auditing`; activity by `spatie/laravel-activitylog`. Money is
> stored as `decimal(18,2)` in PKR. All FKs are `unsignedBigInteger` with `restrictOnDelete`
> unless noted.

## 1. Legend

```
PK  primary key          FK  foreign key
1—* one to many          *—* many to many (pivot)
◇   nullable FK          ⊕   polymorphic relation
```

## 2. High-level domain map

```
                         ┌────────────┐
                         │  branches  │ (multi-plant root)
                         └─────┬──────┘
        ┌──────────────┬──────┼─────────────┬───────────────┬──────────────┐
        │              │      │             │               │              │
   ┌────▼────┐   ┌─────▼───┐ ┌▼─────────┐ ┌─▼──────────┐ ┌──▼────────┐ ┌───▼────────┐
   │  users  │   │products │ │inventory │ │ production │ │   sales   │ │ accounting │
   └────┬────┘   └────┬────┘ └────┬─────┘ └─────┬──────┘ └─────┬─────┘ └─────┬──────┘
        │             │           │             │              │             │
   roles/perms   variants/    stock_moves   batches /     invoices /    journal_entries
   (spatie)      categories   adjustments   consumption   payments      / ledger_lines
```

## 3. Authentication & Authorization

```
users (PK id)
  - branch_id FK ◇ -> branches          (a user belongs to a home branch; super admin = null)
  - employee_id FK ◇ -> employees       (link to HR record)
  - name, email (unique), password, phone, avatar, is_active, last_login_at, last_login_ip
  - timestamps, soft deletes

roles            (spatie) PK id, name, guard_name, + branch_id ◇ (branch-scoped roles)
permissions      (spatie) PK id, name, guard_name
model_has_roles  (*—*) user ↔ role
model_has_permissions (*—*) user ↔ permission
role_has_permissions  (*—*) role ↔ permission

personal_access_tokens (sanctum)  ⊕ tokenable -> users

7 seeded roles: super-admin, admin, manager, accountant, production-manager,
                sales-manager, delivery-staff
```

## 4. Multi-Branch & Settings

```
branches (PK id)
  - code (unique), name, address, city, phone, email
  - is_active, is_head_office
  - opening_balance decimal(18,2)
  1—* almost every operational table

settings (PK id)            key/value config store, optionally branch-scoped
  - branch_id FK ◇ -> branches
  - group (company|currency|tax|invoice), key, value (json), type
  - unique (branch_id, group, key)

tax_rates (PK id) -> branch_id ◇, name, rate decimal(5,2), is_inclusive, is_active
```

## 5. Product Management

```
product_categories (PK id) -> branch_id ◇, parent_id ◇ self, name, slug
                              (Bottle / Cap / Label / Chemical / Filter / Membrane / Packaging / FinishedGoods)

products (PK id)
  - branch_id FK ◇, category_id FK -> product_categories
  - sku (unique), barcode (unique ◇), name, type (finished|raw|consumable)
  - unit (pcs|litre|kg|roll), cost_price, sale_price, wholesale_price
  - reorder_level, opening_stock, track_inventory bool, is_active
  1—* product_variants, 1—* stock_movements, 1—* invoice_items

product_variants (PK id) -> product_id FK
  - sku (unique), barcode ◇, name (e.g. "250ml", "500ml", "19L")
  - capacity_ml int ◇, cost_price, sale_price, reorder_level, is_active
```

Predefined finished-good variants: 250ml, 500ml, 1L, 1.5L, 6L, 19L Gallon + Custom.

## 6. Inventory Management

```
warehouses (PK id) -> branch_id FK, name, code, is_default

stock_items (PK id)        materialized on-hand per product/variant/warehouse
  - branch_id, warehouse_id FK, product_id FK, variant_id FK ◇
  - quantity decimal(18,3), avg_cost decimal(18,4)
  - unique (warehouse_id, product_id, variant_id)

stock_movements (PK id)    immutable ledger of every quantity change
  - branch_id, warehouse_id FK, product_id FK, variant_id FK ◇
  - type (in|out|transfer_in|transfer_out|adjustment|damage|wastage|production_in|production_out|sale|purchase|return)
  - quantity decimal(18,3) (signed), unit_cost, reference_type ⊕, reference_id (polymorphic source doc)
  - batch_id FK ◇ -> production_batches, note, moved_at

stock_transfers (PK id) -> from_warehouse_id, to_warehouse_id, status, transferred_at
  1—* stock_transfer_items

stock_adjustments (PK id) -> warehouse_id, reason, status, approved_by ◇
  1—* stock_adjustment_items  (product, variant ◇, system_qty, counted_qty, diff)

stock_audits (PK id) -> warehouse_id, status (draft|in_progress|completed), audited_at
  1—* stock_audit_items
```

## 7. Production Management

```
production_batches (PK id)
  - branch_id, warehouse_id FK, batch_number (unique), operator_id FK -> users/employees
  - production_date, expiry_date, status (planned|in_progress|completed|cancelled)
  - raw_water_litres, filtered_water_litres, wastage_litres
  - bottles_produced int, production_cost decimal(18,2)
  1—* production_outputs (finished product + variant + qty produced)
  1—* production_consumptions (raw/consumable product + qty used)  -> drives stock_movements
  1—* water_quality_tests ◇
```

## 8. Gallon Tracking

```
gallons (PK id)            physical 19L gallon asset registry (optional per-unit tracking)
  - branch_id, barcode ◇, status (in_stock|with_customer|returned|missing|damaged)

gallon_transactions (PK id)
  - branch_id, customer_id FK -> customers, gallon_id FK ◇
  - type (issue|return|deposit|deposit_refund|missing|damaged)
  - quantity int, deposit_amount decimal(18,2), reference_type ⊕, reference_id, transacted_at

customer_gallon_balances (PK id)  materialized: customer_id, held_qty, deposit_held
```

## 9. Purchase Management

```
suppliers (PK id) -> branch_id ◇, code (unique), name, phone, email, address,
                     opening_balance, current_balance (derived)

purchase_orders (PK id)
  - branch_id, supplier_id FK, po_number (unique), status (draft|sent|partial|received|cancelled)
  - order_date, expected_date, subtotal, tax_total, discount, grand_total
  1—* purchase_order_items (product, variant ◇, qty, unit_cost, tax, line_total)

goods_receipts (PK id) -> purchase_order_id FK ◇, supplier_id, grn_number (unique),
                          received_date, warehouse_id  -> creates stock_movements(in)
  1—* goods_receipt_items

supplier_payments (PK id) -> supplier_id, branch_id, amount, method, paid_at,
                             reference_type ⊕ (PO/GRN)  -> posts journal_entry
```

## 10. Sales Management

```
customers (PK id) -> branch_id ◇, code (unique), name, type (walk_in|retail|wholesale|corporate),
                     phone, email, address, credit_limit, opening_balance, current_balance (derived)

invoices (PK id)           POS + wholesale + credit
  - branch_id, customer_id FK ◇ (null = walk-in), invoice_number (unique)
  - type (pos|wholesale|credit), status (draft|paid|partial|unpaid|cancelled|returned)
  - invoice_date, due_date ◇, subtotal, discount, tax_total, grand_total, paid_total, balance
  - salesman_id FK ◇ -> users, warehouse_id FK
  1—* invoice_items (product, variant ◇, qty, unit_price, discount, tax, line_total) -> stock_movements(sale/out)
  1—* payments ⊕
  1—* sales_returns

sales_returns (PK id) -> invoice_id FK, return_number, reason, total -> stock_movements(return/in)
  1—* sales_return_items

payments (PK id)           ⊕ payable -> invoices | supplier_payments | gallon deposits
  - branch_id, customer_id ◇, amount, method (cash|bank|cheque|card|credit_adjust)
  - account_id FK -> chart_of_accounts (cash/bank), paid_at, reference  -> posts journal_entry
```

## 11. Customer / Supplier Ledger

```
ledger_entries (PK id)     party running balance (customers & suppliers)
  - branch_id, party_type ⊕ (customer|supplier), party_id
  - reference_type ⊕, reference_id, debit, credit, balance, entry_date, narration
```

## 12. Delivery Management

```
delivery_routes (PK id) -> branch_id, name, area, is_active
  *—* customers (route_customer pivot)

delivery_assignments (PK id)
  - branch_id, route_id FK ◇, driver_id FK -> users (delivery-staff), vehicle ◇
  - assigned_date, status (assigned|in_transit|completed|partial)
  1—* delivery_items (invoice_id FK ◇, customer_id, gallons_out, gallons_in, status, delivered_at, signature ◇)
```

## 13. Accounting (Double Entry)

```
chart_of_accounts (PK id)
  - branch_id ◇ (◇ = shared CoA), parent_id ◇ self, code (unique), name
  - type (asset|liability|equity|income|expense), sub_type, is_group bool, opening_balance, is_active

journal_entries (PK id)
  - branch_id, entry_number (unique), entry_date, narration
  - reference_type ⊕, reference_id (source: invoice/payment/expense/payroll/batch)
  - is_posted bool, posted_by ◇
  1—* journal_lines  (CONSTRAINT: Σ debit == Σ credit per entry — enforced in service + test)

journal_lines (PK id) -> journal_entry_id FK, account_id FK -> chart_of_accounts
  - debit decimal(18,2), credit decimal(18,2), narration

  Derived reports (no tables, computed): Cash Book, Bank Book, General Ledger,
  Trial Balance, Profit & Loss, Balance Sheet.
```

## 14. Expense / Income Management

```
expense_categories (PK id) -> branch_id ◇, name (Electricity|Diesel|Salaries|Maintenance|Transport|Misc)
expenses (PK id) -> branch_id, category_id FK, account_id FK, amount, paid_via (cash|bank),
                    vendor ◇, spent_at, note  -> posts journal_entry
incomes  (PK id) -> branch_id, account_id FK, source, amount, received_via, received_at -> posts journal_entry
```

## 15. Employee Management (HR)

```
employees (PK id) -> branch_id, code (unique), name, designation, department, phone,
                     cnic ◇, join_date, basic_salary, status
  1—* attendances (employee_id, date, check_in, check_out, status, hours)
  1—* payrolls (employee_id, month, basic, overtime, advances, deductions, net, status) -> journal_entry
  1—* employee_advances (employee_id, amount, taken_at, settled bool)
  1—* overtimes (employee_id, date, hours, rate, amount)
```

## 16. Water Quality Control

```
water_quality_tests (PK id)
  - branch_id, batch_id FK ◇, tds_value, ph_value, temperature, tested_by FK, tested_at, remarks, passed bool

maintenance_schedules (PK id)
  - branch_id, asset (filter|ro_membrane|uv|pump), title, frequency_days,
    last_done_at, next_due_at, assigned_to ◇, status
  1—* maintenance_logs (schedule_id, done_at, done_by, cost, notes)
```

## 17. Cross-cutting

```
audits (owen-it)            ⊕ auditable -> any model
  - user_id, event (created|updated|deleted), old_values json, new_values json,
    url, ip_address, user_agent, tags, created_at

activity_log (spatie)       ⊕ subject, ⊕ causer, log_name, description, properties json

notifications (laravel)     ⊕ notifiable -> users  (low stock / pending payment / overdue / maintenance)

jobs / failed_jobs / job_batches (queue)
```

## 18. Key relationship summary (cardinalities)

| Parent | Child | Rel |
|--------|-------|-----|
| branches | users, products, invoices, batches, journal_entries, … | 1—* |
| products | product_variants, stock_movements, invoice_items | 1—* |
| warehouses | stock_items, stock_movements | 1—* |
| production_batches | production_outputs, production_consumptions, water_quality_tests | 1—* |
| customers | invoices, gallon_transactions, ledger_entries, delivery_items | 1—* |
| suppliers | purchase_orders, goods_receipts, supplier_payments | 1—* |
| invoices | invoice_items, payments, sales_returns | 1—* |
| journal_entries | journal_lines (Σdr=Σcr) | 1—* |
| chart_of_accounts | journal_lines, parent/children | 1—* |
| employees | attendances, payrolls, advances, overtimes | 1—* |
| users | roles, permissions (spatie pivots), personal_access_tokens | *—* / 1—* |

Every financial mutation (sale, purchase, payment, expense, payroll, gallon deposit,
production cost) **must** create a balanced `journal_entry`. The Trial Balance test asserts
`Σ debits − Σ credits == 0` to the paisa — this is how the system catches a Rs 1 difference.
