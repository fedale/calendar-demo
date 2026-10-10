<?php

namespace App\Calendar\Source;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventStyle;

/**
 * A school timetable: a weekly schedule expanded by the source within the
 * requested range (the calendar knows nothing about recurrences), during the
 * school year only, plus a three-day workshop every month.
 */
#[AsCalendarSource('lessons')]
final class LessonSource implements EventSourceInterface
{
    /** ISO day => list of [subject, from, to, room, color] */
    private const TIMETABLE = [
        1 => [['Mathematics', '09:00', '11:00', 'Room 12', '#2563eb'], ['Italian', '11:15', '13:00', 'Room 12', '#db2777']],
        2 => [['English', '09:00', '11:00', 'Room 7', '#059669']],
        3 => [['Chemistry lab', '14:00', '16:30', 'Lab 2', '#d97706']],
        4 => [['Physics', '09:00', '12:00', 'Room 3', '#7c3aed']],
        5 => [['History', '10:00', '12:00', 'Room 12', '#0891b2']],
    ];

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach (DemoDays::of($query) as $day) {
            if (!$this->inSchoolYear($day) || DemoDays::isHoliday($day)) {
                continue;
            }
            foreach (self::TIMETABLE[(int) $day->format('N')] ?? [] as [$subject, $from, $to, $room, $color]) {
                yield new CalendarEvent(
                    id: $day->format('Y-m-d').'-'.$subject,
                    title: "$subject · $room",
                    start: DemoDays::at($day, $from),
                    end: DemoDays::at($day, $to),
                    type: 'lesson',
                    style: new EventStyle(color: $color),
                );
            }

            // Workshop week: from the third Wednesday of the month, three days.
            if ($day->format('Y-m-d') === $day->modify('third wednesday of this month')->format('Y-m-d')) {
                yield new CalendarEvent(
                    id: 'workshop-'.$day->format('Y-m'),
                    title: 'Science workshop',
                    start: $day,
                    end: $day->modify('+3 days'),
                    allDay: true,
                    type: 'workshop',
                    style: new EventStyle(color: '#0f766e'),
                );
            }
        }
    }

    private function inSchoolYear(\DateTimeImmutable $day): bool
    {
        $monthDay = $day->format('m-d');

        return $monthDay >= '09-15' || $monthDay <= '06-10';
    }
}
