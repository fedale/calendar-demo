<?php

namespace App\Repository;

use App\Entity\Room;
use App\Enum\RoomKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    /**
     * Rooms of the given kinds, with their building, ordered by building then
     * position.
     *
     * @param list<RoomKind> $kinds
     * @param bool|null      $featured only rooms of featured (true) or other (false) buildings; null = all
     *
     * @return list<Room>
     */
    public function findByKinds(array $kinds, ?bool $featured = null): array
    {
        $query = $this->createQueryBuilder('r')
            ->addSelect('b')
            ->join('r.building', 'b')
            ->where('r.kind IN (:kinds)')
            ->setParameter('kinds', array_map(fn(RoomKind $kind) => $kind->value, $kinds))
            ->orderBy('b.name')
            ->addOrderBy('r.position');
        if ($featured !== null) {
            $query->andWhere('b.featured = :featured')->setParameter('featured', $featured);
        }

        return $query->getQuery()->getResult();
    }
}
