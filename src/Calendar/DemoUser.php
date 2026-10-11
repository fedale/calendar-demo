<?php

namespace App\Calendar;

use App\Entity\Person;
use App\Repository\PersonRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * "You": the demo has no login, so the current user is the person with
 * Person::DEMO_USER_EMAIL. A real application would read the security token.
 */
final class DemoUser implements ResetInterface
{
    private ?Person $person = null;

    public function __construct(private readonly PersonRepository $people)
    {
    }

    public function get(): ?Person
    {
        return $this->person ??= $this->people->findDemoUser();
    }

    public function is(?Person $person): bool
    {
        return $person !== null && $person->getId() === $this->get()?->getId();
    }

    public function reset(): void
    {
        $this->person = null;
    }
}
