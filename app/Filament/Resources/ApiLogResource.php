<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiLogResource\Pages;
use App\Models\ApiLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ApiLogResource extends Resource
{
    protected static ?string $model = ApiLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'API Management';

    protected static ?int $navigationSort = 100;

    protected static ?string $modelLabel = 'API Request Log';

    protected static ?string $pluralModelLabel = 'API Request Logs';

    // Make the resource read-only
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->isAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->schema([
                        Forms\Components\TextInput::make('method')
                            ->disabled(),
                        Forms\Components\TextInput::make('endpoint')
                            ->disabled(),
                        Forms\Components\TextInput::make('ip_address')
                            ->disabled(),
                        Forms\Components\TextInput::make('response_code')
                            ->disabled(),
                        Forms\Components\TextInput::make('execution_time')
                            ->suffix('ms')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Related User and Token')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->disabled(),
                        Forms\Components\Select::make('token_id')
                            ->relationship('token', 'name')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Request Data')
                    ->schema([
                        Forms\Components\Textarea::make('request_data')
                            ->disabled()
                            ->columnSpanFull()
                            ->rows(10),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'GET' => 'success',
                        'POST' => 'warning',
                        'PUT', 'PATCH' => 'info',
                        'DELETE' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('endpoint')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('ip_address')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('response_code')
                    ->badge()
                    ->color(fn (string $state): string => match (intval($state)) {
                        200, 201, 204 => 'success',
                        400, 401, 403, 404, 422 => 'warning',
                        500, 503 => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('execution_time')
                    ->suffix(' ms'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'PATCH' => 'PATCH',
                        'DELETE' => 'DELETE',
                    ]),
                Tables\Filters\SelectFilter::make('response_code')
                    ->options([
                        '200' => '200 (OK)',
                        '201' => '201 (Created)',
                        '400' => '400 (Bad Request)',
                        '401' => '401 (Unauthorized)',
                        '403' => '403 (Forbidden)',
                        '404' => '404 (Not Found)',
                        '422' => '422 (Validation Error)',
                        '500' => '500 (Server Error)',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query) => $query->whereDate('created_at', '>=', $data['created_from']),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query) => $query->whereDate('created_at', '<=', $data['created_until']),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->isAdmin()),
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
            'index' => Pages\ListApiLogs::route('/'),
            'view' => Pages\ViewApiLog::route('/{record}'),
        ];
    }
}
