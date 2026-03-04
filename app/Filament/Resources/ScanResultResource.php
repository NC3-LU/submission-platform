<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScanResultResource\Pages;
use App\Models\ScanResult;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ScanResultResource extends Resource
{
    protected static ?string $model = ScanResult::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    
    protected static ?string $navigationGroup = 'Security';
    
    protected static ?int $navigationSort = 10;
    
    protected static ?string $modelLabel = 'File Scan Result';
    
    protected static ?string $pluralModelLabel = 'File Scan Results';

    // Read-only resource - scans are created automatically
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
                Forms\Components\Section::make('Scan Details')
                    ->schema([
                        Forms\Components\TextInput::make('filename')
                            ->label('Filename')
                            ->disabled(),
                        Forms\Components\TextInput::make('scanner_used')
                            ->label('Scanner')
                            ->disabled(),
                        Forms\Components\Toggle::make('is_malicious')
                            ->label('Malicious')
                            ->disabled(),
                        Forms\Components\TextInput::make('created_at')
                            ->label('Scanned At')
                            ->disabled(),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Related Submission')
                    ->schema([
                        Forms\Components\Select::make('submission_id')
                            ->relationship('submission', 'id')
                            ->disabled(),
                    ]),
                    
                Forms\Components\Section::make('Scan Results (Raw)')
                    ->schema([
                        Forms\Components\Textarea::make('scan_results')
                            ->disabled()
                            ->columnSpanFull()
                            ->rows(10)
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('filename')
                    ->label('Filename')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\IconColumn::make('is_malicious')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('scanner_used')
                    ->label('Scanner')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('submission.id')
                    ->label('Submission ID')
                    ->copyable()
                    ->limit(8),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Scanned At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_malicious')
                    ->label('Status')
                    ->trueLabel('Malicious')
                    ->falseLabel('Clean')
                    ->placeholder('All'),
                Tables\Filters\SelectFilter::make('scanner_used')
                    ->options([
                        'pandora' => 'Pandora',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Scanned From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Scanned Until'),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScanResults::route('/'),
            'view' => Pages\ViewScanResult::route('/{record}'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        $maliciousCount = static::getModel()::where('is_malicious', true)->count();
        return $maliciousCount > 0 ? (string) $maliciousCount : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        $maliciousCount = static::getModel()::where('is_malicious', true)->count();
        return $maliciousCount > 0 ? 'danger' : 'success';
    }
}
