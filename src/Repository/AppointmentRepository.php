<?php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * Appointments overlapping [$start, $end), with participants, owner and
     * room loaded in the same query.
     *
     * @return list<Appointment>
     */
    public function findInRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('p', 'o', 'bk', 'r')
            ->leftJoin('a.participants', 'p')
            ->join('a.owner', 'o')
            ->leftJoin('a.booking', 'bk')
            ->leftJoin('bk.room', 'r')
            ->where('a.startsAt < :end')
            ->andWhere('a.endsAt > :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.startsAt')
            ->getQuery()
            ->getResult();
    }
}
