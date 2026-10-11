<?php

namespace App\Tests;

use App\Controller\CalendarController;
use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Story\InitialStateStory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Every page on the demo data (var/test.db, reset and loaded per test).
 */
final class SmokeTest extends WebTestCase
{
    use Factories;
    use InteractsWithLiveComponents;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // The client first: it boots the kernel the story needs.
        $this->client = static::createClient();
        InitialStateStory::load();
    }

    public function testHomeListsEveryCalendar(): void
    {
        $client = $this->client;
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(\count(CalendarController::CALENDARS), $crawler->filter('.demo-cards li'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function calendars(): iterable
    {
        foreach (array_keys(CalendarController::CALENDARS) as $id) {
            yield $id => [$id];
        }
    }

    #[DataProvider('calendars')]
    public function testEveryCalendarRenders(string $id): void
    {
        $client = $this->client;
        $client->request('GET', '/calendar/'.$id.'?date=2026-10-10');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.cal .cal-toolbar__title');
    }

    public function testTheLocaleSwitchTranslatesTheCalendar(): void
    {
        $client = $this->client;
        $client->request('GET', '/calendar/shifts?date=2026-10-10&_locale=it');

        self::assertSelectorTextSame('.cal-toolbar__title', 'ottobre 2026');
    }

    public function testAMoveIsSavedThroughTheDomainRules(): void
    {
        $repository = static::getContainer()->get(BookingRepository::class);
        $booking = $repository->createQueryBuilder('b')
            ->join('b.room', 'r')
            ->where('r.name = :room')->andWhere('b.allDay = false')
            ->setParameter('room', 'Auditorium A')
            ->setMaxResults(1)
            ->getQuery()->getSingleResult();
        \assert($booking instanceof Booking);
        $day = $booking->getStartsAt()->setTimezone(new \DateTimeZone('Europe/Rome'))->format('Y-m-d');

        $component = $this->createLiveComponent('Fedale:Calendar', ['calendar' => 'rooms', 'date' => $day]);
        $component->call('moveEvent', [
            'source' => 'rooms',
            'id' => (string) $booking->getId(),
            'version' => (string) $booking->getVersion(),
            'start' => $day.'T22:00',
            'end' => $day.'T23:00',
        ]);

        self::assertStringContainsString('Moved', $component->render()->crawler()->filter('.cal-notice')->text());
        $moved = $repository->find($booking->getId());
        self::assertSame('22:00', $moved?->getStartsAt()->setTimezone(new \DateTimeZone('Europe/Rome'))->format('H:i'));
        self::assertSame($booking->getVersion() + 1, $moved?->getVersion());
    }

    public function testUnknownCalendarsAreNotFound(): void
    {
        $client = $this->client;
        $client->request('GET', '/calendar/nope');

        self::assertResponseStatusCodeSame(404);
    }
}
