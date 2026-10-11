<?php

namespace App\Calendar\Source;

use App\Calendar\DemoUser;
use App\Entity\Appointment;
use App\Entity\Booking;
use App\Entity\Room;
use App\Enum\RoomKind;
use App\Repository\BookingRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Contract\IntentHandlerInterface;
use Fedale\CalendarBundle\Contract\ResourceSourceInterface;
use Fedale\CalendarBundle\Intent\CalendarIntent;
use Fedale\CalendarBundle\Intent\DeleteEvent;
use Fedale\CalendarBundle\Intent\EventIntent;
use Fedale\CalendarBundle\Intent\IntentResult;
use Fedale\CalendarBundle\Intent\MoveEvent;
use Fedale\CalendarBundle\Intent\Rejection;
use Fedale\CalendarBundle\Intent\ResizeEvent;
use Fedale\CalendarBundle\Intent\SelectRange;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\EventInteractions;
use Fedale\CalendarBundle\Model\EventStyle;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The rooms of the featured buildings (resources) and their bookings
 * (events), from Doctrine; also the handler of the user's changes, with the
 * booking domain's rule: a room never holds two bookings at once.
 *
 * The adapter is the only place that knows both sides: entities in,
 * CalendarEvent and CalendarResource out, intents back into entity changes.
 */
#[AsCalendarSource('rooms')]
class RoomSource implements EventSourceInterface, ResourceSourceInterface, IntentHandlerInterface, ResetInterface
{
    /** @var list<Room>|null */
    private ?array $roomList = null;

    public function __construct(
        private readonly RoomRepository $rooms,
        private readonly BookingRepository $bookings,
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoUser $user,
    ) {
    }

    public function getResources(CalendarQuery $query): iterable
    {
        foreach ($this->roomList() as $room) {
            yield new CalendarResource(
                id: (string) $room->getId(),
                title: $room->getName(),
                parentId: $room->getParent() !== null ? (string) $room->getParent()->getId() : null,
                order: $room->getPosition(),
                data: ['building' => $room->getBuilding()?->getName(), 'occupancy' => $room->getCapacity()],
            );
        }
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach ($this->bookings->findInRange($query->range->start, $query->range->end, $this->roomList()) as $booking) {
            yield $this->event($booking);
        }
    }

    public function handle(CalendarIntent $intent): IntentResult
    {
        if ($intent instanceof SelectRange) {
            return $this->create($intent);
        }
        if (!$intent instanceof MoveEvent && !$intent instanceof ResizeEvent && !$intent instanceof DeleteEvent) {
            return IntentResult::rejected(Rejection::Forbidden, 'Rooms do not support this action.');
        }

        $booking = $this->bookings->find((int) $intent->eventId);
        if ($booking === null || !\in_array($booking->getRoom(), $this->roomList(), true)) {
            return IntentResult::rejected(Rejection::Conflict, 'This booking no longer exists.');
        }
        if (!$this->isEditable($booking)) {
            return IntentResult::rejected(Rejection::Forbidden, 'This booking cannot be changed.');
        }
        if ($this->isStale($booking, $intent)) {
            return IntentResult::rejected(Rejection::Conflict, 'This booking was changed meanwhile.');
        }

        if ($intent instanceof DeleteEvent) {
            // Appointments held there lose their room, not their existence.
            $this->entityManager->createQuery('UPDATE '.Appointment::class.' a SET a.booking = NULL WHERE a.booking = :booking')
                ->setParameter('booking', $booking)
                ->execute();
            $this->entityManager->remove($booking);
            $this->entityManager->flush();

            return IntentResult::accepted('Cancelled "%title%".', ['%title%' => $booking->getTitle()]);
        }

        $room = $booking->getRoom();
        if ($intent instanceof MoveEvent && $intent->toResourceId !== null) {
            $room = $this->room($intent->toResourceId);
            if ($room === null) {
                return IntentResult::rejected(Rejection::Validation, 'Drop the booking on a room.');
            }
        }
        if (($rejection = $this->overlap($room, $intent->start, $intent->end, $booking)) !== null) {
            return $rejection;
        }

        $booking->setRoom($room)->reschedule($intent->start, $intent->end);
        // Appointments held in this booking move with it.
        foreach ($this->entityManager->getRepository(Appointment::class)->findBy(['booking' => $booking]) as $appointment) {
            $appointment->reschedule($intent->start, $intent->end);
        }

        return $this->flush(
            $intent instanceof MoveEvent ? 'Moved "%title%" to %room%.' : 'Changed the end of "%title%".',
            ['%title%' => $booking->getTitle(), '%room%' => $room->getName()],
        );
    }

    public function reset(): void
    {
        $this->roomList = null;
    }

    /** Which buildings this source covers: the featured ones. */
    protected function featured(): ?bool
    {
        return true;
    }

    /**
     * @return list<Room>
     */
    protected function roomList(): array
    {
        return $this->roomList ??= $this->rooms->findByKinds([RoomKind::Auditorium, RoomKind::MeetingRoom], $this->featured());
    }

    private function create(SelectRange $intent): IntentResult
    {
        $room = $intent->resourceId !== null ? $this->room($intent->resourceId) : null;
        $organizer = $this->user->get();
        if ($room === null || $organizer === null) {
            return IntentResult::rejected(Rejection::Validation, 'Select a time on a room\'s row.');
        }
        if (($rejection = $this->overlap($room, $intent->start, $intent->end)) !== null) {
            return $rejection;
        }

        $booking = (new Booking())->setTitle('New booking')->setRoom($room)->setOrganizer($organizer)->setAllDay($intent->allDay)->reschedule($intent->start, $intent->end);
        $this->entityManager->persist($booking);

        return $this->flush('Booked %room%.', ['%room%' => $room->getName()]);
    }

    private function overlap(Room $room, \DateTimeImmutable $start, \DateTimeImmutable $end, ?Booking $except = null): ?IntentResult
    {
        $other = $this->bookings->findOverlapping($room, $start, $end, $except);

        return $other === null ? null : IntentResult::rejected(
            Rejection::Validation,
            '%room% is already booked for "%other%" at that time.',
            ['%room%' => $room->getName(), '%other%' => $other->getTitle()],
        );
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function flush(string $message, array $parameters): IntentResult
    {
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            // Someone saved a newer version between our read and our write.
            return IntentResult::rejected(Rejection::Conflict, 'This booking was changed meanwhile.');
        }

        return IntentResult::accepted($message, $parameters);
    }

    private function room(string $id): ?Room
    {
        foreach ($this->roomList() as $room) {
            if ((string) $room->getId() === $id) {
                return $room;
            }
        }

        return null;
    }

    private function isStale(Booking $booking, EventIntent $intent): bool
    {
        return $intent->version !== (string) $booking->getVersion();
    }

    /** Day-long bookings (a split room, a film shoot) are fixed. */
    private function isEditable(Booking $booking): bool
    {
        return !$booking->isAllDay();
    }

    private function event(Booking $booking): CalendarEvent
    {
        $mine = $this->user->is($booking->getOrganizer());
        $editable = $this->isEditable($booking);

        return new CalendarEvent(
            id: (string) $booking->getId(),
            title: $booking->getTitle(),
            start: $booking->getStartsAt(),
            end: $booking->getEndsAt(),
            allDay: $booking->isAllDay(),
            resourceIds: [(string) $booking->getRoom()?->getId()],
            type: 'booking',
            interactions: new EventInteractions(movable: $editable, resizable: $editable, deletable: $editable),
            version: (string) $booking->getVersion(),
            style: new EventStyle(color: !$editable ? '#475569' : ($mine ? '#16a34a' : '#5b21b6')),
            hints: ['organizer' => $booking->getOrganizer()?->getName()],
        );
    }
}
