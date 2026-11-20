<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

/**
 * One-shot backfill for accounts.sort_key (added 2026_11_19). New writes get it
 * from Account's saving hook; this fills the rows that predate the column.
 */
class BackfillAccountSortKey extends Command
{
    protected $signature = 'accounts:backfill-sort-key';

    protected $description = 'Populate accounts.sort_key (natural-sort key) for existing rows.';

    public function handle(): int
    {
        $count = 0;

        Account::query()->orderBy('id')->chunkById(500, function ($rows) use (&$count) {
            foreach ($rows as $account) {
                $account->sort_key = Account::sortKeyFor($account->numero_cuenta);
                $account->saveQuietly(); // value already set; skip events
                $count++;
            }
        });

        $this->info("sort_key backfilled for {$count} account(s).");

        return self::SUCCESS;
    }
}
