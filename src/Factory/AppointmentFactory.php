<?php

namespace App\Factory;

use App\Entity\Appointment;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Appointment>
 */
final class AppointmentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Appointment::class;
    }

    protected function defaults(): array|callable
    {
        $start = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 week', '+2 weeks'))->setTime(self::faker()->numberBetween(8, 17), 0);

        return [
            'title' => self::faker()->randomElement(['1:1', 'Focus time', 'Sync', 'Interview']),
            'startsAt' => $start,
            'endsAt' => $start->modify('+30 minutes'),
            'allDay' => false,
            'owner' => PersonFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::withConstructor()->alwaysForce());
    }
}
