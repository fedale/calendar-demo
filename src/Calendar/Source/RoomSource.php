<?php

namespace App\Calendar\Source;

use Fedale\CalendarBundle\Attribute\AsCalendarSource;
use Fedale\CalendarBundle\Contract\EventSourceInterface;
use Fedale\CalendarBundle\Contract\ResourceSourceInterface;
use Fedale\CalendarBundle\Model\CalendarEvent;
use Fedale\CalendarBundle\Model\CalendarQuery;
use Fedale\CalendarBundle\Model\CalendarResource;
use Fedale\CalendarBundle\Model\EventStyle;

/**
 * Meeting rooms in two buildings, with the events booked in them. The same
 * source provides the resources (rows of the timeline) and the events;
 * Auditorium D is split into two halves, nested under it with parentId.
 */
#[AsCalendarSource('rooms')]
class RoomSource implements EventSourceInterface, ResourceSourceInterface
{
    private const TITLES = ['Huddle', 'Workshop', 'Check-in', 'Strategy offsite', 'Hiring loop', 'Webinar', 'Interviews', 'Product demo', 'Partner summit', 'Planning', 'Budget review', 'Town hall', 'Panel', 'Training', 'Hackathon', 'Briefing', 'Keynote rehearsal', 'Retro', 'Masterclass', 'Lunch & learn', 'Kickoff', 'Design review'];

    public function getResources(CalendarQuery $query): iterable
    {
        return $this->rooms();
    }

    public function getEvents(CalendarQuery $query): iterable
    {
        foreach (DemoDays::of($query) as $day) {
            foreach ($this->rooms() as $room) {
                $random = DemoDays::random('rooms-'.$room->id, $day);
                $id = $day->format('Y-m-d').'-'.$room->id;

                if ($room->id === 'aud-d' && $day->format('N') <= 5) {
                    yield $this->event("$id-split", 'Split into D1 + D2', $day, $day->modify('+1 day'), $room, true);
                    continue;
                }
                if ($room->id === 'aud-j' && (int) $day->format('j') % 2 === 0) {
                    yield $this->event("$id-shoot", 'Film shoot', $day, $day->modify('+1 day'), $room, true);
                    continue;
                }
                if ($day->format('N') >= 6 && $random->getInt(0, 2) > 0) {
                    continue;
                }
                $count = $random->getInt(0, $room->parentId !== null ? 5 : 3);
                for ($i = 0; $i < $count; ++$i) {
                    $start = DemoDays::at($day, sprintf('%02d:%02d', $random->getInt(8, 17), 30 * $random->getInt(0, 1)));
                    yield $this->event("$id-$i", self::TITLES[$random->getInt(0, \count(self::TITLES) - 1)], $start, $start->modify('+'.(30 * $random->getInt(1, 8)).' minutes'), $room);
                }
            }
        }
    }

    /**
     * @return list<CalendarResource>
     */
    protected function rooms(): array
    {
        $rooms = [];
        foreach (['460 Bryant' => range('A', 'F'), '564 Pacific' => range('G', 'L')] as $building => $letters) {
            foreach ($letters as $letter) {
                $rooms[] = $this->room('aud-'.strtolower($letter), "Auditorium $letter", $building, 40);
                if ($letter === 'D') {
                    $rooms[] = $this->room('aud-d1', 'Room D1', $building, 10, 'aud-d');
                    $rooms[] = $this->room('aud-d2', 'Room D2', $building, 10, 'aud-d');
                }
            }
        }

        return $rooms;
    }

    protected function room(string $id, string $title, string $building, int $occupancy, ?string $parentId = null): CalendarResource
    {
        return new CalendarResource($id, $title, parentId: $parentId, data: ['building' => $building, 'occupancy' => $occupancy]);
    }

    private function event(string $id, string $title, \DateTimeImmutable $start, \DateTimeImmutable $end, CalendarResource $room, bool $allDay = false): CalendarEvent
    {
        return new CalendarEvent(
            id: $id,
            title: $title,
            start: $start,
            end: $end,
            allDay: $allDay,
            resourceIds: [$room->id],
            type: 'booking',
            style: new EventStyle(color: '#5b21b6'),
        );
    }
}
