<?php

namespace App\Filament\Resources\ApiTokenResource\Pages;

use App\Filament\Resources\ApiTokenResource;
use App\Services\ApiTokenService;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateApiToken extends CreateRecord
{
    protected static string $resource = ApiTokenResource::class;

    // Store the plain text token temporarily
    public $plainTextToken;

    protected function handleRecordCreation(array $data): Model
    {
        $issuedToken = app(ApiTokenService::class)->issue(
            (int) $data['user_id'],
            [
                'name' => $data['name'],
                'abilities' => $data['abilities'] ?? ['forms:read'],
                'allowed_ips' => $data['allowed_ips'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ],
            actorUser: auth()->user(),
            ipAddress: request()->ip(),
        );
        $this->plainTextToken = $issuedToken->plainTextToken;

        return $issuedToken->token;
    }

    protected function afterCreate(): void
    {
        // Show a notification with the token
        Notification::make()
            ->title('API Token Created Successfully')
            ->body('**COPY THIS TOKEN NOW - it will not be shown again!**<br><code style="user-select: all;">'.$this->plainTextToken.'</code>')
            ->success()
            ->persistent()
            ->actions([
                Action::make('copy')
                    ->label('Copy Token')
                    ->icon('heroicon-m-clipboard')
                    ->extraAttributes([
                        'x-on:click' => 'navigator.clipboard.writeText(\''.$this->plainTextToken.'\')',
                    ])
                    ->close(),
            ])
            ->send();

        // Also attach it to the record for display on the form
        $this->record->plain_text_token = $this->plainTextToken;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
