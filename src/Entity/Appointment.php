<?php

namespace App\Entity;

use App\Repository\AppointmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entry of people's agendas: one owner, any number of participants, and
 * optionally the room booking where it takes place.
 */
#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Index(columns: ['starts_at', 'ends_at'])]
class Appointment
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
    private ?Person $owner = null;

    /** @var Collection<int, Person> */
    #[ORM\ManyToMany(targetEntity: Person::class)]
    #[ORM\JoinTable(name: 'appointment_participant')]
    private Collection $participants;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Booking $booking = null;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
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
            throw new \InvalidArgumentException('An appointment must end after it starts.');
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

    public function getOwner(): ?Person
    {
        return $this->owner;
    }

    public function setOwner(?Person $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /** @return Collection<int, Person> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(Person $person): static
    {
        if (!$this->participants->contains($person)) {
            $this->participants->add($person);
        }

        return $this;
    }

    public function removeParticipant(Person $person): static
    {
        $this->participants->removeElement($person);

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
