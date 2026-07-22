<?php

namespace App\Filament\Resources\ApiTokenResource\Pages;

use App\Filament\Resources\ApiTokenResource;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateApiToken extends CreateRecord
{
    protected static string $resource = ApiTokenResource::class;

    // Store the plain text token temporarily
    public $plainTextToken;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate a secure token
        $this->plainTextToken = Str::random(40);

        // Store hashed token in the database
        $data['token'] = hash('sha256', $this->plainTextToken);

        // User ID is now selected in the form, not automatically assigned

        // Store default abilities if none provided
        if (! isset($data['abilities'])) {
            $data['abilities'] = ['forms:read'];
        }

        return $data;
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
