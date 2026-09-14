<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiTokenResource\Pages;
use App\Models\ApiSetting;
use App\Models\ApiToken;
use App\Rules\ValidIpList;
use App\Services\ApiTokenService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApiTokenResource extends Resource
{
    protected static ?string $model = ApiToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'API Management';

    protected static ?int $navigationSort = 90;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Token Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->label('Token Owner')
                            ->helperText('User who will own this token and access its data'),

                        Forms\Components\TextInput::make('allowed_ips')
                            ->placeholder('192.168.1.1, 10.0.0.1')
                            ->helperText('Comma-separated IPv4/IPv6 addresses or CIDR ranges. Leave empty to allow all')
                            ->rules([new ValidIpList]),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->minDate(now())
                            ->maxDate(function () {
                                $days = (int) ApiSetting::get('api_token_max_lifetime_days', 0);

                                return $days > 0 ? now()->addDays($days) : null;
                            })
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('abilities')
                            ->options([
                                'forms:read' => 'View Forms',
                                'forms:create' => 'Create Forms',
                                'forms:update' => 'Update Forms',
                                'forms:delete' => 'Delete Forms',
                                'submissions:read' => 'View Submissions',
                                'submissions:create' => 'Create Submissions',
                                'submissions:update' => 'Update Submissions',
                                'submissions:delete' => 'Delete Submissions',
                                'tokens:manage' => 'Manage API Tokens',
                                '*' => 'All Permissions',
                            ])
                            ->required()
                            ->default(['forms:read'])
                            ->columns(2),
                    ]),

                Forms\Components\Section::make('Plain Text Token')
                    ->schema([
                        Forms\Components\Placeholder::make('token_display')
                            ->label('Token')
                            ->content(fn ($record) => $record?->plain_text_token ?? 'Token is only displayed once after creation.'),
                    ])
                    ->visible(fn ($record) => $record && isset($record->plain_text_token)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('allowed_ips')
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                    ])
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        if ($data['value'] === 'active') {
                            return $query->where(function ($query) {
                                $query->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                        }

                        if ($data['value'] === 'expired') {
                            return $query->where('expires_at', '<=', now());
                        }

                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Filter by Owner'),
            ])
            ->actions([
                Tables\Actions\Action::make('rotate')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->action(function (ApiToken $record): void {
                        $rotated = app(ApiTokenService::class)->rotate(
                            $record,
                            actorUser: auth()->user(),
                            ipAddress: request()->ip(),
                        );

                        Notification::make()
                            ->title('API token rotated')
                            ->body('Copy this token now; it will not be shown again:<br><code style="user-select: all;">'.$rotated->plainTextToken.'</code>')
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->using(function (ApiToken $record): bool {
                        app(ApiTokenService::class)->revoke(
                            $record,
                            actorUser: auth()->user(),
                            ipAddress: request()->ip(),
                        );

                        return true;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->using(function ($records): void {
                            $records->each(fn (ApiToken $record) => app(ApiTokenService::class)->revoke(
                                $record,
                                actorUser: auth()->user(),
                                ipAddress: request()->ip(),
                            ));
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiTokens::route('/'),
            'create' => Pages\CreateApiToken::route('/create'),
            'edit' => Pages\EditApiToken::route('/{record}/edit'),
        ];
    }
}
