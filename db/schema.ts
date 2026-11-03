import {
  pgTable, serial, text, timestamp, integer, numeric, boolean, date, jsonb, uniqueIndex, index,
} from "drizzle-orm/pg-core";

const money = (name: string) => numeric(name, { precision: 18, scale: 2, mode: "number" });
const qty = (name: string) => numeric(name, { precision: 18, scale: 3, mode: "number" });

// App-side profile for each Netlify Identity user (role + approval).
export const appUsers = pgTable("app_users", {
  id: serial().primaryKey(),
  identityId: text("identity_id").notNull().unique(),
  email: text().notNull(),
  name: text().notNull().default(""),
  role: text().notNull().default("pending"), // admin | manager | cashier | pending
  isActive: boolean("is_active").notNull().default(true),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const settings = pgTable("settings", {
  key: text().primaryKey(),
  value: jsonb(),
});

export const productCategories = pgTable("product_categories", {
  id: serial().primaryKey(),
  name: text().notNull(),
  type: text().notNull().default("finished_goods"),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const products = pgTable("products", {
  id: serial().primaryKey(),
  categoryId: integer("category_id").references(() => productCategories.id, { onDelete: "set null" }),
  sku: text().notNull().unique(),
  name: text().notNull(),
  type: text().notNull().default("finished"), // finished | raw | consumable
  unit: text().notNull().default("pcs"),
  costPrice: money("cost_price").notNull().default(0),
  salePrice: money("sale_price").notNull().default(0),
  wholesalePrice: money("wholesale_price"),
  stock: qty("stock").notNull().default(0),
  reorderLevel: qty("reorder_level").notNull().default(0),
  isService: boolean("is_service").notNull().default(false),
  isManualPrice: boolean("is_manual_price").notNull().default(false),
  isActive: boolean("is_active").notNull().default(true),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const customers = pgTable("customers", {
  id: serial().primaryKey(),
  code: text().notNull().unique(),
  name: text().notNull(),
  type: text().notNull().default("retail"), // walk_in | retail | wholesale | corporate
  phone: text(),
  email: text(),
  address: text(),
  creditLimit: money("credit_limit").notNull().default(0),
  openingBalance: money("opening_balance").notNull().default(0),
  currentBalance: money("current_balance").notNull().default(0), // + = receivable
  isActive: boolean("is_active").notNull().default(true),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const ledgerEntries = pgTable("ledger_entries", {
  id: serial().primaryKey(),
  customerId: integer("customer_id").notNull().references(() => customers.id, { onDelete: "cascade" }),
  invoiceId: integer("invoice_id"),
  debit: money("debit").notNull().default(0),
  credit: money("credit").notNull().default(0),
  balance: money("balance").notNull().default(0),
  narration: text(),
  entryDate: date("entry_date").notNull(),
  createdAt: timestamp("created_at").defaultNow().notNull(),
}, (t) => [index("ledger_customer_idx").on(t.customerId)]);

export const invoices = pgTable("invoices", {
  id: serial().primaryKey(),
  invoiceNumber: text("invoice_number").notNull().unique(),
  customerId: integer("customer_id").references(() => customers.id, { onDelete: "set null" }),
  status: text().notNull().default("paid"), // paid | partial | unpaid | cancelled
  invoiceDate: date("invoice_date").notNull(),
  subtotal: money("subtotal").notNull().default(0),
  discount: money("discount").notNull().default(0),
  grandTotal: money("grand_total").notNull().default(0),
  paidTotal: money("paid_total").notNull().default(0),
  balance: money("balance").notNull().default(0),
  costTotal: money("cost_total").notNull().default(0),
  bottlesDelivered: integer("bottles_delivered"),
  bottlesReturned: integer("bottles_returned"),
  note: text(),
  createdBy: integer("created_by").references(() => appUsers.id, { onDelete: "set null" }),
  createdAt: timestamp("created_at").defaultNow().notNull(),
}, (t) => [index("invoices_date_idx").on(t.invoiceDate)]);

export const invoiceItems = pgTable("invoice_items", {
  id: serial().primaryKey(),
  invoiceId: integer("invoice_id").notNull().references(() => invoices.id, { onDelete: "cascade" }),
  productId: integer("product_id").references(() => products.id, { onDelete: "set null" }),
  productName: text("product_name").notNull(),
  quantity: qty("quantity").notNull(),
  unitPrice: money("unit_price").notNull(),
  unitCost: money("unit_cost").notNull().default(0),
  lineTotal: money("line_total").notNull(),
});

export const payments = pgTable("payments", {
  id: serial().primaryKey(),
  invoiceId: integer("invoice_id").references(() => invoices.id, { onDelete: "set null" }),
  customerId: integer("customer_id").references(() => customers.id, { onDelete: "set null" }),
  amount: money("amount").notNull(),
  method: text().notNull().default("cash"), // cash | bank | card
  paidAt: date("paid_at").notNull(),
  note: text(),
  createdAt: timestamp("created_at").defaultNow().notNull(),
});

export const expenseCategories = pgTable("expense_categories", {
  id: serial().primaryKey(),
  name: text().notNull(),
}, (t) => [uniqueIndex("expense_categories_name_idx").on(t.name)]);

export const expenses = pgTable("expenses", {
  id: serial().primaryKey(),
  categoryId: integer("category_id").references(() => expenseCategories.id, { onDelete: "set null" }),
  amount: money("amount").notNull(),
  paidVia: text("paid_via").notNull().default("cash"),
  vendor: text(),
  spentAt: date("spent_at").notNull(),
  note: text(),
  createdAt: timestamp("created_at").defaultNow().notNull(),
}, (t) => [index("expenses_date_idx").on(t.spentAt)]);
