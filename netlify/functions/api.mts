import type { Config } from "@netlify/functions";
import { getUser } from "@netlify/identity";
import { and, asc, count, desc, eq, gte, ilike, lte, ne, or, sql, sum } from "drizzle-orm";
import { db } from "../../db/index.js";
import {
  appUsers, customers, expenseCategories, expenses, invoiceItems, invoices, ledgerEntries,
  payments, productCategories, products, settings,
} from "../../db/schema.js";

type AppUser = typeof appUsers.$inferSelect;
type Role = "admin" | "manager" | "cashier";

class HttpError extends Error {
  constructor(public status: number, message: string) { super(message); }
}

const json = (data: unknown, status = 200) => Response.json(data, { status });
const today = () => new Date().toISOString().slice(0, 10);
const round2 = (n: number) => Math.round((Number(n) || 0) * 100) / 100;
const num = (v: unknown, fallback = 0) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : fallback;
};
const str = (v: unknown) => (typeof v === "string" && v.trim() !== "" ? v.trim() : null);
const required = (v: unknown, field: string) => {
  const s = str(v);
  if (!s) throw new HttpError(422, `${field} is required`);
  return s;
};

function allow(user: AppUser, ...roles: Role[]) {
  if (user.role === "admin") return;
  if (!roles.includes(user.role as Role)) throw new HttpError(403, "You do not have permission for this action");
}

// ---------------------------------------------------------------- auth
async function currentAppUser(): Promise<AppUser | null> {
  const idUser = await getUser();
  if (!idUser) return null;

  const [existing] = await db.select().from(appUsers).where(eq(appUsers.identityId, idUser.id));
  if (existing) return existing;

  // First account ever becomes admin; everyone after waits for approval.
  const [{ total }] = await db.select({ total: count() }).from(appUsers);
  const [created] = await db.insert(appUsers).values({
    identityId: idUser.id,
    email: idUser.email ?? "",
    name: idUser.name ?? idUser.email?.split("@")[0] ?? "User",
    role: total === 0 ? "admin" : "pending",
  }).onConflictDoNothing().returning();
  if (created) return created;
  const [again] = await db.select().from(appUsers).where(eq(appUsers.identityId, idUser.id));
  return again ?? null;
}

// ---------------------------------------------------------------- settings
const DEFAULT_SETTINGS = {
  company: { name: "AL-Noor Water Plant & Filter House", phone: "", address: "" },
  currency: { symbol: "Rs" },
  invoice: { footer: "Thank you for your business!" },
};

async function getSettings() {
  const rows = await db.select().from(settings);
  const out: Record<string, any> = structuredClone(DEFAULT_SETTINGS);
  for (const r of rows) out[r.key] = { ...(out[r.key] ?? {}), ...(r.value as object) };
  return out;
}

// ---------------------------------------------------------------- handlers
async function dashboard() {
  const t = today();
  const monthStart = t.slice(0, 8) + "01";
  const notCancelled = ne(invoices.status, "cancelled");

  const salesAgg = (from: string) =>
    db.select({
      total: sum(invoices.grandTotal), cost: sum(invoices.costTotal), count: count(),
    }).from(invoices).where(and(notCancelled, gte(invoices.invoiceDate, from), lte(invoices.invoiceDate, t)));

  const [[todayS], [monthS], [monthE], [receivable], [lowStock], recent, trend] = await Promise.all([
    salesAgg(t),
    salesAgg(monthStart),
    db.select({ total: sum(expenses.amount) }).from(expenses).where(gte(expenses.spentAt, monthStart)),
    db.select({ total: sum(customers.currentBalance) }).from(customers),
    db.select({ c: count() }).from(products).where(and(
      eq(products.isService, false), eq(products.isActive, true), lte(products.stock, products.reorderLevel),
    )),
    db.select({
      id: invoices.id, invoiceNumber: invoices.invoiceNumber, grandTotal: invoices.grandTotal,
      status: invoices.status, invoiceDate: invoices.invoiceDate, customerName: customers.name,
    }).from(invoices).leftJoin(customers, eq(invoices.customerId, customers.id))
      .orderBy(desc(invoices.id)).limit(8),
    db.select({ day: invoices.invoiceDate, total: sum(invoices.grandTotal) }).from(invoices)
      .where(and(notCancelled, gte(invoices.invoiceDate, sql`(current_date - interval '13 days')::date`)))
      .groupBy(invoices.invoiceDate).orderBy(asc(invoices.invoiceDate)),
  ]);

  const monthSales = num(monthS.total);
  const monthExpenses = num(monthE.total);
  return {
    todaySales: num(todayS.total), todayCount: todayS.count,
    monthSales, monthCount: monthS.count, monthExpenses,
    monthProfit: round2(monthSales - num(monthS.cost) - monthExpenses),
    receivable: num(receivable.total), lowStock: lowStock.c,
    recent, trend: trend.map((r) => ({ day: r.day, total: num(r.total) })),
  };
}

// ----- products
function productValues(b: any) {
  return {
    categoryId: b.categoryId ? num(b.categoryId) : null,
    sku: required(b.sku, "SKU"),
    name: required(b.name, "Name"),
    type: str(b.type) ?? "finished",
    unit: str(b.unit) ?? "pcs",
    costPrice: round2(num(b.costPrice)),
    salePrice: round2(num(b.salePrice)),
    wholesalePrice: b.wholesalePrice === null || b.wholesalePrice === "" || b.wholesalePrice === undefined ? null : round2(num(b.wholesalePrice)),
    reorderLevel: num(b.reorderLevel),
    isService: !!b.isService,
    isManualPrice: !!b.isManualPrice,
    isActive: b.isActive !== false,
  };
}

// ----- customers
async function nextCustomerCode(tx: typeof db) {
  const [r] = await tx.select({ max: sql<number>`coalesce(max(${customers.id}), 0)` }).from(customers);
  return `C-${String(Number(r.max) + 1).padStart(4, "0")}`;
}

async function addLedger(tx: any, customerId: number, debit: number, credit: number, narration: string, invoiceId: number | null = null) {
  const [c] = await tx.update(customers)
    .set({ currentBalance: sql`${customers.currentBalance} + ${round2(debit - credit)}` })
    .where(eq(customers.id, customerId)).returning({ balance: customers.currentBalance });
  if (!c) throw new HttpError(404, "Customer not found");
  await tx.insert(ledgerEntries).values({
    customerId, invoiceId, debit: round2(debit), credit: round2(credit),
    balance: num(c.balance), narration, entryDate: today(),
  });
}

async function customerPayment(customerId: number, b: any) {
  const amount = round2(num(b.amount));
  if (amount <= 0) throw new HttpError(422, "Amount must be greater than zero");
  const method = str(b.method) ?? "cash";
  return db.transaction(async (tx) => {
    const [c] = await tx.select().from(customers).where(eq(customers.id, customerId));
    if (!c) throw new HttpError(404, "Customer not found");
    if (amount > num(c.currentBalance)) throw new HttpError(422, "Amount is more than the balance owed");

    // Settle the oldest open invoices first.
    let left = amount;
    const open = await tx.select().from(invoices)
      .where(and(eq(invoices.customerId, customerId), sql`${invoices.balance} > 0`, ne(invoices.status, "cancelled")))
      .orderBy(asc(invoices.id));
    for (const inv of open) {
      if (left <= 0) break;
      const apply = Math.min(left, num(inv.balance));
      const newBal = round2(num(inv.balance) - apply);
      await tx.update(invoices).set({
        paidTotal: round2(num(inv.paidTotal) + apply), balance: newBal, status: newBal <= 0 ? "paid" : "partial",
      }).where(eq(invoices.id, inv.id));
      await tx.insert(payments).values({ invoiceId: inv.id, customerId, amount: round2(apply), method, paidAt: today(), note: str(b.note) });
      left = round2(left - apply);
    }
    if (left > 0) {
      await tx.insert(payments).values({ customerId, amount: left, method, paidAt: today(), note: str(b.note) ?? "On account" });
    }
    await addLedger(tx, customerId, 0, amount, `Payment received (${method})${str(b.note) ? " — " + str(b.note) : ""}`);
    return { ok: true };
  });
}

// ----- invoices
async function createInvoice(user: AppUser, b: any) {
  const items: any[] = Array.isArray(b.items) ? b.items : [];
  if (items.length === 0) throw new HttpError(422, "Add at least one item");
  const customerId = b.customerId ? num(b.customerId) : null;

  return db.transaction(async (tx) => {
    let subtotal = 0, costTotal = 0;
    const lines: any[] = [];
    for (const it of items) {
      const [p] = await tx.select().from(products).where(eq(products.id, num(it.productId)));
      if (!p) throw new HttpError(422, "Product not found");
      const quantity = num(it.quantity);
      if (quantity <= 0) throw new HttpError(422, `Quantity for ${p.name} must be positive`);
      const unitPrice = round2(it.unitPrice === undefined || it.unitPrice === null || it.unitPrice === "" ? num(p.salePrice) : num(it.unitPrice));
      if (unitPrice < 0) throw new HttpError(422, `Price for ${p.name} is invalid`);
      const lineTotal = round2(quantity * unitPrice);
      subtotal += lineTotal;
      costTotal += quantity * num(p.costPrice);
      lines.push({ productId: p.id, productName: p.name, quantity, unitPrice, unitCost: num(p.costPrice), lineTotal, track: !p.isService });
    }
    subtotal = round2(subtotal);
    const discount = Math.min(round2(num(b.discount)), subtotal);
    const grandTotal = round2(subtotal - discount);
    const paid = Math.min(round2(num(b.paid, grandTotal)), grandTotal);
    const balance = round2(grandTotal - paid);
    if (balance > 0 && !customerId) throw new HttpError(422, "Select a customer for credit (udhaar) sales");

    const [inv] = await tx.insert(invoices).values({
      invoiceNumber: `TMP-${crypto.randomUUID()}`,
      customerId, invoiceDate: str(b.invoiceDate) ?? today(),
      subtotal, discount, grandTotal, paidTotal: paid, balance, costTotal: round2(costTotal),
      status: balance <= 0 ? "paid" : paid > 0 ? "partial" : "unpaid",
      bottlesDelivered: b.bottlesDelivered === "" || b.bottlesDelivered == null ? null : num(b.bottlesDelivered),
      bottlesReturned: b.bottlesReturned === "" || b.bottlesReturned == null ? null : num(b.bottlesReturned),
      note: str(b.note), createdBy: user.id,
    }).returning();
    const invoiceNumber = `INV-${String(inv.id).padStart(6, "0")}`;
    await tx.update(invoices).set({ invoiceNumber }).where(eq(invoices.id, inv.id));

    await tx.insert(invoiceItems).values(lines.map(({ track, ...l }) => ({ ...l, invoiceId: inv.id })));
    for (const l of lines) {
      if (l.track) await tx.update(products).set({ stock: sql`${products.stock} - ${l.quantity}` }).where(eq(products.id, l.productId));
    }
    if (paid > 0) {
      await tx.insert(payments).values({ invoiceId: inv.id, customerId, amount: paid, method: str(b.method) ?? "cash", paidAt: today() });
    }
    if (customerId) {
      await addLedger(tx, customerId, grandTotal, 0, `Sale ${invoiceNumber}`, inv.id);
      if (paid > 0) await addLedger(tx, customerId, 0, paid, `Payment on ${invoiceNumber}`, inv.id);
    }
    return { id: inv.id, invoiceNumber };
  });
}

async function invoicePayment(id: number, b: any) {
  const [inv] = await db.select().from(invoices).where(eq(invoices.id, id));
  if (!inv) throw new HttpError(404, "Invoice not found");
  if (inv.status === "cancelled") throw new HttpError(422, "Invoice is cancelled");
  const amount = Math.min(round2(num(b.amount)), num(inv.balance));
  if (amount <= 0) throw new HttpError(422, "Nothing to collect");
  return db.transaction(async (tx) => {
    const newBal = round2(num(inv.balance) - amount);
    await tx.update(invoices).set({
      paidTotal: round2(num(inv.paidTotal) + amount), balance: newBal, status: newBal <= 0 ? "paid" : "partial",
    }).where(eq(invoices.id, id));
    await tx.insert(payments).values({ invoiceId: id, customerId: inv.customerId, amount, method: str(b.method) ?? "cash", paidAt: today() });
    if (inv.customerId) await addLedger(tx, inv.customerId, 0, amount, `Payment on ${inv.invoiceNumber}`, id);
    return { ok: true };
  });
}

async function cancelInvoice(id: number) {
  return db.transaction(async (tx) => {
    const [inv] = await tx.select().from(invoices).where(eq(invoices.id, id));
    if (!inv) throw new HttpError(404, "Invoice not found");
    if (inv.status === "cancelled") throw new HttpError(422, "Already cancelled");
    const items = await tx.select().from(invoiceItems).where(eq(invoiceItems.invoiceId, id));
    for (const it of items) {
      if (!it.productId) continue;
      await tx.update(products).set({ stock: sql`${products.stock} + ${it.quantity}` })
        .where(and(eq(products.id, it.productId), eq(products.isService, false)));
    }
    // Reverse only the unpaid part on the customer's account; money already received is refunded in cash.
    if (inv.customerId && num(inv.balance) > 0) {
      await addLedger(tx, inv.customerId, 0, num(inv.balance), `Cancelled ${inv.invoiceNumber}`, id);
    }
    await tx.update(invoices).set({ status: "cancelled", balance: 0 }).where(eq(invoices.id, id));
    return { ok: true };
  });
}

// ---------------------------------------------------------------- router
async function route(req: Request, user: AppUser): Promise<Response> {
  const url = new URL(req.url);
  const parts = url.pathname.replace(/^\/api\/?/, "").split("/").filter(Boolean);
  const [res, idRaw, action] = parts;
  const id = idRaw ? Number(idRaw) : NaN;
  const m = req.method;
  const body = m === "POST" || m === "PUT" ? await req.json().catch(() => ({})) : {};

  switch (res) {
    case "settings":
      if (m === "GET") return json(await getSettings());
      if (m === "PUT") {
        allow(user);
        for (const key of Object.keys(DEFAULT_SETTINGS)) {
          if (body[key] && typeof body[key] === "object") {
            await db.insert(settings).values({ key, value: body[key] })
              .onConflictDoUpdate({ target: settings.key, set: { value: body[key] } });
          }
        }
        return json(await getSettings());
      }
      break;

    case "dashboard":
      allow(user, "manager");
      return json(await dashboard());

    case "categories":
      if (m === "GET") return json(await db.select().from(productCategories).orderBy(asc(productCategories.name)));
      allow(user, "manager");
      if (m === "POST") {
        const [c] = await db.insert(productCategories).values({ name: required(body.name, "Name"), type: str(body.type) ?? "finished_goods" }).returning();
        return json(c, 201);
      }
      if (m === "DELETE" && id) { await db.delete(productCategories).where(eq(productCategories.id, id)); return json({ ok: true }); }
      break;

    case "products":
      if (m === "GET" && !idRaw) {
        const q = url.searchParams.get("search");
        const where = q ? or(ilike(products.name, `%${q}%`), ilike(products.sku, `%${q}%`)) : undefined;
        return json(await db.select().from(products).where(where).orderBy(asc(products.name)));
      }
      allow(user, "manager");
      if (m === "POST" && !idRaw) {
        const v = productValues(body);
        const [p] = await db.insert(products).values({ ...v, stock: num(body.stock) }).returning();
        return json(p, 201);
      }
      if (m === "PUT" && id) {
        const [p] = await db.update(products).set(productValues(body)).where(eq(products.id, id)).returning();
        return json(p);
      }
      if (m === "POST" && id && action === "stock") {
        const delta = num(body.quantity);
        if (!delta) throw new HttpError(422, "Quantity is required");
        const [p] = await db.update(products).set({ stock: sql`${products.stock} + ${delta}` }).where(eq(products.id, id)).returning();
        return json(p);
      }
      if (m === "DELETE" && id) {
        const [{ c }] = await db.select({ c: count() }).from(invoiceItems).where(eq(invoiceItems.productId, id));
        if (c > 0) {
          await db.update(products).set({ isActive: false }).where(eq(products.id, id));
          return json({ ok: true, archived: true });
        }
        await db.delete(products).where(eq(products.id, id));
        return json({ ok: true });
      }
      break;

    case "customers":
      if (m === "GET" && !idRaw) {
        const q = url.searchParams.get("search");
        const where = q ? or(ilike(customers.name, `%${q}%`), ilike(customers.code, `%${q}%`), ilike(customers.phone, `%${q}%`)) : undefined;
        return json(await db.select().from(customers).where(where).orderBy(asc(customers.name)));
      }
      if (m === "GET" && id && action === "ledger") {
        const [c] = await db.select().from(customers).where(eq(customers.id, id));
        if (!c) throw new HttpError(404, "Customer not found");
        const entries = await db.select().from(ledgerEntries).where(eq(ledgerEntries.customerId, id)).orderBy(asc(ledgerEntries.id));
        return json({ customer: c, entries });
      }
      if (m === "POST" && id && action === "payment") return json(await customerPayment(id, body));
      if (m === "POST" && !idRaw) {
        const created = await db.transaction(async (tx) => {
          const opening = round2(num(body.openingBalance));
          const [c] = await tx.insert(customers).values({
            code: str(body.code) ?? (await nextCustomerCode(tx as any)),
            name: required(body.name, "Name"), type: str(body.type) ?? "retail",
            phone: str(body.phone), email: str(body.email), address: str(body.address),
            creditLimit: round2(num(body.creditLimit)), openingBalance: opening, currentBalance: 0,
          }).returning();
          if (opening !== 0) await addLedger(tx, c.id, Math.max(opening, 0), Math.max(-opening, 0), "Opening balance");
          return c;
        });
        return json(created, 201);
      }
      if (m === "PUT" && id) {
        allow(user, "manager");
        const [c] = await db.update(customers).set({
          name: required(body.name, "Name"), type: str(body.type) ?? "retail",
          phone: str(body.phone), email: str(body.email), address: str(body.address),
          creditLimit: round2(num(body.creditLimit)), isActive: body.isActive !== false,
        }).where(eq(customers.id, id)).returning();
        return json(c);
      }
      break;

    case "invoices":
      if (m === "GET" && !idRaw) {
        const from = url.searchParams.get("from");
        const to = url.searchParams.get("to");
        const q = url.searchParams.get("search");
        const conds = [
          from ? gte(invoices.invoiceDate, from) : undefined,
          to ? lte(invoices.invoiceDate, to) : undefined,
          q ? or(ilike(invoices.invoiceNumber, `%${q}%`), ilike(customers.name, `%${q}%`)) : undefined,
        ].filter(Boolean);
        return json(await db.select({
          id: invoices.id, invoiceNumber: invoices.invoiceNumber, invoiceDate: invoices.invoiceDate,
          status: invoices.status, grandTotal: invoices.grandTotal, paidTotal: invoices.paidTotal,
          balance: invoices.balance, customerId: invoices.customerId, customerName: customers.name,
        }).from(invoices).leftJoin(customers, eq(invoices.customerId, customers.id))
          .where(conds.length ? and(...(conds as any)) : undefined).orderBy(desc(invoices.id)).limit(200));
      }
      if (m === "GET" && id) {
        const [inv] = await db.select().from(invoices).where(eq(invoices.id, id));
        if (!inv) throw new HttpError(404, "Invoice not found");
        const [items, [customer]] = await Promise.all([
          db.select().from(invoiceItems).where(eq(invoiceItems.invoiceId, id)),
          inv.customerId ? db.select().from(customers).where(eq(customers.id, inv.customerId)) : Promise.resolve([null]),
        ]);
        return json({ ...inv, items, customer });
      }
      if (m === "POST" && !idRaw) return json(await createInvoice(user, body), 201);
      if (m === "POST" && id && action === "payment") return json(await invoicePayment(id, body));
      if (m === "POST" && id && action === "cancel") { allow(user, "manager"); return json(await cancelInvoice(id)); }
      break;

    case "expense-categories":
      if (m === "GET") return json(await db.select().from(expenseCategories).orderBy(asc(expenseCategories.name)));
      allow(user, "manager");
      if (m === "POST") {
        const [c] = await db.insert(expenseCategories).values({ name: required(body.name, "Name") }).onConflictDoNothing().returning();
        return json(c ?? { ok: true }, 201);
      }
      break;

    case "expenses": {
      allow(user, "manager");
      if (m === "GET") {
        const from = url.searchParams.get("from");
        const to = url.searchParams.get("to");
        const conds = [from ? gte(expenses.spentAt, from) : undefined, to ? lte(expenses.spentAt, to) : undefined].filter(Boolean);
        return json(await db.select({
          id: expenses.id, categoryId: expenses.categoryId, categoryName: expenseCategories.name,
          amount: expenses.amount, paidVia: expenses.paidVia, vendor: expenses.vendor,
          spentAt: expenses.spentAt, note: expenses.note,
        }).from(expenses).leftJoin(expenseCategories, eq(expenses.categoryId, expenseCategories.id))
          .where(conds.length ? and(...(conds as any)) : undefined).orderBy(desc(expenses.spentAt), desc(expenses.id)).limit(500));
      }
      const values = () => {
        const amount = round2(num(body.amount));
        if (amount <= 0) throw new HttpError(422, "Amount must be greater than zero");
        return {
          categoryId: body.categoryId ? num(body.categoryId) : null, amount,
          paidVia: str(body.paidVia) ?? "cash", vendor: str(body.vendor),
          spentAt: str(body.spentAt) ?? today(), note: str(body.note),
        };
      };
      if (m === "POST") { const [e] = await db.insert(expenses).values(values()).returning(); return json(e, 201); }
      if (m === "PUT" && id) { const [e] = await db.update(expenses).set(values()).where(eq(expenses.id, id)).returning(); return json(e); }
      if (m === "DELETE" && id) { await db.delete(expenses).where(eq(expenses.id, id)); return json({ ok: true }); }
      break;
    }

    case "users":
      allow(user);
      if (m === "GET") return json(await db.select().from(appUsers).orderBy(asc(appUsers.id)));
      if (m === "PUT" && id) {
        const role = str(body.role);
        if (role && !["admin", "manager", "cashier", "pending"].includes(role)) throw new HttpError(422, "Invalid role");
        if (id === user.id && role && role !== "admin") throw new HttpError(422, "You cannot remove your own admin role");
        const [u] = await db.update(appUsers).set({
          ...(role ? { role } : {}), ...(str(body.name) ? { name: str(body.name)! } : {}),
          ...(typeof body.isActive === "boolean" && id !== user.id ? { isActive: body.isActive } : {}),
        }).where(eq(appUsers.id, id)).returning();
        return json(u);
      }
      break;
  }
  throw new HttpError(404, "Not found");
}

export default async (req: Request) => {
  try {
    const user = await currentAppUser();
    if (!user) return json({ error: "Please log in" }, 401);

    const path = new URL(req.url).pathname;
    if (path === "/api/me") return json({ user, settings: await getSettings() });
    if (!user.isActive || user.role === "pending") return json({ error: "Your account is waiting for admin approval" }, 403);

    return await route(req, user);
  } catch (e: any) {
    if (e instanceof HttpError) return json({ error: e.message }, e.status);
    if (e?.code === "23505") return json({ error: "A record with this value already exists (duplicate SKU / code / name)" }, 422);
    if (e?.code === "23503") return json({ error: "This record is in use and cannot be deleted" }, 422);
    console.error(e);
    return json({ error: "Server error" }, 500);
  }
};

export const config: Config = {
  path: "/api/*",
};
