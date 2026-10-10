<?php

namespace Tests\Unit;

use App\Enums\TicketStatus;
use PHPUnit\Framework\TestCase;

/**
 * The solid status badges are 12px bold text, which WCAG AA wants at 4.5 to 1. White on the old
 * 400/500 shades measured 1.7 to 2.9 (Pending was close to unreadable).
 */
class TicketStatusBadgeContrastTest extends TestCase
{
    /** Tailwind colours as the browser computes them, for every background a status badge may use. */
    private const BACKGROUNDS = [
        'bg-blue-700' => '#1447e6',
        'bg-violet-700' => '#7008e7',
        'bg-amber-700' => '#bb4d00',
        'bg-emerald-700' => '#007a55',
        'bg-slate-600' => '#45556c',
        'bg-rose-700' => '#c60036',
    ];

    public function test_every_solid_status_badge_has_readable_white_text(): void
    {
        foreach (TicketStatus::cases() as $status) {
            $classes = explode(' ', $status->badgeClass());

            $this->assertContains('text-white', $classes, "{$status->name} badge is expected to use white text");

            $background = collect($classes)->first(fn (string $class) => str_starts_with($class, 'bg-'));
            $this->assertArrayHasKey($background, self::BACKGROUNDS, "{$status->name} uses {$background}; add its colour to BACKGROUNDS so the contrast can be checked");

            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrastWithWhite(self::BACKGROUNDS[$background]),
                "{$status->name} badge ({$background}) is too light for white text"
            );
        }
    }

    public function test_the_ratio_matches_known_values(): void
    {
        $this->assertEqualsWithDelta(21.0, $this->contrastWithWhite('#000000'), 0.01);
        $this->assertEqualsWithDelta(1.0, $this->contrastWithWhite('#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.72, $this->contrastWithWhite('#ffb900'), 0.01, 'the old Pending colour');
    }

    private function contrastWithWhite(string $hex): float
    {
        [$red, $green, $blue] = array_map(
            static function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2)
        );

        $luminance = 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;

        return 1.05 / ($luminance + 0.05);
    }
}
