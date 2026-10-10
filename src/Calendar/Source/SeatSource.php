<?php

namespace App\Calendar\Source;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventStyle;
use Fedale\CalendarBundle\Model\EventVisibility;

/**
 * Desk bookings for whole days. The current user ("you") sees their own
 * bookings in full and everybody else's as "Busy": that decision belongs to
 * the source, the calendar only applies it.
 */
#[AsCalendarSource('seats')]
final class SeatSource implements EventSourceInterface
{
    private const DESKS = ['Desk A1', 'Desk A2', 'Desk A3', 'Desk B1', 'Desk B2', 'Desk B3'];

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach (DemoDays::of($query) as $day) {
            if ($day->format('N') >= 6 || DemoDays::isHoliday($day)) {
                continue;
            }
            $random = DemoDays::random('seats', $day);
            foreach ($random->pickArrayKeys(self::DESKS, $random->getInt(2, 5)) as $desk) {
                $mine = $random->getInt(1, 4) === 1;
                yield new CalendarEvent(
                    id: $day->format('Y-m-d').'-'.$desk,
                    title: self::DESKS[$desk].($mine ? ' (you)' : ''),
                    start: $day,
                    end: $day->modify('+1 day'),
                    allDay: true,
                    type: 'desk',
                    visibility: $mine ? EventVisibility::Full : EventVisibility::BusyOnly,
                    style: new EventStyle(color: $mine ? '#16a34a' : '#64748b'),
                );
            }
        }
    }
}
