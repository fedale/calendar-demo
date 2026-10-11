<?php

namespace App\Calendar\Source;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;

/**
 * {@see RoomSource} over every building: the 54 rooms of the load test.
 */
#[AsCalendarSource('rooms_many')]
final class ManyRoomsSource extends RoomSource
{
    protected function featured(): ?bool
    {
        return null;
    }
}
