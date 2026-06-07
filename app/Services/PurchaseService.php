<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Enums\StockMovementType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseService extends BaseService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly LedgerService $ledger,
    ) {}

    public function list(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return PurchaseOrder::with('supplier')
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * Create a purchase order with line items. Totals are computed server-side.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): PurchaseOrder
    {
        return $this->transaction(function () use ($data): PurchaseOrder {
            $items = $data['items'];
            $subtotal = 0.0;
            foreach ($items as &$line) {
                $line['line_total'] = round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
                $subtotal += $line['line_total'];
            }
            unset($line);

            $discount = (float) ($data['discount'] ?? 0);
            $tax = (float) ($data['tax_total'] ?? 0);

            $po = PurchaseOrder::create([
                'po_number' => 'PO-'.now()->format('ymd').'-'.str_pad((string) (PurchaseOrder::max('id') + 1), 4, '0', STR_PAD_LEFT),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => 'draft',
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_total' => $tax,
                'grand_total' => round($subtotal - $discount + $tax, 2),
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $line) {
                $po->items()->create($line);
            }

            return $po->load('items');
        });
    }

    /** Receive goods: add stock and post the payable to the supplier ledger. */
    public function receive(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status === 'received') {
            return $po;
        }

        return $this->transaction(function () use ($po): PurchaseOrder {
            foreach ($po->items as $item) {
                $this->inventory->move([
                    'warehouse_id' => $po->warehouse_id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'type' => StockMovementType::Purchase,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'reference' => $po,
                    'note' => "GRN {$po->po_number}",
                ]);
            }

            $supplier = Supplier::find($po->supplier_id);
            $this->ledger->post($supplier, (float) $po->grand_total, 0, $po, "Purchase {$po->po_number}");

            $po->update(['status' => 'received', 'received_at' => now()]);

            return $po;
        });
    }

    public function payInvoice(Supplier $supplier, float $amount, ?string $note = null): void
    {
        $this->ledger->post($supplier, 0, $amount, null, $note ?? 'Payment made');
    }
}
