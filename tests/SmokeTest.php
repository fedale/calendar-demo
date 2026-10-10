<?php

namespace App\Tests;

use App\Controller\CalendarController;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    public function testHomeListsEveryCalendar(): void
    {
        $client = static::createClient();
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
        $client = static::createClient();
        $client->request('GET', '/calendar/'.$id.'?date=2026-10-10');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.cal .cal-toolbar__title');
    }

    public function testTheLocaleSwitchTranslatesTheCalendar(): void
    {
        $client = static::createClient();
        $client->request('GET', '/calendar/shifts?date=2026-10-10&_locale=it');

        self::assertSelectorTextSame('.cal-toolbar__title', 'ottobre 2026');
    }

    public function testUnknownCalendarsAreNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/calendar/nope');

        self::assertResponseStatusCodeSame(404);
    }
}
