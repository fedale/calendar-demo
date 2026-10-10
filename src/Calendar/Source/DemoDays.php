<?php

namespace App\Calendar\Source;

use Fedale\CalendarBundle\Model\CalendarQuery;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Helpers shared by the demo sources: they generate events day by day, with a
 * random generator seeded by the source and the date, so a given day always
 * yields the same events whatever range the calendar asks for.
 */
final class DemoDays
{
    /**
     * The days of the queried range, starting one day earlier so events that
     * begin the evening before (night shifts) and spill into the range are
     * generated too.
     *
     * @return iterable<\DateTimeImmutable>
     */
    public static function of(CalendarQuery $query): iterable
    {
        $day = $query->range->start->setTimezone($query->timezone)->setTime(0, 0)->modify('-1 day');
        while ($day < $query->range->end) {
            yield $day;
            $day = $day->modify('+1 day')->setTime(0, 0);
        }
    }

    public static function random(string $source, \DateTimeImmutable $day): Randomizer
    {
        return new Randomizer(new Xoshiro256StarStar(hash('xxh128', $source.$day->format('Y-m-d'), true).str_repeat("\0", 16)));
    }

    public static function at(\DateTimeImmutable $day, string $time): \DateTimeImmutable
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $day->setTime($hours, $minutes);
    }

    /** Fixed-date Italian public holidays (Easter Monday left out for brevity). */
    public static function isHoliday(\DateTimeImmutable $day): bool
    {
        return \in_array($day->format('m-d'), ['01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26'], true);
    }
}
