<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TransferFormOwnership extends Command
{
    protected $signature = 'app:transfer-form-ownership {from : Departing owner email} {to : Successor email} {--dry-run : Show the forms without changing ownership}';

    protected $description = 'Transfer all forms to a verified evaluator or administrator while preserving responses';

    public function handle(): int
    {
        return DB::transaction(function () {
            $from = User::where('email', $this->argument('from'))->lockForUpdate()->first();
            $to = User::where('email', $this->argument('to'))->lockForUpdate()->first();
            if (! $from || ! $to || $from->is($to) || ! $to->hasVerifiedEmail() || ! in_array($to->role, ['admin', 'internal_evaluator', 'external_evaluator'])) {
                $this->error('Choose two distinct existing accounts; the successor must be a verified evaluator or administrator.');

                return self::FAILURE;
            }
            $forms = $from->forms()->lockForUpdate()->get();
            $this->table(['Form ID', 'Title'], $forms->map(fn ($form) => [$form->id, $form->title]));
            if (! $this->option('dry-run')) {
                foreach ($forms as $form) {
                    $form->update(['user_id' => $to->id]);
                }
            }
            $this->info($forms->count().($this->option('dry-run') ? ' forms would be transferred.' : ' forms transferred. Review and revoke the departing account’s API tokens and access before deleting it.'));

            return self::SUCCESS;
        });
    }
}
