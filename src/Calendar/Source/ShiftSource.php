<?php

namespace App\Calendar\Source;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventLayer;
use Fedale\CalendarBundle\Model\EventStyle;

/**
 * Work shifts of a small team: morning, afternoon and night (which crosses
 * midnight), one or two people each. Public holidays are background bands.
 */
#[AsCalendarSource('shifts')]
final class ShiftSource implements EventSourceInterface
{
    private const EMPLOYEES = ['Alice Bianchi', 'Marco Rossi', 'Giulia Verdi', 'Luca Neri', 'Sara Gallo', 'Paolo Costa', 'Elena Fontana', 'Davide Ricci'];

    private const SHIFTS = [
        'morning' => ['06:00', '14:00', '#2563eb'],
        'afternoon' => ['14:00', '22:00', '#d97706'],
        'night' => ['22:00', '06:00', '#7c3aed'],
    ];

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach (DemoDays::of($query) as $day) {
            $date = $day->format('Y-m-d');
            if (DemoDays::isHoliday($day)) {
                yield new CalendarEvent("holiday-$date", 'Public holiday', $day, $day->modify('+1 day'), allDay: true, layer: EventLayer::Background, style: new EventStyle(color: '#dc2626'));
                continue;
            }

            $random = DemoDays::random('shifts', $day);
            $sunday = $day->format('N') === '7';
            foreach (self::SHIFTS as $type => [$from, $to, $color]) {
                if ($sunday && $type !== 'morning') {
                    continue;
                }
                $start = DemoDays::at($day, $from);
                $end = DemoDays::at($from < $to ? $day : $day->modify('+1 day'), $to);
                foreach ($random->pickArrayKeys(self::EMPLOYEES, $random->getInt(1, 2)) as $employee) {
                    yield new CalendarEvent(
                        id: "$date-$type-$employee",
                        title: self::EMPLOYEES[$employee],
                        start: $start,
                        end: $end,
                        type: $type,
                        style: new EventStyle(color: $color),
                    );
                }
            }
        }
    }
}
