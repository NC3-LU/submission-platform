<?php

namespace App\Filament\Pages;

use App\Models\ApiSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ApiSecuritySettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'API Management';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.api-security-settings';

    protected static ?string $navigationLabel = 'Security Settings';

    protected static ?string $title = 'API Security Settings';

    protected static ?string $slug = 'api-security-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        // Only allow access to admin users
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->getSettingsData());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rate Limiting')
                    ->description('Configure API rate limits to prevent abuse and ensure fair usage.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('rate_limit_api_authenticated')
                                    ->label('Authenticated Rate Limit')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(10000)
                                    ->suffix('req/min')
                                    ->helperText('Requests per minute for authenticated API tokens'),

                                Forms\Components\TextInput::make('rate_limit_api_unauthenticated')
                                    ->label('Unauthenticated Rate Limit')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(1000)
                                    ->suffix('req/min')
                                    ->helperText('Requests per minute for unauthenticated requests (by IP)'),

                                Forms\Components\TextInput::make('rate_limit_auth_attempts')
                                    ->label('Authentication Attempts Limit')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->suffix('req/min')
                                    ->helperText('Maximum failed authentication attempts per minute per IP'),
                            ]),
                    ]),

                Forms\Components\Section::make('Submissions Rate Limiting')
                    ->description('Specific rate limits for the submissions endpoint.')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('rate_limit_submissions_read')
                                    ->label('Read Operations')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(10000)
                                    ->suffix('req/min')
                                    ->helperText('GET requests per minute'),

                                Forms\Components\TextInput::make('rate_limit_submissions_write')
                                    ->label('Write Operations')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(1000)
                                    ->suffix('req/min')
                                    ->helperText('POST/PUT/PATCH requests per minute'),

                                Forms\Components\TextInput::make('rate_limit_submissions_daily')
                                    ->label('Daily Limit')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(100000)
                                    ->suffix('req/day')
                                    ->helperText('Maximum submissions per day per token'),
                            ]),
                    ]),

                Forms\Components\Section::make('Access Control')
                    ->description('Configure access controls for API documentation and CORS.')
                    ->schema([
                        Forms\Components\Textarea::make('api_docs_allowed_domains')
                            ->label('API Docs Allowed Domains')
                            ->required()
                            ->rows(3)
                            ->helperText('Comma-separated list of email domains allowed to access API documentation (e.g., example.com,example.org)')
                            ->rules(['regex:/^[a-zA-Z0-9\-.,\s]+$/']),

                        Forms\Components\Textarea::make('cors_allowed_origins')
                            ->label('CORS Allowed Origins')
                            ->required()
                            ->rows(3)
                            ->helperText('Comma-separated list of origins allowed for CORS requests (e.g., http://localhost,https://example.com)')
                            ->rules(['regex:/^[a-zA-Z0-9\-:\/.,\s]+$/']),
                    ]),

                Forms\Components\Section::make('Token Configuration')
                    ->description('Configure Sanctum token behavior.')
                    ->schema([
                        Forms\Components\TextInput::make('sanctum_token_prefix')
                            ->label('Sanctum Token Prefix')
                            ->maxLength(20)
                            ->helperText('Optional prefix for new Sanctum tokens (helps with GitHub secret scanning)')
                            ->alphaDash()
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Logging & Monitoring')
                    ->description('Configure API logging and monitoring features.')
                    ->schema([
                        Forms\Components\Toggle::make('api_logging_enabled')
                            ->label('Enable API Request Logging')
                            ->helperText('Log all API requests for audit and debugging purposes')
                            ->inline(false),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getSettingsData(): array
    {
        $settings = ApiSetting::all()->keyBy('key');
        $data = [];

        foreach ($settings as $key => $setting) {
            // Convert toggle values to boolean
            if ($setting->type === 'toggle') {
                $data[$key] = (bool) $setting->value;
            } else {
                $data[$key] = $setting->value;
            }
        }

        return $data;
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            DB::transaction(function () use ($data) {
                foreach ($data as $key => $value) {
                    $setting = ApiSetting::find($key);

                    if ($setting) {
                        // Convert boolean to string for toggle fields
                        if ($setting->type === 'toggle') {
                            $value = $value ? '1' : '0';
                        }

                        $setting->value = $value;
                        $setting->save();
                    }
                }
            });

            Notification::make()
                ->title('Settings saved successfully')
                ->success()
                ->body('API security settings have been updated and will take effect immediately.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving settings')
                ->danger()
                ->body('Failed to save settings: '.$e->getMessage())
                ->send();
        }
    }
}
