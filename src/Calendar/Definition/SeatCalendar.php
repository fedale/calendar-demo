<?php

namespace App\Calendar\Definition;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

#[AsCalendar('seats')]
final class SeatCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Desk bookings';
    }

    public function viewConfig(): array
    {
        return [
            'display' => [
                'hiddenDays' => [6, 7],
                'month' => ['dayMaxEvents' => 4],
            ],
            'integration' => ['sources' => ['seats']],
        ];
    }
}
