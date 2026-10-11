<?php

namespace App\Calendar\Source;

use App\Calendar\Booking\BookingStore;
use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Contract\IntentHandlerInterface;
use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\DeleteEvent;
use Fedale\CalendarBundle\Intent\IntentResult;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\Rejection;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Model\EventInteractions;
use Fedale\CalendarBundle\Model\EventStyle;
use Fedale\CalendarBundle\Model\EventVisibility;

/**
 * Desk bookings for whole days. The current user ("you") sees their own
 * bookings in full and may move them to another day or cancel them;
 * everybody else's are "Busy" and fixed. Both decisions belong to the
 * source, the calendar only applies them. The domain rule: one person per
 * desk per day, on working days.
 */
#[AsCalendarSource('seats')]
final class SeatSource implements EventSourceInterface, IntentHandlerInterface
{
    private const DESKS = ['Desk A1', 'Desk A2', 'Desk A3', 'Desk B1', 'Desk B2', 'Desk B3'];

    public function __construct(private readonly BookingStore $store)
    {
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        $stored = $this->store->all('seats');
        foreach (DemoDays::of($query) as $day) {
            foreach ($this->generated($day) as $event) {
                if (!\array_key_exists($event->id, $stored)) {
                    yield $event;
                }
            }
        }
        foreach ($stored as $id => $booking) {
            if ($booking !== null) {
                $event = $this->booking($id, (int) $booking['desk'], new \DateTimeImmutable($booking['day'], $query->timezone), true, (string) $booking['version']);
                if ($query->range->overlaps($event->start, $event->end)) {
                    yield $event;
                }
            }
        }
    }

    public function handle(CalendarIntent $intent): IntentResult
    {
        if (!$intent instanceof MoveEvent && !$intent instanceof DeleteEvent) {
            return IntentResult::rejected(Rejection::Forbidden, 'Desk bookings can only be moved or cancelled.');
        }
        $timezone = $intent instanceof MoveEvent ? $intent->start->getTimezone() : new \DateTimeZone(date_default_timezone_get());
        $current = $this->find($intent->eventId, $timezone);
        if ($current === null || !$current->interactions->movable) {
            return IntentResult::rejected(Rejection::Forbidden, 'You can only change your own bookings.');
        }
        if ($intent->version !== $current->version) {
            return IntentResult::rejected(Rejection::Conflict, 'This booking was changed meanwhile.');
        }
        if ($intent instanceof DeleteEvent) {
            $this->store->save('seats', $current->id, null);

            return IntentResult::accepted('Cancelled your booking of %desk%.', ['%desk%' => self::DESKS[$current->data['desk']]]);
        }

        $day = $intent->start->setTime(0, 0);
        if ($day->format('N') >= 6 || DemoDays::isHoliday($day)) {
            return IntentResult::rejected(Rejection::Validation, 'The office is closed on %day%.', ['%day%' => $day->format('l j F')]);
        }
        $desk = $current->data['desk'];
        $query = new CalendarQuery(new DateRange($day, $day->modify('+1 day')), $timezone);
        foreach ($this->getEvents($query) as $other) {
            if ($other->id !== $current->id && $other->data['desk'] === $desk && $other->start->format('Y-m-d') === $day->format('Y-m-d')) {
                return IntentResult::rejected(Rejection::Validation, '%desk% is already taken on %day%.', ['%desk%' => self::DESKS[$desk], '%day%' => $day->format('l j F')]);
            }
        }

        $this->store->save('seats', $current->id, ['desk' => $desk, 'day' => $day->format('Y-m-d'), 'version' => (int) $current->version + 1]);

        return IntentResult::accepted('Moved your booking of %desk% to %day%.', ['%desk%' => self::DESKS[$desk], '%day%' => $day->format('l j F')]);
    }

    /**
     * @return iterable<CalendarEvent>
     */
    private function generated(\DateTimeImmutable $day): iterable
    {
        if ($day->format('N') >= 6 || DemoDays::isHoliday($day)) {
            return;
        }
        $random = DemoDays::random('seats', $day);
        foreach ($random->pickArrayKeys(self::DESKS, $random->getInt(2, 5)) as $desk) {
            yield $this->booking($day->format('Y-m-d').'-'.$desk, $desk, $day, $random->getInt(1, 4) === 1);
        }
    }

    private function find(string $id, \DateTimeZone $timezone): ?CalendarEvent
    {
        $stored = $this->store->all('seats');
        if (\array_key_exists($id, $stored)) {
            $booking = $stored[$id];

            return $booking !== null ? $this->booking($id, (int) $booking['desk'], new \DateTimeImmutable($booking['day'], $timezone), true, (string) $booking['version']) : null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($id, 0, 10), $timezone);
        foreach ($day !== false ? $this->generated($day) : [] as $event) {
            if ($event->id === $id) {
                return $event;
            }
        }

        return null;
    }

    private function booking(string $id, int $desk, \DateTimeImmutable $day, bool $mine, string $version = '1'): CalendarEvent
    {
        return new CalendarEvent(
            id: $id,
            title: self::DESKS[$desk].($mine ? ' (you)' : ''),
            start: $day,
            end: $day->modify('+1 day'),
            allDay: true,
            type: 'desk',
            visibility: $mine ? EventVisibility::Full : EventVisibility::BusyOnly,
            interactions: new EventInteractions(movable: $mine, deletable: $mine),
            version: $version,
            style: new EventStyle(color: $mine ? '#16a34a' : '#64748b'),
            data: ['desk' => $desk],
        );
    }
}
