<?php

namespace App\Repository;

use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Person>
 */
class PersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    /** The person playing "you" in the demo (no login). */
    public function findDemoUser(): ?Person
    {
        return $this->findOneBy(['email' => Person::DEMO_USER_EMAIL]);
    }

    /**
     * @return list<Person>
     */
    public function findAllByTeam(): array
    {
        return $this->findBy([], ['team' => 'ASC', 'name' => 'ASC']);
    }
}
