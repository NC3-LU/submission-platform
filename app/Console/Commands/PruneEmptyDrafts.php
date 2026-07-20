<?php

namespace App\Console\Commands;

use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Removes draft submissions that contain no user-entered values.
 *
 * These accumulated because opening a form used to create a draft row
 * immediately; they pollute the submission list and exports.
 */
class PruneEmptyDrafts extends Command
{
    protected $signature = 'app:prune-empty-drafts
                            {--force : Actually delete. Without this the command only reports.}
                            {--days=0 : Only prune drafts untouched for at least this many days.}
                            {--untouched-only : Only prune drafts with no submission_values rows at all.}';

    protected $description = 'Delete draft submissions that have no non-empty field values';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $query = $this->emptyDraftsQuery($days);
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No empty drafts found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Form', 'User', 'Created', 'Updated'],
            (clone $query)->with(['form:id,title', 'user:id,email'])
                ->orderBy('created_at')
                ->limit(50)
                ->get()
                ->map(fn (Submission $s) => [
                    $s->id,
                    $s->form?->title ?? '—',
                    $s->user?->email ?? 'guest',
                    $s->created_at,
                    $s->updated_at,
                ])->all()
        );

        if ($count > 50) {
            $this->line('… showing first 50 of '.$count.'.');
        }

        if (! $this->option('force')) {
            $this->warn("Dry run: {$count} empty draft(s) would be deleted. Re-run with --force to apply.");

            return self::SUCCESS;
        }

        // Values are removed by the submissions table's cascade; delete in chunks
        // so a large backlog does not build one huge transaction.
        $deleted = 0;
        (clone $query)->chunkById(200, function ($drafts) use (&$deleted) {
            foreach ($drafts as $draft) {
                $draft->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} empty draft(s).");

        return self::SUCCESS;
    }

    /**
     * Drafts with no submission_values row holding a non-empty value.
     */
    protected function emptyDraftsQuery(int $days): Builder
    {
        return Submission::query()
            ->whereIn('status', ['draft', 'ongoing'])
            ->when($days > 0, fn (Builder $q) => $q->where('updated_at', '<=', now()->subDays($days)))
            // A draft that holds value rows which are *all* blank is a different
            // animal from one that was never touched: it means fields were written
            // but saved empty, which may indicate lost input rather than an
            // abandoned form. --untouched-only excludes those from pruning so they
            // can be investigated instead of silently discarded.
            ->when(
                $this->option('untouched-only'),
                fn (Builder $q) => $q->whereDoesntHave('values'),
                fn (Builder $q) => $q->whereDoesntHave('values', function (Builder $inner) {
                    $inner->whereNotNull('value')->where('value', '<>', '');
                })
            );
    }
}
