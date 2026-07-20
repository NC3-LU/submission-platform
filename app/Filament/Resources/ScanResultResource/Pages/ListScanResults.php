<?php

namespace App\Filament\Resources\ScanResultResource\Pages;

use App\Filament\Resources\ScanResultResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListScanResults extends ListRecords
{
    protected static string $resource = ScanResultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
    
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Scans')
                ->icon('heroicon-o-clipboard-document-list'),
            'malicious' => Tab::make('Malicious')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_malicious', true))
                ->badge(fn () => \App\Models\ScanResult::where('is_malicious', true)->count())
                ->badgeColor('danger'),
            'clean' => Tab::make('Clean')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_malicious', false))
                ->badge(fn () => \App\Models\ScanResult::where('is_malicious', false)->count())
                ->badgeColor('success'),
        ];
    }
}
