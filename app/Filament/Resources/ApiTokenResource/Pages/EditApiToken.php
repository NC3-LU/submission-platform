<?php

namespace App\Filament\Resources\ApiTokenResource\Pages;

use App\Filament\Resources\ApiTokenResource;
use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditApiToken extends EditRecord
{
    protected static string $resource = ApiTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(function (ApiToken $record): bool {
                    app(ApiTokenService::class)->revoke(
                        $record,
                        actorUser: auth()->user(),
                        ipAddress: request()->ip(),
                    );

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ApiTokenService::class)->update(
            $record,
            $data,
            actorUser: auth()->user(),
            ipAddress: request()->ip(),
        );
    }
}
