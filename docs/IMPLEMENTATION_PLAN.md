# Water Filter Plant ERP — Implementation Plan & Folder Structure

## 0. Tech stack (as built)

| Layer | Choice |
|-------|--------|
| Framework | Laravel 12 (PHP 8.3 runtime; 8.4-ready) |
| Auth | Sanctum (API tokens) + Inertia session auth (Breeze) |
| Authorization | spatie/laravel-permission (7 roles, granular permissions, Policies) |
| Audit trail | owen-it/laravel-auditing (old/new values, IP, user, UA) |
| Activity log | spatie/laravel-activitylog |
| Frontend | Vue 3 + Inertia.js + Vite |
| Styling | Tailwind CSS (smart-home theme ported: sidebar+topbar, rounded-3xl cards, brand color, light/dark) |
| Async | Laravel Queue (database driver) + Scheduler |
| DB | MySQL (`water_plant`), `water_plant_test` for tests |
| Tests | PHPUnit (Feature + Unit), in-memory sqlite for fast CI / MySQL for integration |

## 1. Clean / layered architecture

```
HTTP (Controller, FormRequest, Resource, Inertia page)
        │  thin — validation + delegate, no business logic
        ▼
Service  (App\Services\*)            ← business rules, orchestration, DB::transaction
        │  depends on Repository INTERFACES (DI via container)
        ▼
Repository (App\Repositories\* impl + App\Repositories\Contracts\*)  ← all Eloquent queries
        │
        ▼
Model (App\Models\*)  ← relations, casts, scopes, Auditable, LogsActivity, HasBranch
```

Rules:
- Controllers never touch Eloquent directly — only Services.
- Services never build queries — only Repositories.
- Every multi-write operation wrapped in `DB::transaction()`.
- Validation only in FormRequests; authorization only in Policies/`can:` middleware.
- API returns `JsonResource` via `ApiResponse` helper; web returns `Inertia::render`.

## 2. Folder structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/                 # REST controllers (Sanctum) — versioned under routes/api.php
│   │   └── Web/                 # Inertia controllers
│   ├── Requests/<Module>/       # FormRequest per action (Store/Update/...)
│   ├── Resources/               # JsonResource transformers
│   └── Middleware/              # SetActiveBranch, EnsureBranchAccess
├── Models/                      # Eloquent models (+ concerns)
│   └── Concerns/                # HasBranch, HasAuditTrail, Blameable traits
├── Repositories/
│   ├── Contracts/               # *RepositoryInterface
│   └── Eloquent/                # BaseRepository + concrete repos
├── Services/                    # one service per aggregate (ProductService, SaleService, AccountingService…)
│   └── Accounting/              # JournalService, LedgerService, ReportService
├── Policies/                    # one policy per model
├── Support/                     # Money (PKR K/Lac/Crore), ApiResponse, Enums/
│   └── Enums/                   # StockMovementType, InvoiceStatus, AccountType, RoleName…
├── Actions/                     # single-purpose domain actions (PostJournalEntry, AdjustStock…)
├── Notifications/               # LowStock, PaymentDue, OverdueCustomer, MaintenanceDue
├── Jobs/                        # GenerateForecast, RecalculateLedger, SendNotifications
├── Console/Commands/            # scheduler commands (check:low-stock, check:overdue…)
└── Providers/
    └── RepositoryServiceProvider.php   # binds interfaces -> implementations

database/
├── migrations/                  # one file per table, ordered by dependency
├── seeders/                     # Role/Permission, Branch, ChartOfAccounts, Product, DemoData
└── factories/                   # factory per model

resources/
├── js/
│   ├── Layouts/                 # AppLayout (sidebar+topbar), AuthLayout, GuestLayout
│   ├── Components/              # ui/ (Card, Button, StatCard, DataTable…), charts/
│   ├── Pages/                   # Inertia pages mirroring modules
│   │   ├── Dashboard/  Products/  Inventory/  Production/  Sales/  Purchases/
│   │   ├── Customers/  Suppliers/  Delivery/  Accounting/  Expenses/
│   │   ├── Employees/  Quality/   Reports/    Settings/    Branches/  Users/
│   ├── composables/             # useCurrency (Rs/K/Lac/Crore), useBranch, usePermissions
│   └── app.js
└── css/app.css                  # Tailwind + theme tokens ported from smart-home

routes/
├── web.php                      # Inertia routes (auth + module pages)
├── api.php                      # REST API (Sanctum) v1
├── console.php                  # scheduled commands
└── auth.php                     # Breeze auth routes

tests/
├── Feature/<Module>/            # endpoint + policy + transaction tests
└── Unit/                        # Money formatter, JournalService balance, services
```

## 3. Module-wise build order (each = migration→model→repo→service→policy→request→controller→route→resource→Vue page→factory→seeder→test)

| # | Module | Status this session | Depends on |
|---|--------|--------------------|-----------|
| 0 | **Core architecture** (Base repo/service, Money, Enums, traits, ApiResponse) | ✅ Phase 1 | — |
| 1 | **Auth & RBAC** (users, roles, perms, policies, Sanctum) | ✅ Phase 1 | 0 |
| 2 | **Multi-Branch** (branches, branch scope, middleware) | ✅ Phase 1 | 0 |
| 3 | **Settings** (company/currency/tax/invoice) | ✅ Phase 1 | 2 |
| 4 | **Dashboard** (7 KPI widgets + theme) | ✅ Phase 1 (shell) | 1,2 |
| 5 | Product Management (categories, products, variants) | ▶ next | 2,3 |
| 6 | Inventory (warehouses, stock_items, movements, transfer, adjustment, audit, damage) | ▶ | 5 |
| 7 | Production (batches, outputs, consumptions) | ▶ | 6 |
| 8 | Gallon Tracking | ▶ | 6,9 |
| 9 | Customer Management (+ ledger) | ▶ | 2 |
| 10 | Supplier / Purchase (PO, GRN, payments) | ▶ | 6 |
| 11 | Sales (POS, wholesale, credit, returns, discounts, barcode) | ▶ | 5,6,9,13 |
| 12 | Delivery (routes, assignments, tracking, confirmation) | ▶ | 9,11 |
| 13 | **Accounting** (CoA, journals, ledger, cash/bank book, TB, P&L, BS) | ▶ core | 2 |
| 14 | Expense / Income | ▶ | 13 |
| 15 | Employee / HR (attendance, payroll, advances, overtime) | ▶ | 13 |
| 16 | Water Quality (TDS/pH, tests, maintenance schedule) | ▶ | 7 |
| 17 | Reporting (all reports + exports) | ▶ | most |
| 18 | Audit System (already wired via owen-it; UI viewer) | ▶ | 1 |
| 19 | Notifications (low stock, payments, overdue, maintenance) | ▶ | 6,9,16 |
| 20 | AI Features (demand/sales/inventory forecast, auto-purchase suggestions) | ▶ | 11,6 |

## 4. Phases (deliverables)

- **Phase 1 — Foundation (this session):** project, packages, core architecture, Auth/RBAC,
  Multi-branch, Settings, Dashboard shell + theme, migrations+seeders for foundation, tests
  green. Planning docs (this file + ERD + SCHEMA).
- **Phase 2 — Backend APIs & business logic:** modules 5–16 services/repos/controllers/APIs.
- **Phase 3 — Vue frontend:** Inertia pages for every module.
- **Phase 4 — Reports:** module 17 + exportable PDFs/Excel.
- **Phase 5 — Testing:** full Feature/Unit coverage, double-entry balance assertions, policy tests.

## 5. Roles → default permission matrix (seeded)

| Permission group | super-admin | admin | manager | accountant | production-mgr | sales-mgr | delivery-staff |
|---|---|---|---|---|---|---|---|
| users.* / roles.* | ✓ | ✓ | view | – | – | – | – |
| branches.* | ✓ | ✓ | view | view | – | – | – |
| settings.* | ✓ | ✓ | – | tax/invoice | – | – | – |
| products.* / inventory.* | ✓ | ✓ | ✓ | view | view | view | – |
| production.* | ✓ | ✓ | ✓ | view | ✓ | – | – |
| sales.* / customers.* | ✓ | ✓ | ✓ | view | – | ✓ | view |
| purchases.* / suppliers.* | ✓ | ✓ | ✓ | ✓ | – | – | – |
| delivery.* | ✓ | ✓ | ✓ | – | – | view | ✓ (own) |
| accounting.* / expenses.* | ✓ | ✓ | view | ✓ | – | – | – |
| employees.* / payroll.* | ✓ | ✓ | ✓ | ✓ | – | – | – |
| quality.* | ✓ | ✓ | ✓ | – | ✓ | – | – |
| reports.* | ✓ | ✓ | ✓ | ✓ | prod only | sales only | – |
| audit.view | ✓ | ✓ | – | view | – | – | – |

super-admin bypasses all checks via `Gate::before`.

## 6. Currency rule (PKR)

`App\Support\Money` + `useCurrency` composable render:
`1000 → Rs 1K`, `100000 → Rs 1 Lac`, `10000000 → Rs 1 Crore`, with exact `Rs 1,234,567.00`
available for ledgers/invoices. Stored as `decimal(18,2)`; never floated in calculations
(use bcmath / integer paisa in accounting service).
