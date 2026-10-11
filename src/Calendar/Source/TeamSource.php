<?php

namespace App\Calendar\Source;

use App\Entity\Appointment;
use App\Entity\Person;
use App\Repository\AppointmentRepository;
use App\Repository\BookingRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
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
use Fedale\CalendarBundle\Model\EventInteractions;
use Fedale\CalendarBundle\Model\EventStyle;
use Symfony\Contracts\Service\ResetInterface;

/**
 * People's agendas: one row per person, grouped by team; an appointment
 * shows on the row of each participant (one event, several resources).
 *
 * The agenda's rules: dropping an appointment on another person's row swaps
 * that participant; an appointment held in a booked room moves with its
 * booking, and only if the room is free at the new time.
 */
#[AsCalendarSource('team')]
final class TeamSource implements EventSourceInterface, ResourceSourceInterface, IntentHandlerInterface, ResetInterface
{
    /** @var array<string, Person>|null id => person */
    private ?array $people = null;

    public function __construct(
        private readonly PersonRepository $personRepository,
        private readonly AppointmentRepository $appointments,
        private readonly BookingRepository $bookings,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getResources(CalendarQuery $query): iterable
    {
        foreach ($this->people() as $id => $person) {
            yield new CalendarResource((string) $id, $person->getName(), data: ['team' => $person->getTeam(), 'email' => $person->getEmail()]);
        }
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach ($this->appointments->findInRange($query->range->start, $query->range->end) as $appointment) {
            $room = $appointment->getBooking()?->getRoom()?->getName();
            yield new CalendarEvent(
                id: (string) $appointment->getId(),
                title: $appointment->getTitle().($room !== null ? ' · '.$room : ''),
                start: $appointment->getStartsAt(),
                end: $appointment->getEndsAt(),
                allDay: $appointment->isAllDay(),
                resourceIds: array_map(fn(Person $person) => (string) $person->getId(), $appointment->getParticipants()->toArray()),
                type: $room !== null ? 'meeting' : 'appointment',
                interactions: new EventInteractions(movable: true, resizable: true, deletable: true),
                version: (string) $appointment->getVersion(),
                style: new EventStyle(color: $appointment->getOwner()?->getColor()),
                hints: ['owner' => $appointment->getOwner()?->getName(), 'room' => $room],
            );
        }
    }

    public function handle(CalendarIntent $intent): IntentResult
    {
        if ($intent instanceof SelectRange) {
            $person = $intent->resourceId !== null ? ($this->people()[$intent->resourceId] ?? null) : null;
            if ($person === null) {
                return IntentResult::rejected(Rejection::Validation, 'Select a time on a person\'s row.');
            }
            $appointment = (new Appointment())->setTitle('New appointment')->setOwner($person)->addParticipant($person)->setAllDay($intent->allDay)->reschedule($intent->start, $intent->end);
            $this->entityManager->persist($appointment);

            return $this->flush('Added an appointment for %name%.', ['%name%' => $person->getName()]);
        }
        if (!$intent instanceof MoveEvent && !$intent instanceof ResizeEvent && !$intent instanceof DeleteEvent) {
            return IntentResult::rejected(Rejection::Forbidden, 'The agenda does not support this action.');
        }

        $appointment = $this->appointments->find((int) $intent->eventId);
        if ($appointment === null) {
            return IntentResult::rejected(Rejection::Conflict, 'This appointment no longer exists.');
        }
        if ($intent->version !== (string) $appointment->getVersion()) {
            return IntentResult::rejected(Rejection::Conflict, 'This appointment was changed meanwhile.');
        }

        if ($intent instanceof DeleteEvent) {
            $this->entityManager->remove($appointment);
            $this->entityManager->flush();

            return IntentResult::accepted('Removed "%title%".', ['%title%' => $appointment->getTitle()]);
        }

        if ($intent instanceof MoveEvent && $intent->changesResource()) {
            $from = $this->people()[(string) $intent->fromResourceId] ?? null;
            $to = $this->people()[(string) $intent->toResourceId] ?? null;
            if ($from === null || $to === null) {
                return IntentResult::rejected(Rejection::Validation, 'Drop the appointment on a person\'s row.');
            }
            if ($appointment->getParticipants()->contains($to)) {
                return IntentResult::rejected(Rejection::Validation, '%name% already takes part.', ['%name%' => $to->getName()]);
            }
            $appointment->removeParticipant($from)->addParticipant($to);
            if ($appointment->getOwner() === $from) {
                $appointment->setOwner($to);
            }
        }

        $booking = $appointment->getBooking();
        if ($booking !== null && $booking->getRoom() !== null) {
            $other = $this->bookings->findOverlapping($booking->getRoom(), $intent->start, $intent->end, $booking);
            if ($other !== null) {
                return IntentResult::rejected(Rejection::Validation, '%room% is taken by "%other%" at that time; the meeting stays where it is.', ['%room%' => $booking->getRoom()->getName(), '%other%' => $other->getTitle()]);
            }
            $booking->reschedule($intent->start, $intent->end);
        }
        $appointment->reschedule($intent->start, $intent->end);

        return $this->flush($intent instanceof MoveEvent ? 'Moved "%title%".' : 'Changed the end of "%title%".', ['%title%' => $appointment->getTitle()]);
    }

    public function reset(): void
    {
        $this->people = null;
    }

    /**
     * @return array<string, Person>
     */
    private function people(): array
    {
        if ($this->people === null) {
            $this->people = [];
            foreach ($this->personRepository->findAllByTeam() as $person) {
                $this->people[(string) $person->getId()] = $person;
            }
        }

        return $this->people;
    }

    /**
     * @param array<string, scalar> $parameters
     */
    private function flush(string $message, array $parameters): IntentResult
    {
        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            return IntentResult::rejected(Rejection::Conflict, 'This appointment was changed meanwhile.');
        }

        return IntentResult::accepted($message, $parameters);
    }
}
