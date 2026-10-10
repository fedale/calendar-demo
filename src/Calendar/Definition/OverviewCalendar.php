<?php

namespace App\Calendar\Definition;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

/**
 * Three unrelated domains on one calendar: each keeps its own source and
 * rules, the calendar only overlays them.
 */
#[AsCalendar('overview')]
final class OverviewCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Overview';
    }

    public function viewConfig(): array
    {
        return [
            'integration' => ['sources' => ['lessons', 'seats', 'shifts']],
        ];
    }
}
