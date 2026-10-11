Calendar Demo Application
=========================

This project is a demo application for [fedale/calendar-bundle][1], a server-rendered calendar
component for Symfony UX that knows nothing about your domain.

Each page feeds the same calendar component from a different source. Some read a SQLite database
through Doctrine, as a real application would; others generate their events in code, to show that a
source can sit on anything.

| Page | What it shows |
|---|---|
| Shifts | Morning, afternoon and night shifts in month, week, day and list views; in the month, night shifts stay on the day they start (`nextDayThreshold`); public holidays are background bands, week numbers are on |
| Seats | *Database.* All-day desk bookings; other people's bookings are "Busy" because the source withholds their names; your own (green) ones can be dragged to another day or deleted; weekends are hidden |
| Lessons | A weekly timetable expanded by the source: a weekly list by default, and a week time grid limited to school hours, with business hours shaded |
| Overview | The three sources above on one calendar |
| Rooms | *Database.* An editable resource timeline: rooms grouped by building, a room split into two nested halves, day / week / month, one column per room ("Columns"), business hours. Drag bookings to move or resize them, drag over empty time to book, press Delete to cancel; overlapping bookings are refused. An "Add event" button is handled in `assets/app.js` |
| Team | *Database.* People's agendas, grouped by team: an appointment shows on every participant's row. Drop it on another row to swap that participant; a meeting held in a booked room moves with its booking, if the room is free |
| Stress | About 500 generated events a month, to measure rendering cost |
| Rooms many | *Database.* Every room (54) and its bookings, to measure rendering cost |

Shifts, lessons and stress generate their events from a random generator seeded by the date, so a
given day always shows the same events.

The data model
--------------

| Entity | What it is | Relations |
|---|---|---|
| `Building` | A site; the featured ones make the "Rooms" calendar | has many `Room` |
| `Room` | An auditorium, a meeting room or a desk (`RoomKind`) | belongs to a `Building`; may be split into children (Auditorium D into D1 and D2) |
| `Person` | Someone who books rooms and has appointments; `you@example.com` plays "you" | |
| `Booking` | A room reserved for a time span | a `Room`, an organizer `Person` |
| `Appointment` | An agenda entry | an owner `Person`, participant `Person`s, optionally the `Booking` where it takes place |

`Booking` and `Appointment` carry a `version` column (Doctrine's optimistic lock). The calendar
sends it back with every change, so a booking changed meanwhile by someone else is refused instead
of overwritten. Dates are stored in UTC (`App\Doctrine\UtcDateTimeImmutableType`); the calendar
shows them in Europe/Rome.

The calendars' end-user settings (the gear) are stored by fedale/setting-bundle in the same
database (`setting_scoped`).

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
$ php bin/console foundry:load-fixtures initial_state
```

The last command creates the SQLite database (`var/data.db`) and loads the demo data around the
current date, from four weeks before to eight weeks after: 54 rooms and 12 desks in 8 buildings, 41
people, about 7,500 bookings and 4,900 appointments. It takes a few seconds; run it again to start
over, or a few weeks later to move the data back around today.

Usage
-----

```bash
$ symfony serve
```

Then open `https://127.0.0.1:8000`. Switch the language with the EN / IT / FR / DE / ES links in
the header, and open the calendar settings with the gear in each calendar's toolbar.

To change the load of the stress calendar, set the number of timed events per day:

```bash
$ CALENDAR_STRESS_PER_DAY=32 symfony serve
```

API
---

The calendars are also an API, through calendar-bundle's API Platform bridge: open
`http://127.0.0.1:8000/api` for the interactive documentation. Clients read what the calendar
shows and send the same intents as the UI, so the domain rules apply as well:

```bash
$ curl -H 'Accept: application/json' \
    'http://127.0.0.1:8000/api/calendars/rooms/events?start=2026-10-13T08:00:00%2B02:00&end=2026-10-13T12:00:00%2B02:00'

$ curl -X POST -H 'Accept: application/json' -H 'Content-Type: application/json' -H 'If-Match: "1"' \
    -d '{"start": "2026-10-13T07:00:00+02:00", "end": "2026-10-13T07:30:00+02:00"}' \
    http://127.0.0.1:8000/api/calendars/rooms/events/rooms/2874/move
```

A stale `If-Match` answers 409, an overlapping booking 422, an event that cannot move 403.

The bridge is turned on in `config/packages/fedale_calendar.yaml` (`api.enabled`). The demo
exposes every calendar without access checks because it has no users; a real application lists
the calendars to expose and sets read/write security attributes (see the bundle's README).

Benchmark
---------

`bin/benchmark` measures a calendar page and one Live navigation against a running server, as the
median of several runs. Run the server in prod mode, so the numbers leave out the profiler:

```bash
$ APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
$ APP_ENV=prod APP_DEBUG=0 php -S 127.0.0.1:8002 -t public &
$ bin/benchmark http://127.0.0.1:8002 'rooms_many?view=timelineMonth'
```

On the demo data (PHP 8.4 with opcache, median of 5 runs, uncompressed HTML):

| Page | Page | Live "next" | HTML |
|---|---|---|---|
| `rooms` (day) | 23 ms | 21 ms | 102 KB |
| `team` (day, 41 people) | 34 ms | 30 ms | 278 KB |
| `rooms_many?view=timelineWeek` | 58 ms | 55 ms | 612 KB |
| `team?view=timelineWeek` | 85 ms | 80 ms | 1.0 MB |
| `rooms_many` (month, ~3,800 bookings) | 178 ms | 163 ms | 2.2 MB |

Tests
-----

```bash
$ vendor/bin/phpunit
```

[1]: ../calendar-bundle
[2]: https://getcomposer.org/
[3]: https://symfony.com/doc/current/setup.html#technical-requirements
