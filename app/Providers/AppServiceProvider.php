<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\VirtualUserProvider;
use App\Sms\InforiusClient;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\Entry;
use Filament\Support\Components\Component;
use Filament\Support\Concerns\Configurable;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InforiusClient::class, fn (): InforiusClient => InforiusClient::fromConfig());
    }

    public function boot(): void
    {
        Auth::provider('virtual-user', function ($app, array $config) {
            return new VirtualUserProvider;
        });

        $this->configureTable();
        $this->translatableComponents();

        Password::defaults(fn (): Password => Password::min(12)->letters()->mixedCase()->numbers());
    }

    private function translatableComponents(): void
    {
        foreach ([Field::class, BaseFilter::class, Placeholder::class, Column::class, Entry::class] as $component) {
            /* @var Configurable $component */
            $component::configureUsing(function (Component $translatable): void {
                /** @phpstan-ignore method.notFound */
                $translatable->translateLabel();
            });
        }
    }

    private function configureTable(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->striped()
                ->deferLoading();
        });
    }
}
