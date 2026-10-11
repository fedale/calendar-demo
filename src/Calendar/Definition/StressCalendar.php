<?php

namespace App\Calendar\Definition;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

#[AsCalendar('stress')]
final class StressCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Stress test';
    }

    public function viewConfig(): array
    {
        return [
            'display' => ['views' => ['enabled' => ['year', 'month', 'timeGridWeek', 'listMonth'], 'default' => 'month']],
            'integration' => ['sources' => ['stress']],
        ];
    }
}
