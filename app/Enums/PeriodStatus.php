<?php

namespace App\Enums;

/**
 * The lifecycle of a client's accounting period. This enum IS the dashboard
 * state machine — each value maps to a stage in the processing pipeline and
 * carries its own label, Bootstrap color, and icon for consistent rendering.
 */
enum PeriodStatus: string
{
    case NotStarted = 'not_started';
    case Downloaded = 'downloaded';
    case Extracted  = 'extracted';
    case Matched    = 'matched';
    case NeedsReview = 'needs_review';
    case Done       = 'done';

    /** Human label (Spanish — the UI language). */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted  => 'Sin iniciar',
            self::Downloaded  => 'Descargado',
            self::Extracted   => 'Extraído',
            self::Matched     => 'Conciliado',
            self::NeedsReview => 'Requiere revisión',
            self::Done        => 'Completado',
        };
    }

    /** Bootstrap contextual color used for badges. */
    public function color(): string
    {
        return match ($this) {
            self::NotStarted  => 'secondary',
            self::Downloaded  => 'info',
            self::Extracted   => 'primary',
            self::Matched     => 'warning',
            self::NeedsReview => 'danger',
            self::Done        => 'success',
        };
    }

    /** Font Awesome icon name (without the fa- style prefix). */
    public function icon(): string
    {
        return match ($this) {
            self::NotStarted  => 'circle-dashed',
            self::Downloaded  => 'cloud-arrow-down',
            self::Extracted   => 'file-lines',
            self::Matched     => 'code-compare',
            self::NeedsReview => 'triangle-exclamation',
            self::Done        => 'circle-check',
        };
    }

    /** Ordered progression, used for progress bars and "next step" hints. */
    public function step(): int
    {
        return match ($this) {
            self::NotStarted  => 0,
            self::Downloaded  => 1,
            self::Extracted   => 2,
            self::Matched     => 3,
            self::NeedsReview => 3, // same stage as matched, needs attention
            self::Done        => 4,
        };
    }

    public static function total_steps(): int
    {
        return 4;
    }

    /** All cases as value => label, handy for filters and selects. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
