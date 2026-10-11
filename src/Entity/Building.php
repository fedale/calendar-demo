<?php

namespace App\Entity;

use App\Repository\BuildingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuildingRepository::class)]
class Building
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    /** Buildings shown on the featured "Rooms" calendar; the others only on "Rooms many". */
    #[ORM\Column]
    private bool $featured = false;

    /** @var Collection<int, Room> */
    #[ORM\OneToMany(targetEntity: Room::class, mappedBy: 'building')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $rooms;

    public function __construct()
    {
        $this->rooms = new ArrayCollection();
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    /** @return Collection<int, Room> */
    public function getRooms(): Collection
    {
        return $this->rooms;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
