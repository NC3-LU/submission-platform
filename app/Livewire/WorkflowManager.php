<?php

namespace App\Livewire;

use App\Models\Form;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class WorkflowManager extends Component
{
    public Form $form;

    public ?Workflow $workflow = null;

    // Form state
    public string $workflowName = '';

    public ?string $workflowDescription = null;

    // Step management
    public array $steps = [];

    public array $newStep = [
        'name' => '',
        'description' => '',
        'type' => '',
        'assignees' => [],
    ];

    // UI state
    public bool $showNewStepModal = false;

    public bool $editingStep = false;

    public ?int $editingStepId = null;

    protected $listeners = ['stepOrderUpdated'];

    public function mount(Form $form): void
    {
        $this->form = $form;
        $this->workflow = $form->workflow;

        if ($this->workflow) {
            $this->workflowName = $this->workflow->name;
            $this->workflowDescription = $this->workflow->description;
            $this->loadSteps();
        }
    }

    public function loadSteps(): void
    {
        if (! $this->workflow) {
            return;
        }

        $this->steps = $this->workflow->steps()
            ->with(['assignments.user'])
            ->orderBy('order')
            ->get()
            ->toArray();
    }

    public function createWorkflow(): void
    {
        $this->validate([
            'workflowName' => 'required|string|max:100',
            'workflowDescription' => 'nullable|string',
        ]);

        DB::transaction(function () {
            $this->workflow = $this->form->workflows()->create([
                'name' => $this->workflowName,
                'description' => $this->workflowDescription,
                'status' => 'draft',
            ]);
        });

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Workflow created successfully.',
        ]);
    }

    public function showAddStep(): void
    {
        $this->resetNewStep();
        $this->showNewStepModal = true;
    }

    public function addStep(): void
    {
        $this->validate([
            'newStep.name' => 'required|string|max:100',
            'newStep.description' => 'nullable|string',
            'newStep.type' => 'required|in:review,approval,notification,automated',
            'newStep.assignees' => 'array',
        ]);

        DB::transaction(function () {
            $step = $this->workflow->steps()->create([
                'name' => $this->newStep['name'],
                'description' => $this->newStep['description'],
                'type' => $this->newStep['type'],
                'is_active' => true,
                'order' => count($this->steps) + 1,
            ]);

            foreach ($this->newStep['assignees'] as $assignee) {
                $step->assignments()->create([
                    'user_id' => $assignee['user_id'],
                    'role' => $assignee['role'],
                    'is_active' => true,
                ]);
            }
        });

        $this->showNewStepModal = false;
        $this->loadSteps();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Step added successfully.',
        ]);
    }

    public function editStep($stepId): void
    {
        $this->editingStepId = $stepId;
        $step = collect($this->steps)->firstWhere('id', $stepId);
        if ($step) {
            $this->editingStep = true;
            $this->newStep = [
                'name' => $step['name'],
                'description' => $step['description'],
                'type' => $step['type'],
                'assignees' => collect($step['assignments'])->map(function ($assignment) {
                    return [
                        'user_id' => $assignment['user_id'],
                        'role' => $assignment['role'],
                    ];
                })->toArray(),
            ];
        }
    }

    public function updateStep(): void
    {
        $this->validate([
            'newStep.name' => 'required|string|max:100',
            'newStep.description' => 'nullable|string',
            'newStep.type' => 'required|in:review,approval,notification,automated',
            'newStep.assignees' => 'array',
        ]);

        DB::transaction(function () {
            $step = WorkflowStep::find($this->editingStepId);
            $step->update([
                'name' => $this->newStep['name'],
                'description' => $this->newStep['description'],
                'type' => $this->newStep['type'],
            ]);

            // Update assignees
            $step->assignments()->delete();
            foreach ($this->newStep['assignees'] as $assignee) {
                $step->assignments()->create([
                    'user_id' => $assignee['user_id'],
                    'role' => $assignee['role'],
                    'is_active' => true,
                ]);
            }
        });

        $this->editingStep = false;
        $this->editingStepId = null;
        $this->loadSteps();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Step updated successfully.',
        ]);
    }

    public function stepOrderUpdated($orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $order => $id) {
                WorkflowStep::where('id', $id)->update(['order' => $order + 1]);
            }
        });

        $this->loadSteps();
    }

    private function resetNewStep(): void
    {
        $this->newStep = [
            'name' => '',
            'description' => '',
            'type' => '',
            'assignees' => [],
        ];
    }

    public function getAvailableUsersProperty()
    {
        return User::whereIn('role', ['internal_evaluator', 'external_evaluator'])
            ->orderBy('name')
            ->get();
    }

    public function render(): View|Factory|Application
    {
        return view('livewire.workflow-manager');
    }
}
