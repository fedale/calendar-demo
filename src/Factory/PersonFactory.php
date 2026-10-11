<?php

namespace App\Factory;

use App\Entity\Person;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Person>
 */
final class PersonFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Person::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->name(),
            'email' => self::faker()->unique()->safeEmail(),
            'team' => self::faker()->randomElement(['Engineering', 'Design', 'Product', 'Sales', 'Operations']),
            'color' => self::faker()->hexColor(),
        ];
    }

    /** The person playing "you" in the demo. */
    public function demoUser(): self
    {
        return $this->with(['name' => 'You', 'email' => Person::DEMO_USER_EMAIL, 'team' => 'Engineering', 'color' => '#16a34a']);
    }
}
