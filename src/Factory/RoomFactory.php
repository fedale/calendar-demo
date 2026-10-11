<?php

namespace App\Factory;

use App\Entity\Room;
use App\Enum\RoomKind;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Room>
 */
final class RoomFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Room::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'name' => 'Room '.self::faker()->unique()->numberBetween(100, 999),
            'kind' => RoomKind::MeetingRoom,
            'capacity' => self::faker()->numberBetween(4, 12),
            'position' => 0,
            'building' => BuildingFactory::new(),
        ];
    }
}
