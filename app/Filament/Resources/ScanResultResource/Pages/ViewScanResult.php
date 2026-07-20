<?php

namespace App\Filament\Resources\ScanResultResource\Pages;

use App\Filament\Resources\ScanResultResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewScanResult extends ViewRecord
{
    protected static string $resource = ScanResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()->isAdmin()),
        ];
    }
}
