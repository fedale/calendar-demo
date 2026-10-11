<?php

namespace App\Entity;

use App\Enum\RoomKind;
use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A bookable space: an auditorium, a meeting room or a desk. A room may be
 * split into smaller ones, its children (Auditorium D into D1 and D2).
 */
#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Index(columns: ['kind'])]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(enumType: RoomKind::class)]
    private RoomKind $kind = RoomKind::MeetingRoom;

    #[ORM\Column]
    private int $capacity = 1;

    /** Order of the room within its building. */
    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(inversedBy: 'rooms')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Building $building = null;

    #[ORM\ManyToOne(inversedBy: 'children')]
    private ?Room $parent = null;

    /** @var Collection<int, Room> */
    #[ORM\OneToMany(targetEntity: Room::class, mappedBy: 'parent')]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getKind(): RoomKind
    {
        return $this->kind;
    }

    public function setKind(RoomKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getBuilding(): ?Building
    {
        return $this->building;
    }

    public function setBuilding(?Building $building): static
    {
        $this->building = $building;

        return $this;
    }

    public function getParent(): ?Room
    {
        return $this->parent;
    }

    public function setParent(?Room $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, Room> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
