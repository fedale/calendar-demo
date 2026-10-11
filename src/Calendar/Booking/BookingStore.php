<?php

namespace App\Calendar\Booking;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The demo's "database": changes to the generated bookings, kept in the
 * session so every visitor plays with their own copy, one bucket per source.
 * A booking stored here replaces the generated one with the same id; a
 * deleted id (null) hides it.
 */
final class BookingStore
{
    private const KEY = 'demo.bookings';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return array<string, array<string, mixed>|null> id => booking, or null when deleted
     */
    public function all(string $bucket): array
    {
        $session = $this->requestStack->getCurrentRequest()?->hasSession() ? $this->requestStack->getSession() : null;

        return $session?->get(self::KEY.'.'.$bucket, []) ?? [];
    }

    /**
     * @param array<string, mixed>|null $booking null deletes
     */
    public function save(string $bucket, string $id, ?array $booking): void
    {
        $bookings = $this->all($bucket);
        $bookings[$id] = $booking;
        $this->requestStack->getSession()->set(self::KEY.'.'.$bucket, $bookings);
    }
}
