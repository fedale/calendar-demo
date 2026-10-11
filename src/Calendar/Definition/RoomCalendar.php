<?php

namespace App\Calendar\Definition;

use Fedale\CalendarBundle\Attribute\AsCalendar;
use Fedale\CalendarBundle\Definition\AbstractCalendar;

/**
 * The resource timeline: rooms down the side, time across, grouped by
 * building, with a custom "Add event" button handled in assets/app.js.
 */
#[AsCalendar('rooms')]
class RoomCalendar extends AbstractCalendar
{
    public function label(): string
    {
        return 'Resource timeline';
    }

    public function viewConfig(): array
    {
        return [
            'display' => [
                'views' => [
                    'enabled' => ['timelineDay', 'timelineWeek', 'timelineMonth'],
                    'default' => 'timelineDay',
                ],
                'toolbar' => ['start' => 'add prev,today,next', 'center' => 'title', 'end' => 'views'],
                'buttons' => ['add' => ['label' => 'Add event', 'event' => 'demo:add-event']],
                'resources' => [
                    'label' => 'Rooms',
                    'columns' => [
                        ['field' => 'building', 'label' => 'Building'],
                        ['field' => 'title', 'label' => 'Room'],
                        ['field' => 'occupancy', 'label' => 'Occupancy'],
                    ],
                    'groupBy' => 'building',
                    'width' => '26rem',
                ],
            ],
            'integration' => ['sources' => ['rooms']],
        ];
    }
}
