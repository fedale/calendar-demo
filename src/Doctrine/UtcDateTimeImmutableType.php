<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;

/**
 * datetime_immutable, always in UTC: values are converted to UTC on the way
 * in and read back as UTC, whatever PHP's default timezone. SQLite keeps no
 * timezone, so without this a date would shift with the server's settings.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    private static ?\DateTimeZone $utc = null;

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeImmutable) {
            $value = $value->setTimezone(self::utc());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if ($value === null || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $date = \DateTimeImmutable::createFromFormat($platform->getDateTimeFormatString(), $value, self::utc());

        return $date !== false ? $date : parent::convertToPHPValue($value, $platform);
    }

    private static function utc(): \DateTimeZone
    {
        return self::$utc ??= new \DateTimeZone('UTC');
    }
}
