<?php

namespace App\Filament\Pages\Analytics\Concerns;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Agent 04: the shared date-range filter for the analytics pages — quick
 * presets plus custom from/to dates, clamped to sane bounds.
 */
trait HasDateRange
{
    public string $from = '';

    public string $to = '';

    public string $preset = '30d';

    public function mountHasDateRange(): void
    {
        $this->applyPreset($this->preset);
    }

    /**
     * @return array<string, string>
     */
    public function presets(): array
    {
        return ['7d' => '7 days', '30d' => '30 days', '90d' => '90 days', '12m' => '12 months'];
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = array_key_exists($preset, $this->presets()) ? $preset : '30d';
        $this->to = now()->toDateString();
        $this->from = match ($this->preset) {
            '7d' => now()->subDays(6)->toDateString(),
            '90d' => now()->subDays(89)->toDateString(),
            '12m' => now()->subMonths(12)->addDay()->toDateString(),
            default => now()->subDays(29)->toDateString(),
        };
    }

    public function updatedFrom(): void
    {
        $this->preset = 'custom';
    }

    public function updatedTo(): void
    {
        $this->preset = 'custom';
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        try {
            $from = Carbon::parse($this->from ?: now()->subDays(29)->toDateString())->startOfDay();
            $to = Carbon::parse($this->to ?: now()->toDateString())->endOfDay();
        } catch (Throwable) {
            $from = now()->subDays(29)->startOfDay();
            $to = now()->endOfDay();
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        // At most two years per view keeps every query bounded.
        if ($from->lessThan($to->copy()->subYears(2))) {
            $from = $to->copy()->subYears(2)->startOfDay();
        }

        return [$from, $to];
    }

    public function rangeLabel(): string
    {
        [$from, $to] = $this->range();

        return $from->format('M j, Y').' – '.$to->format('M j, Y');
    }
}
