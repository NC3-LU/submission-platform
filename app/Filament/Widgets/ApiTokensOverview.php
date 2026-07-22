<?php

namespace App\Filament\Widgets;

use App\Models\ApiLog;
use App\Models\ApiToken;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApiTokensOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $activeTokens = ApiToken::whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
            ->count();

        $expiredTokens = ApiToken::whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();

        $totalRequests = ApiLog::count();
        $avgResponseTime = ApiLog::avg('execution_time') ?? 0;

        return [
            Stat::make('Active API Tokens', $activeTokens)
                ->description('Tokens currently in use')
                ->descriptionIcon('heroicon-m-key')
                ->color('success'),

            Stat::make('Expired API Tokens', $expiredTokens)
                ->description('Tokens that need renewal')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger'),

            Stat::make('Total API Requests', $totalRequests)
                ->description('Avg. '.number_format($avgResponseTime, 2).'ms response time')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),
        ];
    }
}
