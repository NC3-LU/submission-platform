<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkflowController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display workflow management interface for a form
     *
     * @throws AuthorizationException
     */
    public function manage(Form $form): View
    {
        $this->authorize('update', $form);

        return view('workflows.manage', [
            'form' => $form,
            'workflow' => $form->workflow()->with(['steps.assignments.user'])->first(),
        ]);
    }

    /**
     * Display workflow details for a specific form
     *
     * @throws AuthorizationException
     */
    public function show(Form $form, Workflow $workflow): View
    {
        $this->authorize('view', $form);

        $workflow->load(['steps.assignments.user', 'steps.notifications']);

        return view('workflows.show', [
            'form' => $form,
            'workflow' => $workflow,
        ]);
    }

    /**
     * Delete a workflow step
     *
     * @throws AuthorizationException
     */
    public function destroyStep(Form $form, WorkflowStep $step): RedirectResponse
    {
        $this->authorize('update', $form);

        try {
            DB::transaction(function () use ($step) {
                // Delete assignments
                $step->assignments()->delete();

                // Delete notifications
                $step->notifications()->delete();

                // Delete the step
                $step->delete();

                // Reorder remaining steps
                $workflow = $step->workflow;
                $workflow->steps()
                    ->where('order', '>', $step->order)
                    ->decrement('order');
            });

            return redirect()->route('workflows.manage', $form)
                ->with('success', 'Step deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete step: '.$e->getMessage()]);
        }
    }

    /**
     * Delete an entire workflow
     *
     * @throws AuthorizationException
     */
    public function destroy(Form $form, Workflow $workflow): RedirectResponse
    {
        $this->authorize('update', $form);

        try {
            DB::transaction(function () use ($workflow) {
                // Delete all steps and related data
                foreach ($workflow->steps as $step) {
                    $step->assignments()->delete();
                    $step->notifications()->delete();
                }
                $workflow->steps()->delete();
                $workflow->delete();
            });

            return redirect()->route('forms.edit', $form)
                ->with('success', 'Workflow deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete workflow: '.$e->getMessage()]);
        }
    }
}
