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
                'views' => ['enabled' => ['listWeek', 'month'], 'default' => 'listWeek'],
            ],
            'integration' => ['sources' => ['lessons']],
        ];
    }
}
