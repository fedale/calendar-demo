<?php

namespace App\Calendar\Definition;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

#[AsCalendar('shifts')]
final class ShiftCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Work shifts';
    }

    public function viewConfig(): array
    {
        return [
            // Night shifts (22:00-06:00) stay on the day they start.
            'display' => ['weekNumbers' => true, 'nextDayThreshold' => '09:00'],
            'integration' => ['sources' => ['shifts']],
        ];
    }
}
