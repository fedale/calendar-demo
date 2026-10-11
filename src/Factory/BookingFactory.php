<?php

namespace App\Factory;

use App\Entity\Booking;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Booking>
 */
final class BookingFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Booking::class;
    }

    protected function defaults(): array|callable
    {
        $start = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-1 week', '+2 weeks'))->setTime(self::faker()->numberBetween(8, 17), 0);

        return [
            'title' => self::faker()->randomElement(['Workshop', 'Planning', 'Review', 'Training', 'Kickoff']),
            'startsAt' => $start,
            'endsAt' => $start->modify('+1 hour'),
            'allDay' => false,
            'room' => RoomFactory::new(),
            'organizer' => PersonFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        // Start and end have no setters (reschedule() keeps them consistent):
        // the fixtures write the properties directly.
        return $this->instantiateWith(Instantiator::withConstructor()->alwaysForce());
    }
}
