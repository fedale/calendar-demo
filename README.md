Calendar Demo Application
=========================

This project is a demo application for [fedale/calendar-bundle][1], a server-rendered calendar
component for Symfony UX that knows nothing about your domain.

Each page feeds the same calendar component from a different source:

| Page | What it shows |
|---|---|
| Shifts | Morning, afternoon and night shifts in month, week, day and list views; in the month, night shifts stay on the day they start (`nextDayThreshold`); public holidays are background bands, week numbers are on |
| Seats | All-day desk bookings; other people's bookings are "Busy" because the source withholds their names; weekends are hidden |
| Lessons | A weekly timetable expanded by the source: a weekly list by default, and a week time grid limited to school hours, with business hours shaded |
| Overview | The three sources above on one calendar |
| Rooms | A resource timeline: rooms grouped by building, a room split into two nested halves, day / week / month, one column per room ("Columns"), business hours, and an "Add event" button handled in `assets/app.js` |
| Stress | About 500 events a month, to measure rendering cost |
| Rooms many | The timeline with 50 rooms, to measure rendering cost |

There is no database: the sources generate their events from a random generator seeded by the date,
so a given day always shows the same events.

Requirements
------------

  * PHP 8.4 or higher, with the `intl` extension;
  * [Composer][2];
  * and the [usual Symfony application requirements][3].

You don't need Node.js: assets are managed with AssetMapper.

Installation
------------

The application loads calendar-bundle from a relative path repository (`../calendar-bundle`), so
you must clone both repositories into the same parent directory:

```bash
$ mkdir calendar && cd calendar/
$ git clone git@github.com:fedale/calendar-bundle.git
$ git clone git@github.com:fedale/calendar-demo.git
$ cd calendar-demo/
$ composer install                    # symlinks ../calendar-bundle into vendor/
```

Usage
-----

```bash
$ symfony serve
```

Then open `https://127.0.0.1:8000`. Switch the language with the EN / IT links in the header.

To change the load of the stress calendar, set the number of timed events per day:

```bash
$ CALENDAR_STRESS_PER_DAY=32 symfony serve
```

Benchmark
---------

`bin/benchmark` measures a calendar page and one Live navigation against a running server, as the
median of several runs. Run the server in prod mode, so the numbers leave out the profiler:

```bash
$ APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
$ APP_ENV=prod APP_DEBUG=0 php -S 127.0.0.1:8002 -t public &
$ bin/benchmark http://127.0.0.1:8002 'rooms_many?view=timelineMonth'
```

Tests
-----

```bash
$ vendor/bin/phpunit
```

[1]: ../calendar-bundle
[2]: https://getcomposer.org/
[3]: https://symfony.com/doc/current/setup.html#technical-requirements
