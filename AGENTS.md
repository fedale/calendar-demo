# AGENTS.md

## This project

A demo application for [fedale/calendar-bundle](../calendar-bundle), a server-rendered calendar
component for Symfony UX. It has no database: the demo sources generate their events, so every page
works right after `composer install`.

**Requirements:** PHP 8.4+ with `ext-intl`, Symfony 8.x.

### Layout

- `src/Calendar/Source/` - the event sources (`#[AsCalendarSource]`), one per demo domain: shifts,
  seats, lessons, rooms (`RoomSource` also provides the timeline's resources), plus `StressSource`
  and `ManyRoomsSource` for load tests. `DemoDays` seeds the random generator by
  source and date, so a day always yields the same events whatever range is requested; keep it that
  way, or navigation shows different data for the same day.
- `src/Calendar/Booking/BookingStore.php` - the demo's "database": changes to the generated
  bookings, per source, kept in the session. `RoomSource` and `SeatSource` are also the intent
  handlers (`IntentHandlerInterface`) and hold the demo's domain rules (no overlapping bookings in a
  room, one person per desk per day)
- `src/Calendar/Definition/` - the calendars (`#[AsCalendar]`) and their `viewConfig()`
- `src/Controller/CalendarController.php` - the pages; `CALENDARS` lists the demo calendars and what
  each one demonstrates
- `src/EventSubscriber/LocaleSubscriber.php` - `?_locale=en|it`, kept in the session so Live
  Component requests use it too
- `config/packages/fedale_calendar.yaml` - bundle configuration
- `importmap.php` - includes the bundle stylesheet (`@fedale/calendar-bundle/styles/calendar.css`)
- `assets/controllers.json` - enables the bundle's Stimulus controllers (`cal-now`, `cal-button`)
- `bin/benchmark` - measures a page and a Live navigation against a running server (see README)
- `assets/app.js` - handles the `demo:add-event` DOM event of the rooms calendar's custom button

### Working with calendar-bundle

- The bundle is loaded from a symlinked path repository (`../calendar-bundle`); both repositories
  must sit in the same parent directory.
- Fix bundle bugs in `../calendar-bundle`, not in `vendor/`; keep demo-specific code here. The
  bundle has its own guidelines in `../calendar-bundle/AGENTS.md`.
- A new demo calendar is a source, a definition and an entry in `CalendarController::CALENDARS`.
- The stress calendar's volume is `CALENDAR_STRESS_PER_DAY` (timed events per day, default 16, about
  500 a month).

### Before reporting a change as done

- `php bin/console lint:container` and `php bin/console lint:twig templates/`
- `vendor/bin/phpunit` (the smoke test opens every demo page)

## Symfony guidelines

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran. Don't assume Doctrine,
Twig, API Platform, Messenger, or Lock are installed unless one of those says so.

## Ask before generating

If the task doesn't specify, ask rather than guess:

- Persistence: Doctrine ORM, Doctrine ODM, or none?
- Interface: server-rendered (Twig), API (Serializer, maybe API Platform), or both?
- Auth: SecurityBundle, and which authenticator?

If you can't ask (no interactive channel), state the assumption you're making and
pick the smallest option (e.g. no persistence layer) rather than scaffolding a
full stack nobody asked for.

## Adding features: Flex, not hand-wiring

Install new capabilities with `composer require <package>` (e.g. `symfony/lock`,
`symfony/messenger`, `orm-pack`) and let the Flex recipe register the bundle and
generate its config. Don't hand-edit `config/bundles.php` or hand-write a bundle's
base config; that's what the recipe is for. Don't skip a good-fit component just
because it isn't installed yet; installing it is one command.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, `composer require` them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
  Don't mark a service `readonly` if it might become `lazy: true`: a lazy proxy
  can't extend a `readonly` class.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

## Everyday workflow

- Run the app with `symfony serve -d`, and commands with `symfony console ...`
  (or `bin/console` when the Symfony CLI isn't available).
- When something fails, read `var/log/dev.log` and the web profiler
  (`/_profiler`) before changing code.
- If `maker-bundle` is installed, prefer `bin/console make:*` with every argument
  passed up front and `--no-interaction` where supported: makers prompt on a
  terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- If Doctrine ORM is installed, schema changes go through migrations
  (`bin/console make:migration`, then `doctrine:migrations:migrate`), never
  `doctrine:schema:update` or hand-written SQL.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Testing

Install `symfony/test-pack` if it isn't already. Functional/HTTP tests extend
`WebTestCase`; service-level tests extend `KernelTestCase`. Run
`php bin/phpunit` (falls back to `vendor/bin/phpunit`). A feature isn't done
until it has a test that exercises it the way a caller would, an HTTP request for
a controller or a service call for a service, not just "it didn't throw."

## Code style

Symfony's coding standard, the `@Symfony` php-cs-fixer ruleset (a PSR-12-derived
superset). Run `vendor/bin/php-cs-fixer fix` if `friendsofphp/php-cs-fixer` is
installed; it isn't part of the skeleton by default.

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `bin/console lint:container`, plus `lint:twig templates/` and
  `lint:yaml config/` where those packages are installed: validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).
