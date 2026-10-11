<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Bookings overlapping [$start, $end) in the given rooms, with their room
     * and organizer loaded in the same query.
     *
     * @param list<Room> $rooms
     *
     * @return list<Booking>
     */
    public function findInRange(\DateTimeImmutable $start, \DateTimeImmutable $end, array $rooms): array
    {
        if ($rooms === []) {
            return [];
        }

        return $this->createQueryBuilder('bk')
            ->addSelect('r', 'o')
            ->join('bk.room', 'r')
            ->join('bk.organizer', 'o')
            ->where('bk.startsAt < :end')
            ->andWhere('bk.endsAt > :start')
            ->andWhere('bk.room IN (:rooms)')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('rooms', $rooms)
            ->orderBy('bk.startsAt')
            ->getQuery()
            ->getResult();
    }

    /** Another booking of $room overlapping [$start, $end), if any. */
    public function findOverlapping(Room $room, \DateTimeImmutable $start, \DateTimeImmutable $end, ?Booking $except = null): ?Booking
    {
        $query = $this->createQueryBuilder('bk')
            ->where('bk.room = :room')
            ->andWhere('bk.startsAt < :end')
            ->andWhere('bk.endsAt > :start')
            ->setParameter('room', $room)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults(1);
        if ($except !== null) {
            $query->andWhere('bk != :except')->setParameter('except', $except);
        }

        return $query->getQuery()->getOneOrNullResult();
    }
}
