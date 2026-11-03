CREATE TABLE "app_users" (
	"id" serial PRIMARY KEY,
	"identity_id" text NOT NULL UNIQUE,
	"email" text NOT NULL,
	"name" text DEFAULT '' NOT NULL,
	"role" text DEFAULT 'pending' NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "customers" (
	"id" serial PRIMARY KEY,
	"code" text NOT NULL UNIQUE,
	"name" text NOT NULL,
	"type" text DEFAULT 'retail' NOT NULL,
	"phone" text,
	"email" text,
	"address" text,
	"credit_limit" numeric(18,2) DEFAULT '0' NOT NULL,
	"opening_balance" numeric(18,2) DEFAULT '0' NOT NULL,
	"current_balance" numeric(18,2) DEFAULT '0' NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "expense_categories" (
	"id" serial PRIMARY KEY,
	"name" text NOT NULL
);
--> statement-breakpoint
CREATE TABLE "expenses" (
	"id" serial PRIMARY KEY,
	"category_id" integer,
	"amount" numeric(18,2) NOT NULL,
	"paid_via" text DEFAULT 'cash' NOT NULL,
	"vendor" text,
	"spent_at" date NOT NULL,
	"note" text,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "invoice_items" (
	"id" serial PRIMARY KEY,
	"invoice_id" integer NOT NULL,
	"product_id" integer,
	"product_name" text NOT NULL,
	"quantity" numeric(18,3) NOT NULL,
	"unit_price" numeric(18,2) NOT NULL,
	"unit_cost" numeric(18,2) DEFAULT '0' NOT NULL,
	"line_total" numeric(18,2) NOT NULL
);
--> statement-breakpoint
CREATE TABLE "invoices" (
	"id" serial PRIMARY KEY,
	"invoice_number" text NOT NULL UNIQUE,
	"customer_id" integer,
	"status" text DEFAULT 'paid' NOT NULL,
	"invoice_date" date NOT NULL,
	"subtotal" numeric(18,2) DEFAULT '0' NOT NULL,
	"discount" numeric(18,2) DEFAULT '0' NOT NULL,
	"grand_total" numeric(18,2) DEFAULT '0' NOT NULL,
	"paid_total" numeric(18,2) DEFAULT '0' NOT NULL,
	"balance" numeric(18,2) DEFAULT '0' NOT NULL,
	"cost_total" numeric(18,2) DEFAULT '0' NOT NULL,
	"bottles_delivered" integer,
	"bottles_returned" integer,
	"note" text,
	"created_by" integer,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "ledger_entries" (
	"id" serial PRIMARY KEY,
	"customer_id" integer NOT NULL,
	"invoice_id" integer,
	"debit" numeric(18,2) DEFAULT '0' NOT NULL,
	"credit" numeric(18,2) DEFAULT '0' NOT NULL,
	"balance" numeric(18,2) DEFAULT '0' NOT NULL,
	"narration" text,
	"entry_date" date NOT NULL,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "payments" (
	"id" serial PRIMARY KEY,
	"invoice_id" integer,
	"customer_id" integer,
	"amount" numeric(18,2) NOT NULL,
	"method" text DEFAULT 'cash' NOT NULL,
	"paid_at" date NOT NULL,
	"note" text,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "product_categories" (
	"id" serial PRIMARY KEY,
	"name" text NOT NULL,
	"type" text DEFAULT 'finished_goods' NOT NULL,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "products" (
	"id" serial PRIMARY KEY,
	"category_id" integer,
	"sku" text NOT NULL UNIQUE,
	"name" text NOT NULL,
	"type" text DEFAULT 'finished' NOT NULL,
	"unit" text DEFAULT 'pcs' NOT NULL,
	"cost_price" numeric(18,2) DEFAULT '0' NOT NULL,
	"sale_price" numeric(18,2) DEFAULT '0' NOT NULL,
	"wholesale_price" numeric(18,2),
	"stock" numeric(18,3) DEFAULT '0' NOT NULL,
	"reorder_level" numeric(18,3) DEFAULT '0' NOT NULL,
	"is_service" boolean DEFAULT false NOT NULL,
	"is_manual_price" boolean DEFAULT false NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_at" timestamp DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "settings" (
	"key" text PRIMARY KEY,
	"value" jsonb
);
--> statement-breakpoint
CREATE UNIQUE INDEX "expense_categories_name_idx" ON "expense_categories" ("name");--> statement-breakpoint
CREATE INDEX "expenses_date_idx" ON "expenses" ("spent_at");--> statement-breakpoint
CREATE INDEX "invoices_date_idx" ON "invoices" ("invoice_date");--> statement-breakpoint
CREATE INDEX "ledger_customer_idx" ON "ledger_entries" ("customer_id");--> statement-breakpoint
ALTER TABLE "expenses" ADD CONSTRAINT "expenses_category_id_expense_categories_id_fkey" FOREIGN KEY ("category_id") REFERENCES "expense_categories"("id") ON DELETE SET NULL;--> statement-breakpoint
ALTER TABLE "invoice_items" ADD CONSTRAINT "invoice_items_invoice_id_invoices_id_fkey" FOREIGN KEY ("invoice_id") REFERENCES "invoices"("id") ON DELETE CASCADE;--> statement-breakpoint
ALTER TABLE "invoice_items" ADD CONSTRAINT "invoice_items_product_id_products_id_fkey" FOREIGN KEY ("product_id") REFERENCES "products"("id") ON DELETE SET NULL;--> statement-breakpoint
ALTER TABLE "invoices" ADD CONSTRAINT "invoices_customer_id_customers_id_fkey" FOREIGN KEY ("customer_id") REFERENCES "customers"("id") ON DELETE SET NULL;--> statement-breakpoint
ALTER TABLE "invoices" ADD CONSTRAINT "invoices_created_by_app_users_id_fkey" FOREIGN KEY ("created_by") REFERENCES "app_users"("id") ON DELETE SET NULL;--> statement-breakpoint
ALTER TABLE "ledger_entries" ADD CONSTRAINT "ledger_entries_customer_id_customers_id_fkey" FOREIGN KEY ("customer_id") REFERENCES "customers"("id") ON DELETE CASCADE;--> statement-breakpoint
ALTER TABLE "payments" ADD CONSTRAINT "payments_invoice_id_invoices_id_fkey" FOREIGN KEY ("invoice_id") REFERENCES "invoices"("id") ON DELETE SET NULL;--> statement-breakpoint
ALTER TABLE "payments" ADD CONSTRAINT "payments_customer_id_customers_id_fkey" FOREIGN KEY ("customer_id") REFERENCES "customers"("id") ON DELETE SET NULL;--> statement-breakpoint
ALTER TABLE "products" ADD CONSTRAINT "products_category_id_product_categories_id_fkey" FOREIGN KEY ("category_id") REFERENCES "product_categories"("id") ON DELETE SET NULL;