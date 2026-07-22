<?php

namespace App\Filament\Widgets;

use App\Models\ApiLog;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApiRequestsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // Today's API requests
        $todayCount = ApiLog::whereDate('created_at', Carbon::today())->count();

        // Last 7 days API requests
        $lastWeekCount = ApiLog::whereDate('created_at', '>=', Carbon::today()->subDays(7))
            ->count();

        // Average response time
        $avgResponseTime = ApiLog::avg('execution_time') ?? 0;

        // Error rate percentage (non-200 status codes)
        $totalRequests = ApiLog::count();
        $errorCount = ApiLog::where(function ($query) {
            $query->where('response_code', '<', 200)
                ->orWhere('response_code', '>=', 400);
        })->count();

        $errorRate = $totalRequests > 0
            ? round(($errorCount / $totalRequests) * 100, 2)
            : 0;

        return [
            Stat::make('Today\'s API Requests', $todayCount)
                ->description('API calls made today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Last 7 Days', $lastWeekCount)
                ->description('Total API calls in past week')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make('Avg Response Time', number_format($avgResponseTime, 2).' ms')
                ->description('Error rate: '.$errorRate.'%')
                ->descriptionIcon($errorRate > 5 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($errorRate > 5 ? 'danger' : 'success'),
        ];
    }
}
