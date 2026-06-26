<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatCard extends Component
{
    public function __construct(
        public readonly string $label,
        public readonly string $value,
        public readonly string $icon,
        public readonly string $color = 'primary',
        public readonly ?float $trend = null,
        public readonly string $trendLabel = '',
        public readonly ?string $subtext = null,
    ) {}

    public function render(): View
    {
        return view('components.stat-card')->with([
            'borderColor' => match ($this->color) {
                'secondary' => 'border-secondary',
                'tertiary' => 'border-tertiary-container',
                'error' => 'border-error',
                default => 'border-primary-fixed-dim',
            },
            'iconBg' => match ($this->color) {
                'secondary' => 'bg-secondary/15',
                'tertiary' => 'bg-tertiary-container/30',
                'error' => 'bg-error-container',
                default => 'bg-primary-fixed/20',
            },
            'iconColor' => match ($this->color) {
                'secondary' => 'text-secondary',
                'tertiary' => 'text-tertiary-container',
                'error' => 'text-error',
                default => 'text-primary-fixed-dim',
            },
        ]);
    }
}
