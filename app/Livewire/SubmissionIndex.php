<?php

namespace App\Livewire;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class SubmissionIndex extends Component
{
    use WithPagination;

    public Form $form;

    public string $statusFilter = 'all';

    public string $search = '';

    public string $sortField = 'updated_at';

    public string $sortDirection = 'desc';

    public bool $showCompleted = false;

    protected $listeners = ['refresh' => '$refresh'];

    protected array $queryString = [
        'statusFilter' => ['except' => 'all'],
        'search' => ['except' => ''],
        'sortField' => ['except' => 'updated_at'],
        'sortDirection' => ['except' => 'desc'],
        'showCompleted' => ['except' => false],
    ];

    public function mount(Form $form): void
    {
        if (! Gate::allows('viewAny', [Submission::class, $form])) {
            abort(403);
        }
        $this->form = $form;
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function updatingSearch($value): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter($value): void
    {
        $this->resetPage();
    }

    public function getSubmissionStatusClass($status): string
    {
        return match ($status) {
            'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            'ongoing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'submitted' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'under_review' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'completed' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    public function exportAllAsJson(): void
    {
        Log::info('Exporting all submissions as JSON', [
            'form_id' => $this->form->id,
            'status_filter' => $this->statusFilter,
            'search' => $this->search,
        ]);

        $this->redirect(route('submissions.export.form.json', $this->form));
    }

    public function render(): Factory|View|Application
    {

        $query = $this->form->submissions()->with(['user', 'form', 'scanResults']);

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('id', 'like', '%'.$this->search.'%')
                    ->orWhereHas('user', function ($userQuery) {
                        $userQuery->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
            });
        }

        if (! $this->showCompleted) {
            $query->where('status', '!=', 'completed');
        }

        $submissions = $query
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.submission-index', [
            'submissions' => $submissions,
        ]);
    }
}
