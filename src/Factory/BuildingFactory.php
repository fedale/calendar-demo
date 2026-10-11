<?php

namespace App\Factory;

use App\Entity\Building;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Building>
 */
final class BuildingFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Building::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => self::faker()->unique()->company(),
            'address' => self::faker()->streetAddress(),
            'featured' => false,
        ];
    }
}
