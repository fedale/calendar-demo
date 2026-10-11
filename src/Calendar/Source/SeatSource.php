<?php

namespace App\Calendar\Source;

use App\Calendar\DemoUser;
use App\Entity\Booking;
use App\Entity\Room;
use App\Enum\RoomKind;
use App\Repository\BookingRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventFinderInterface;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Contract\IntentHandlerInterface;
use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\DeleteEvent;
use Fedale\CalendarBundle\Intent\IntentResult;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\Rejection;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\EventInteractions;
use Fedale\CalendarBundle\Model\EventStyle;
use Fedale\CalendarBundle\Model\EventVisibility;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Desk bookings (bookings of rooms of kind Desk) for whole days. You see
 * your own in full and may move or cancel them; everybody else's are "Busy"
 * and fixed: both decisions belong to this source, the calendar applies
 * them. The rules: one person per desk per day, one desk per day for you,
 * working days only.
 */
#[AsCalendarSource('seats')]
final class SeatSource implements EventSourceInterface, IntentHandlerInterface, EventFinderInterface, ResetInterface
{
    /** @var list<Room>|null */
    private ?array $desks = null;

    public function __construct(
        private readonly RoomRepository $rooms,
        private readonly BookingRepository $bookings,
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoUser $user,
    ) {
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach ($this->bookings->findInRange($query->range->start, $query->range->end, $this->desks()) as $booking) {
            yield $this->event($booking);
        }
    }

    /** For the API: one desk booking by id. */
    public function findEvent(string $id): ?CalendarEvent
    {
        $booking = $this->bookings->find((int) $id);

        return $booking !== null && \in_array($booking->getRoom(), $this->desks(), true) ? $this->event($booking) : null;
    }

    public function handle(CalendarIntent $intent): IntentResult
    {
        if (!$intent instanceof MoveEvent && !$intent instanceof DeleteEvent) {
            return IntentResult::rejected(Rejection::Forbidden, 'Desk bookings can only be moved or cancelled.');
        }
        $booking = $this->bookings->find((int) $intent->eventId);
        if ($booking === null || !\in_array($booking->getRoom(), $this->desks(), true)) {
            return IntentResult::rejected(Rejection::Conflict, 'This booking no longer exists.');
        }
        if (!$this->user->is($booking->getOrganizer())) {
            return IntentResult::rejected(Rejection::Forbidden, 'You can only change your own bookings.');
        }
        if ($intent->version !== (string) $booking->getVersion()) {
            return IntentResult::rejected(Rejection::Conflict, 'This booking was changed meanwhile.');
        }
        $desk = $booking->getRoom()?->getName();

        if ($intent instanceof DeleteEvent) {
            $this->entityManager->remove($booking);
            $this->entityManager->flush();

            return IntentResult::accepted('Cancelled your booking of %desk%.', ['%desk%' => $desk]);
        }

        $day = $intent->start->setTime(0, 0);
        $label = $day->format('l j F');
        if ($day->format('N') >= 6 || DemoDays::isHoliday($day)) {
            return IntentResult::rejected(Rejection::Validation, 'The office is closed on %day%.', ['%day%' => $label]);
        }
        $next = $day->modify('+1 day');
        foreach ($this->bookings->findInRange($day, $next, $this->desks()) as $other) {
            if ($other === $booking) {
                continue;
            }
            if ($other->getRoom() === $booking->getRoom()) {
                return IntentResult::rejected(Rejection::Validation, '%desk% is already taken on %day%.', ['%desk%' => $desk, '%day%' => $label]);
            }
            if ($this->user->is($other->getOrganizer())) {
                return IntentResult::rejected(Rejection::Validation, 'You already have %other% on %day%.', ['%other%' => $other->getRoom()?->getName(), '%day%' => $label]);
            }
        }

        $booking->reschedule($day, $next);
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            return IntentResult::rejected(Rejection::Conflict, 'This booking was changed meanwhile.');
        }

        return IntentResult::accepted('Moved your booking of %desk% to %day%.', ['%desk%' => $desk, '%day%' => $label]);
    }

    public function reset(): void
    {
        $this->desks = null;
    }

    private function event(Booking $booking): CalendarEvent
    {
        $mine = $this->user->is($booking->getOrganizer());

        return new CalendarEvent(
            id: (string) $booking->getId(),
            title: $booking->getRoom()?->getName().($mine ? ' (you)' : ' · '.$booking->getOrganizer()?->getName()),
            start: $booking->getStartsAt(),
            end: $booking->getEndsAt(),
            allDay: true,
            type: 'desk',
            visibility: $mine ? EventVisibility::Full : EventVisibility::BusyOnly,
            interactions: new EventInteractions(movable: $mine, deletable: $mine),
            version: (string) $booking->getVersion(),
            style: new EventStyle(color: $mine ? '#16a34a' : '#64748b'),
        );
    }

    /**
     * @return list<Room>
     */
    private function desks(): array
    {
        return $this->desks ??= $this->rooms->findByKinds([RoomKind::Desk]);
    }
}
