<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refills sold before their cost was set were saved with unit_cost NULL ("cost not set"),
 * so they were left out of profit. Owner's rule: a refill's whole amount is profit → cost 0.
 * This fills those NULLs with 0 for the refill products only. Dry run by default.
 */
class BackfillRefillCost extends Command
{
    protected $signature = 'refills:zero-cost {--apply : Write the change (default is a dry run)}';

    protected $description = 'Set cost 0 on already-sold 19L / cooler refill lines that have no cost';

    public function handle(): int
    {
        $ids = DB::table('products')->whereIn('sku', ['19L-REFILL', 'COOLER-REFILL'])->pluck('id');
        $rows = DB::table('invoice_items')->whereIn('product_id', $ids)->whereNull('unit_cost');
        $count = $rows->count();

        if ($count === 0) {
            $this->info('Nothing to do — every refill line already has a cost.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->info("{$count} refill line(s) have no cost. Run with --apply to set them to 0.");

            return self::SUCCESS;
        }

        $rows->update(['unit_cost' => 0]);
        $this->info("✓ {$count} refill line(s) set to cost 0 — they now count in profit.");

        return self::SUCCESS;
    }
}
