<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\User;
use App\Services\DashboardStatisticsService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * DashboardController handles the display of user-specific and admin dashboard views.
 */
class DashboardController extends Controller
{
    private DashboardStatisticsService $statisticsService;

    /**
     * Create a new controller instance.
     */
    public function __construct(DashboardStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Display the dashboard view with user-specific and admin statistics.
     */
    public function index(): View|Factory|Application
    {
        $formStats = $this->getUserFormStatistics();
        $adminStats = $this->getAdminStatistics();

        return view('dashboard', compact('formStats', 'adminStats'));
    }

    /**
     * Get statistics for forms owned by or assigned to the current user.
     */
    private function getUserFormStatistics(): Collection
    {
        $forms = $this->getUserForms();

        return $forms->map(function (Form $form) {
            return [
                'form' => $form,
                'draft_count' => $this->statisticsService->getFormDraftCount($form),
                'submitted_count' => $this->statisticsService->getFormSubmittedCount($form),
            ];
        });
    }

    /**
     * Get forms owned by or assigned to the current user.
     */
    private function getUserForms(): Collection
    {
        $createdForms = Auth::user()->forms()->latest();
        $assignedForms = Form::whereHas('appointedUsers', function ($query) {
            $query->where('user_id', Auth::id());
        })->latest();

        return $createdForms->union($assignedForms)->get();
    }

    /**
     * Get admin statistics if the current user is an admin.
     */
    private function getAdminStatistics(): ?array
    {
        if (! Auth::user()->isAdmin()) {
            return null;
        }

        return [
            'users' => $this->statisticsService->getUserStatistics(),
            'forms' => $this->statisticsService->getFormStatistics(),
            'submissions' => $this->statisticsService->getSubmissionStatistics(),
        ];
    }
}
