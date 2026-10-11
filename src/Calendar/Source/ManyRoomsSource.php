<?php

namespace App\Calendar\Source;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Model\CalendarResource;

/**
 * Load-test variant of {@see RoomSource}: 50 rooms in five buildings.
 */
#[AsCalendarSource('rooms_many')]
final class ManyRoomsSource extends RoomSource
{
    protected function rooms(): array
    {
        $rooms = [];
        foreach (['Atrium', 'Harbor', 'Lakeside', 'Meridian', 'Summit'] as $b => $building) {
            for ($i = 1; $i <= 10; ++$i) {
                $rooms[] = $this->roomResource(sprintf('room-%d-%02d', $b, $i), sprintf('%s %02d', $building, $i), $building, 10 * ($i % 4 + 1));
            }
        }

        return $rooms;
    }
}
