<?php

namespace App\Calendar\Definition;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

/**
 * People down the side, grouped by team: an appointment with three
 * participants is one event on three rows.
 */
#[AsCalendar('team')]
final class TeamCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Team agenda';
    }

    public function viewConfig(): array
    {
        return [
            'display' => [
                'views' => [
                    'enabled' => ['timelineDay', 'timelineWeek', 'resourceTimeGridDay'],
                    'default' => 'timelineDay',
                    'variants' => [
                        'timelineDay' => ['dayStart' => '07:00', 'dayEnd' => '20:00'],
                        'resourceTimeGridDay' => ['label' => 'Columns', 'dayStart' => '07:00', 'dayEnd' => '20:00'],
                    ],
                ],
                'businessHours' => [['days' => [1, 2, 3, 4, 5], 'start' => '09:00', 'end' => '18:00']],
                'resources' => [
                    'label' => 'People',
                    'columns' => [['field' => 'title', 'label' => 'Name']],
                    'groupBy' => 'team',
                    'width' => '14rem',
                ],
            ],
            'behavior' => ['selectable' => true],
            'integration' => ['sources' => ['team']],
        ];
    }
}
