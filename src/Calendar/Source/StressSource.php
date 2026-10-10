<?php

namespace App\Calendar\Source;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Load-test data: `app.stress.per_day` random timed events every day (about
 * 500 a month with the default), plus a multi-day event every third day.
 */
#[AsCalendarSource('stress')]
final class StressSource implements EventSourceInterface
{
    private const COLORS = ['#2563eb', '#d97706', '#7c3aed', '#059669', '#db2777', '#0891b2'];

    public function __construct(
        #[Autowire('%app.stress.per_day%')]
        private readonly int $perDay,
    ) {
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach (DemoDays::of($query) as $day) {
            $random = DemoDays::random('stress', $day);
            for ($i = 0; $i < $this->perDay; ++$i) {
                $start = $day->setTime($random->getInt(7, 19), 15 * $random->getInt(0, 3));
                yield new CalendarEvent(
                    id: $day->format('Y-m-d').'-'.$i,
                    title: 'Event '.$day->format('d').'.'.($i + 1),
                    start: $start,
                    end: $start->modify('+'.(30 * $random->getInt(1, 6)).' minutes'),
                    style: new EventStyle(color: self::COLORS[$i % \count(self::COLORS)]),
                    url: '#event-'.$day->format('Ymd').'-'.$i,
                );
            }
            if ((int) $day->format('j') % 3 === 0) {
                yield new CalendarEvent(
                    id: $day->format('Y-m-d').'-span',
                    title: 'Multi-day '.$day->format('d'),
                    start: $day,
                    end: $day->modify('+'.$random->getInt(2, 4).' days'),
                    allDay: true,
                    style: new EventStyle(color: '#475569'),
                );
            }
        }
    }
}
