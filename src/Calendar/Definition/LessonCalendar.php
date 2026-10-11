<?php

namespace App\Calendar\Definition;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

#[AsCalendar('lessons')]
final class LessonCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Lessons';
    }

    public function viewConfig(): array
    {
        return [
            'display' => [
                'views' => [
                    'enabled' => ['listWeek', 'timeGridWeek', 'month'],
                    'default' => 'listWeek',
                    // School hours only; the built-in variant keeps its other parameters.
                    'variants' => ['timeGridWeek' => ['dayStart' => '07:00', 'dayEnd' => '19:00']],
                ],
                'hiddenDays' => [6, 7],
                'businessHours' => [['days' => [1, 2, 3, 4, 5], 'start' => '08:00', 'end' => '14:00']],
            ],
            'integration' => ['sources' => ['lessons']],
        ];
    }
}
