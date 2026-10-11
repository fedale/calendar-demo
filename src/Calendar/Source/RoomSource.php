<?php

namespace App\Calendar\Source;

use App\Calendar\Booking\BookingStore;
use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Contract\IntentHandlerInterface;
use Fedale\CalendarBundle\Contract\ResourceSourceInterface;
use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\DeleteEvent;
use Fedale\CalendarBundle\Intent\IntentResult;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\Rejection;
use Fedale\CalendarBundle\Intent\ResizeEvent;
use Fedale\CalendarBundle\Intent\SelectRange;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\DateRange;
use Fedale\CalendarBundle\Model\EventInteractions;
use Fedale\CalendarBundle\Model\EventStyle;

/**
 * Meeting rooms in two buildings, with the events booked in them. The same
 * class is the source of the rows (resources), of the events, and the
 * handler of the user's changes: a tiny booking domain whose one rule is
 * that a room cannot hold two bookings at the same time.
 *
 * Bookings are generated from the date; changes live in the session
 * ({@see BookingStore}).
 */
#[AsCalendarSource('rooms')]
class RoomSource implements EventSourceInterface, ResourceSourceInterface, IntentHandlerInterface
{
    private const TITLES = ['Huddle', 'Workshop', 'Check-in', 'Strategy offsite', 'Hiring loop', 'Webinar', 'Interviews', 'Product demo', 'Partner summit', 'Planning', 'Budget review', 'Town hall', 'Panel', 'Training', 'Hackathon', 'Briefing', 'Keynote rehearsal', 'Retro', 'Masterclass', 'Lunch & learn', 'Kickoff', 'Design review'];

    public function __construct(private readonly BookingStore $store)
    {
    }

    public function getResources(CalendarQuery $query): iterable
    {
        return $this->rooms();
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        $stored = $this->store->all('rooms');
        foreach ($this->generate($query) as $event) {
            if (!\array_key_exists($event->id, $stored)) {
                yield $event;
            }
        }
        foreach ($stored as $id => $booking) {
            if ($booking === null) {
                continue;
            }
            $event = $this->fromStore($id, $booking, $query->timezone);
            if ($query->range->overlaps($event->start, $event->end)) {
                yield $event;
            }
        }
    }

    public function handle(CalendarIntent $intent): IntentResult
    {
        $timezone = $intent instanceof SelectRange || $intent instanceof MoveEvent || $intent instanceof ResizeEvent
            ? $intent->start->getTimezone()
            : new \DateTimeZone(date_default_timezone_get());

        if ($intent instanceof SelectRange) {
            if ($intent->resourceId === null || $this->room($intent->resourceId) === null) {
                return IntentResult::rejected(Rejection::Validation, 'Select a time on a room\'s row.');
            }

            return $this->book('new-'.bin2hex(random_bytes(4)), 'New booking', $intent->start, $intent->end, $intent->resourceId, $intent->allDay, 0, 'Booked %room%.');
        }

        if (!$intent instanceof MoveEvent && !$intent instanceof ResizeEvent && !$intent instanceof DeleteEvent) {
            return IntentResult::rejected(Rejection::Forbidden, 'Rooms do not support this action.');
        }

        $current = $this->find($intent->eventId, $timezone);
        if ($current === null) {
            return IntentResult::rejected(Rejection::Conflict, 'This booking no longer exists.');
        }
        // The calendar checked the version too, but only against what it
        // fetched a moment ago: the domain checks against its own state.
        if ($intent->version !== $current->version) {
            return IntentResult::rejected(Rejection::Conflict, 'This booking was changed meanwhile.');
        }

        if ($intent instanceof DeleteEvent) {
            $this->store->save('rooms', $current->id, null);

            return IntentResult::accepted(sprintf('Cancelled "%s".', $current->title));
        }

        $room = $intent instanceof MoveEvent ? ($intent->toResourceId ?? $current->resourceIds[0]) : $current->resourceIds[0];
        if ($this->room($room) === null) {
            return IntentResult::rejected(Rejection::Validation, 'Drop the booking on a room.');
        }

        return $this->book($current->id, $current->title, $intent->start, $intent->end, $room, $current->allDay, (int) $current->version, $intent instanceof MoveEvent ? 'Moved "%title%" to %room%.' : 'Changed the end of "%title%".');
    }

    /**
     * @return list<CalendarResource>
     */
    protected function rooms(): array
    {
        $rooms = [];
        foreach (['460 Bryant' => range('A', 'F'), '564 Pacific' => range('G', 'L')] as $building => $letters) {
            foreach ($letters as $letter) {
                $rooms[] = $this->roomResource('aud-'.strtolower($letter), "Auditorium $letter", $building, 40);
                if ($letter === 'D') {
                    $rooms[] = $this->roomResource('aud-d1', 'Room D1', $building, 10, 'aud-d');
                    $rooms[] = $this->roomResource('aud-d2', 'Room D2', $building, 10, 'aud-d');
                }
            }
        }

        return $rooms;
    }

    protected function roomResource(string $id, string $title, string $building, int $occupancy, ?string $parentId = null): CalendarResource
    {
        return new CalendarResource($id, $title, parentId: $parentId, data: ['building' => $building, 'occupancy' => $occupancy]);
    }

    /**
     * The domain rule: no two bookings in a room at the same time.
     */
    private function book(string $id, string $title, \DateTimeImmutable $start, \DateTimeImmutable $end, string $room, bool $allDay, int $version, string $message): IntentResult
    {
        $roomTitle = $this->room($room)?->title ?? $room;
        $end = $allDay && $end <= $start ? $start->modify('+1 day') : $end;
        $range = new DateRange($start->setTime(0, 0), $end->setTime(0, 0)->modify('+1 day'));
        foreach ($this->getEvents(new CalendarQuery($range, $start->getTimezone())) as $other) {
            if ($other->id !== $id && \in_array($room, $other->resourceIds, true) && $other->start < $end && $other->end > $start) {
                return IntentResult::rejected(Rejection::Validation, '%room% is already booked for "%other%" at that time.', ['%room%' => $roomTitle, '%other%' => $other->title]);
            }
        }

        $this->store->save('rooms', $id, [
            'title' => $title,
            'start' => $start->format(\DATE_ATOM),
            'end' => $end->format(\DATE_ATOM),
            'room' => $room,
            'allDay' => $allDay,
            'version' => $version + 1,
        ]);

        return IntentResult::accepted($message, ['%title%' => $title, '%room%' => $roomTitle]);
    }

    /** The current state of a booking: stored, or generated for the day its id names. */
    private function find(string $id, \DateTimeZone $timezone): ?CalendarEvent
    {
        $stored = $this->store->all('rooms');
        if (\array_key_exists($id, $stored)) {
            return $stored[$id] !== null ? $this->fromStore($id, $stored[$id], $timezone) : null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($id, 0, 10), $timezone);
        if ($day === false) {
            return null;
        }
        foreach ($this->generate(new CalendarQuery(new DateRange($day, $day->modify('+1 day')), $timezone)) as $event) {
            if ($event->id === $id) {
                return $event;
            }
        }

        return null;
    }

    private function room(string $id): ?CalendarResource
    {
        foreach ($this->rooms() as $room) {
            if ($room->id === $id) {
                return $room;
            }
        }

        return null;
    }

    /**
     * @return iterable<CalendarEvent>
     */
    private function generate(CalendarQuery $query): iterable
    {
        foreach (DemoDays::of($query) as $day) {
            foreach ($this->rooms() as $room) {
                $random = DemoDays::random('rooms-'.$room->id, $day);
                $id = $day->format('Y-m-d').'-'.$room->id;

                if ($room->id === 'aud-d' && $day->format('N') <= 5) {
                    yield $this->event("$id-split", 'Split into D1 + D2', $day, $day->modify('+1 day'), $room->id, true, editable: false);
                    continue;
                }
                if ($room->id === 'aud-j' && (int) $day->format('j') % 2 === 0) {
                    yield $this->event("$id-shoot", 'Film shoot', $day, $day->modify('+1 day'), $room->id, true, editable: false);
                    continue;
                }
                if ($day->format('N') >= 6 && $random->getInt(0, 2) > 0) {
                    continue;
                }
                $count = $random->getInt(0, $room->parentId !== null ? 5 : 3);
                for ($i = 0; $i < $count; ++$i) {
                    $start = DemoDays::at($day, sprintf('%02d:%02d', $random->getInt(8, 17), 30 * $random->getInt(0, 1)));
                    yield $this->event("$id-$i", self::TITLES[$random->getInt(0, \count(self::TITLES) - 1)], $start, $start->modify('+'.(30 * $random->getInt(1, 8)).' minutes'), $room->id);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $booking title, start, end, room, allDay, version
     */
    private function fromStore(string $id, array $booking, \DateTimeZone $timezone): CalendarEvent
    {
        return $this->event(
            $id,
            $booking['title'],
            (new \DateTimeImmutable($booking['start']))->setTimezone($timezone),
            (new \DateTimeImmutable($booking['end']))->setTimezone($timezone),
            $booking['room'],
            $booking['allDay'],
            version: (string) $booking['version'],
        );
    }

    private function event(string $id, string $title, \DateTimeImmutable $start, \DateTimeImmutable $end, string $room, bool $allDay = false, bool $editable = true, string $version = '1'): CalendarEvent
    {
        return new CalendarEvent(
            id: $id,
            title: $title,
            start: $start,
            end: $end,
            allDay: $allDay,
            resourceIds: [$room],
            type: 'booking',
            interactions: new EventInteractions(movable: $editable, resizable: $editable, deletable: $editable),
            version: $version,
            style: new EventStyle(color: $editable ? '#5b21b6' : '#475569'),
        );
    }
}
