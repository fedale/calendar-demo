<?php

namespace App\Calendar\Definition;

use Fedale\CalendarBundle\Attribute\AsCalendar;

#[AsCalendar('rooms_many')]
final class ManyRoomsCalendar extends RoomCalendar
{
    public function label(): string
    {
        return 'Timeline stress test';
    }

    public function viewConfig(): array
    {
        $config = parent::viewConfig();
        $config['display']['views']['default'] = 'timelineMonth';
        $config['integration']['sources'] = ['rooms_many'];

        return $config;
    }
}
