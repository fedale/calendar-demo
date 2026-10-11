<?php

namespace App\Story;

use App\Entity\Appointment;
use App\Entity\Booking;
use App\Entity\Person;
use App\Entity\Room;
use App\Enum\RoomKind;
use App\Factory\BuildingFactory;
use App\Factory\PersonFactory;
use App\Factory\RoomFactory;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * The demo's data, around the day it is loaded: from four weeks before to
 * eight weeks after.
 *
 * - 2 featured buildings with 14 rooms (Auditorium D split into D1 and D2),
 *   5 more buildings with 40 meeting rooms, an open space with 12 desks;
 * - 41 people in 5 teams, one of them "you" (you@example.com);
 * - ~8,000 room bookings that never overlap in a room, ~400 desk bookings,
 *   and ~6,000 appointments with their participants, some held in a booked
 *   room.
 *
 * A fixed seed makes every load produce the same data relative to its date.
 * The reference data (buildings, rooms, people) goes through the factories;
 * the ~25,000 bookings, appointments and participations are persisted
 * directly and flushed once, which takes seconds instead of minutes.
 *
 *   php bin/console foundry:load-fixtures initial_state
 */
#[AsFixture('initial_state')]
final class InitialStateStory extends Story
{
    private const TEAMS = [
        'Engineering' => '#2563eb',
        'Design' => '#db2777',
        'Product' => '#7c3aed',
        'Sales' => '#d97706',
        'Operations' => '#059669',
    ];

    private const FIRST_NAMES = ['Alice', 'Marco', 'Giulia', 'Luca', 'Sara', 'Paolo', 'Elena', 'Davide', 'Chiara', 'Andrea', 'Francesca', 'Matteo', 'Laura', 'Simone', 'Valentina', 'Stefano', 'Martina', 'Roberto', 'Anna', 'Federico'];

    private const LAST_NAMES = ['Bianchi', 'Rossi', 'Verdi', 'Neri', 'Gallo', 'Costa', 'Fontana', 'Ricci', 'Marino', 'Greco', 'Bruno', 'Conti', 'De Luca', 'Mancini', 'Lombardi', 'Moretti', 'Barbieri', 'Ferrari', 'Romano', 'Colombo'];

    private const MEETINGS = ['Huddle', 'Workshop', 'Check-in', 'Strategy offsite', 'Hiring loop', 'Webinar', 'Interviews', 'Product demo', 'Partner summit', 'Planning', 'Budget review', 'Town hall', 'Panel', 'Training', 'Hackathon', 'Briefing', 'Keynote rehearsal', 'Retro', 'Masterclass', 'Lunch & learn', 'Kickoff', 'Design review'];

    private const PERSONAL = ['Focus time', '1:1', 'Customer call', 'Code review', 'Lunch', 'Interview', 'Planning'];

    private Randomizer $random;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function build(): void
    {
        $this->random = new Randomizer(new Xoshiro256StarStar(hash('xxh128', 'calendar-demo', true).str_repeat("\0", 16)));
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Rome'));
        $first = $today->modify('monday this week')->modify('-4 weeks');
        $days = [];
        for ($day = $first; $day < $today->modify('+8 weeks'); $day = $day->modify('+1 day')) {
            $days[] = $day;
        }

        $people = $this->people();
        [$rooms, $desks] = $this->rooms();

        $meetings = $this->roomBookings($rooms, $days, $people);
        $this->deskBookings($desks, $days, $people);
        $this->appointments($meetings, $days, $people);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @return list<Person>
     */
    private function people(): array
    {
        $people = [PersonFactory::new()->demoUser()->create()];
        $teams = array_keys(self::TEAMS);
        for ($i = 0; $i < 40; ++$i) {
            $first = self::FIRST_NAMES[$i % \count(self::FIRST_NAMES)];
            // Shifted every 20 people, so first and last names never pair up twice.
            $last = self::LAST_NAMES[($i * 7 + intdiv($i, \count(self::FIRST_NAMES)) * 3) % \count(self::LAST_NAMES)];
            $team = $teams[$i % \count($teams)];
            $people[] = PersonFactory::createOne([
                'name' => "$first $last",
                'email' => strtolower(str_replace(' ', '', "$first.$last")).$i.'@example.com',
                'team' => $team,
                'color' => self::TEAMS[$team],
            ]);
        }

        return $people;
    }

    /**
     * @return array{list<Room>, list<Room>} bookable rooms, desks
     */
    private function rooms(): array
    {
        $rooms = [];
        $position = 0;
        foreach (['460 Bryant' => range('A', 'F'), '564 Pacific' => range('G', 'L')] as $name => $letters) {
            $building = BuildingFactory::createOne(['name' => $name, 'featured' => true]);
            foreach ($letters as $letter) {
                $room = RoomFactory::createOne(['name' => "Auditorium $letter", 'kind' => RoomKind::Auditorium, 'capacity' => 40, 'position' => ++$position, 'building' => $building]);
                $rooms[] = $room;
                if ($letter === 'D') {
                    foreach (['D1', 'D2'] as $half) {
                        $rooms[] = RoomFactory::createOne(['name' => "Room $half", 'kind' => RoomKind::MeetingRoom, 'capacity' => 10, 'position' => ++$position, 'building' => $building, 'parent' => $room]);
                    }
                }
            }
        }
        foreach (['Atrium', 'Harbor', 'Lakeside', 'Meridian', 'Summit'] as $name) {
            $building = BuildingFactory::createOne(['name' => $name]);
            for ($i = 1; $i <= 8; ++$i) {
                $rooms[] = RoomFactory::createOne(['name' => sprintf('%s %02d', $name, $i), 'capacity' => 4 + 2 * ($i % 5), 'position' => $i, 'building' => $building]);
            }
        }

        $openSpace = BuildingFactory::createOne(['name' => 'Open space']);
        $desks = [];
        foreach (['A', 'B'] as $row) {
            for ($i = 1; $i <= 6; ++$i) {
                $desks[] = RoomFactory::createOne(['name' => "Desk $row$i", 'kind' => RoomKind::Desk, 'capacity' => 1, 'position' => \count($desks) + 1, 'building' => $openSpace]);
            }
        }

        return [$rooms, $desks];
    }

    /**
     * Bookings laid one after the other through each room's day, so a room
     * never holds two at once.
     *
     * @param list<Room>               $rooms
     * @param list<\DateTimeImmutable> $days
     * @param list<Person>             $people
     *
     * @return list<Booking> the meeting-room bookings, to hold some appointments
     */
    private function roomBookings(array $rooms, array $days, array $people): array
    {
        $meetings = [];
        foreach ($days as $day) {
            $weekend = $day->format('N') >= 6;
            foreach ($rooms as $room) {
                if ($room->getName() === 'Auditorium D' && !$weekend) {
                    $this->booking($room, 'Split into D1 + D2', $day, $day->modify('+1 day'), $people[1], true);
                    continue;
                }
                if ($room->getName() === 'Auditorium J' && (int) $day->format('j') % 2 === 0) {
                    $this->booking($room, 'Film shoot', $day, $day->modify('+1 day'), $people[2], true);
                    continue;
                }
                if ($room->getName() === 'Auditorium D' || ($weekend && $this->random->getInt(1, 5) > 1)) {
                    continue;
                }

                $cursor = 8 * 60 + 30 * $this->random->getInt(0, 4);
                $count = $this->random->getInt(0, $room->getParent() !== null ? 5 : 4);
                for ($i = 0; $i < $count && $cursor < 18 * 60; ++$i) {
                    $length = 30 * $this->random->getInt(1, 6);
                    $start = $day->setTime(intdiv($cursor, 60), $cursor % 60);
                    $organizer = $people[$this->random->getInt(0, \count($people) - 1)];
                    $booking = $this->booking($room, self::MEETINGS[$this->random->getInt(0, \count(self::MEETINGS) - 1)], $start, $start->modify("+$length minutes"), $organizer);
                    if ($room->getKind() === RoomKind::MeetingRoom) {
                        $meetings[] = $booking;
                    }
                    $cursor += $length + 30 * $this->random->getInt(0, 4);
                }
            }
        }

        return $meetings;
    }

    /**
     * @param list<Room>               $desks
     * @param list<\DateTimeImmutable> $days
     * @param list<Person>             $people
     */
    private function deskBookings(array $desks, array $days, array $people): void
    {
        foreach ($days as $day) {
            if ($day->format('N') >= 6) {
                continue;
            }
            $you = $this->random->getInt(1, 3) === 1 ? $this->random->getInt(0, \count($desks) - 1) : null;
            foreach ($desks as $index => $desk) {
                if ($index === $you) {
                    $this->booking($desk, 'Desk', $day, $day->modify('+1 day'), $people[0], true);
                } elseif ($this->random->getInt(1, 100) <= 55) {
                    $this->booking($desk, 'Desk', $day, $day->modify('+1 day'), $people[$this->random->getInt(1, \count($people) - 1)], true);
                }
            }
        }
    }

    /**
     * Meetings held in a booked room (two in five meeting-room bookings), and
     * personal appointments laid one after the other through each day.
     *
     * @param list<Booking>            $meetings
     * @param list<\DateTimeImmutable> $days
     * @param list<Person>             $people
     */
    private function appointments(array $meetings, array $days, array $people): void
    {
        foreach ($meetings as $booking) {
            if ($this->random->getInt(1, 5) > 2) {
                continue;
            }
            $participants = [$booking->getOrganizer()];
            foreach ($this->random->pickArrayKeys($people, $this->random->getInt(1, 5)) as $key) {
                $participants[] = $people[$key];
            }
            $this->appointment($booking->getTitle(), $booking->getStartsAt(), $booking->getEndsAt(), $booking->getOrganizer(), $participants, $booking);
        }

        foreach ($days as $day) {
            if ($day->format('N') >= 6) {
                continue;
            }
            foreach ($people as $index => $person) {
                $cursor = 9 * 60 + 30 * $this->random->getInt(0, 6);
                for ($i = $this->random->getInt(0, 2); $i > 0 && $cursor < 17 * 60; --$i) {
                    $length = 30 * $this->random->getInt(1, 4);
                    $title = self::PERSONAL[$this->random->getInt(0, \count(self::PERSONAL) - 1)];
                    $participants = [$person];
                    if ($title === '1:1' || $title === 'Interview') {
                        $participants[] = $people[($index + $this->random->getInt(1, \count($people) - 1)) % \count($people)];
                    }
                    $start = $day->setTime(intdiv($cursor, 60), $cursor % 60);
                    $this->appointment($title, $start, $start->modify("+$length minutes"), $person, $participants);
                    $cursor += $length + 30 * $this->random->getInt(1, 4);
                }
            }
        }
    }

    private function booking(Room $room, string $title, \DateTimeImmutable $start, \DateTimeImmutable $end, Person $organizer, bool $allDay = false): Booking
    {
        $booking = (new Booking())->setTitle($title)->setRoom($room)->setOrganizer($organizer)->setAllDay($allDay)->reschedule($start, $end);
        $this->entityManager->persist($booking);

        return $booking;
    }

    /**
     * @param list<Person|null> $participants
     */
    private function appointment(string $title, \DateTimeImmutable $start, \DateTimeImmutable $end, ?Person $owner, array $participants, ?Booking $booking = null): void
    {
        $appointment = (new Appointment())->setTitle($title)->setOwner($owner)->setBooking($booking)->reschedule($start, $end);
        foreach ($participants as $participant) {
            if ($participant !== null) {
                $appointment->addParticipant($participant);
            }
        }
        $this->entityManager->persist($appointment);
    }
}
