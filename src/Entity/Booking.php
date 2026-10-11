<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A room (or desk) reserved for a time span by a person. `version` is
 * Doctrine's optimistic lock: it is what the calendar sends back with a move,
 * so a change made meanwhile by someone else is detected.
 */
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Index(columns: ['starts_at', 'ends_at'])]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $title = '';

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    /** Exclusive. */
    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column]
    private bool $allDay = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Room $room = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Person $organizer = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct()
    {
        $this->startsAt = new \DateTimeImmutable();
        $this->endsAt = $this->startsAt->modify('+1 hour');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function reschedule(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): static
    {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('A booking must end after it starts.');
        }
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): static
    {
        $this->allDay = $allDay;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getOrganizer(): ?Person
    {
        return $this->organizer;
    }

    public function setOrganizer(?Person $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
