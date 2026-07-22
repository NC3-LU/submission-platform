<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Service class for handling dashboard statistics calculations and caching.
 */
class DashboardStatisticsService
{
    /**
     * Cache duration in seconds (1 hour)
     */
    private const CACHE_DURATION = 3600;

    /**
     * Get user-related statistics.
     */
    public function getUserStatistics(): array
    {
        return Cache::remember('dashboard.user_stats', self::CACHE_DURATION, function () {
            return [
                'total' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'internal_evaluators' => User::where('role', 'internal_evaluator')->count(),
                'external_evaluators' => User::where('role', 'external_evaluator')->count(),
                'regular_users' => User::where('role', 'user')->count(),
                'with_2fa' => User::whereNotNull('two_factor_confirmed_at')->count(),
                'unconfirmed_email' => User::whereNull('email_verified_at')->count(),
            ];
        });
    }

    /**
     * Get form-related statistics.
     */
    public function getFormStatistics(): array
    {
        return Cache::remember('dashboard.form_stats', self::CACHE_DURATION, function () {
            return [
                'total' => Form::count(),
                'draft' => Form::where('status', 'draft')->count(),
                'published' => Form::where('status', 'published')->count(),
                'archived' => Form::where('status', 'archived')->count(),
            ];
        });
    }

    /**
     * Get submission-related statistics.
     */
    public function getSubmissionStatistics(): array
    {
        return Cache::remember('dashboard.submission_stats', self::CACHE_DURATION, function () {
            return [
                'total' => Submission::count(),
                'draft' => Submission::whereIn('status', ['draft', 'ongoing'])->count(),
                'submitted' => Submission::where('status', 'submitted')->count(),
                'under_review' => Submission::where('status', 'under_review')->count(),
                'completed' => Submission::where('status', 'completed')->count(),
            ];
        });
    }

    /**
     * Get the count of draft submissions for a specific form.
     */
    public function getFormDraftCount(Form $form): int
    {
        return Cache::remember("dashboard.form.{$form->id}.draft_count", self::CACHE_DURATION, function () use ($form) {
            return $form->submissions()
                ->whereIn('status', ['draft', 'ongoing'])
                ->count();
        });
    }

    /**
     * Get the count of submitted submissions for a specific form.
     */
    public function getFormSubmittedCount(Form $form): int
    {
        return Cache::remember("dashboard.form.{$form->id}.submitted_count", self::CACHE_DURATION, function () use ($form) {
            return $form->submissions()
                ->where('status', 'submitted')
                ->count();
        });
    }

    /**
     * Clear all dashboard statistics cache.
     */
    public function clearCache(): void
    {
        Cache::forget('dashboard.user_stats');
        Cache::forget('dashboard.form_stats');
        Cache::forget('dashboard.submission_stats');
    }
}
