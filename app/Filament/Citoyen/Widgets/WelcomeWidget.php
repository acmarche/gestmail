<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Widgets;

use Filament\Widgets\Widget;

final class WelcomeWidget extends Widget
{
    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.citoyen.widgets.welcome';
}
