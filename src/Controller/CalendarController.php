<?php

namespace App\Controller;

use Fedale\CalendarBundle\Definition\CalendarRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CalendarController extends AbstractController
{
    /** Calendar id => what the page demonstrates. */
    public const CALENDARS = [
        'shifts' => 'Night shifts cross midnight but stay on the day they start (nextDayThreshold), holidays are background bands, week numbers are on.',
        'seats' => 'All-day desk bookings. Other people\'s desks are "Busy": the source withholds their names. Drag your own (green) bookings to another day or press Delete to cancel them; a taken desk or a closed day is refused. Weekends are hidden.',
        'lessons' => 'A weekly timetable expanded by the source; the list view is the default, one week per page.',
        'overview' => 'Three unrelated sources on one calendar.',
        'rooms' => 'A resource timeline you can edit: drag a booking to move it (also to another room), drag its end to resize it, drag over empty time to book, Delete to cancel. Overlapping bookings in a room are refused by the source. Changes live in your session.',
        'team' => 'People\'s agendas from the database: an appointment shows on every participant\'s row. Drop it on another row to swap that participant; a meeting held in a booked room moves with its booking, if the room is free.',
        'stress' => 'About 500 events a month, to measure rendering cost.',
        'rooms_many' => 'Every room of the database (54, about 8,000 bookings over three months), to measure rendering cost.',
    ];

    public function __construct(private readonly CalendarRegistry $calendars)
    {
    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->render('calendar/home.html.twig', ['calendars' => self::CALENDARS]);
    }

    #[Route('/calendar/{id}', name: 'calendar')]
    public function show(string $id): Response
    {
        if (!isset(self::CALENDARS[$id]) || !$this->calendars->has($id)) {
            throw new NotFoundHttpException(sprintf('No demo calendar "%s".', $id));
        }

        return $this->render('calendar/show.html.twig', [
            'id' => $id,
            'label' => $this->calendars->get($id)->label(),
            'description' => self::CALENDARS[$id],
            'calendars' => self::CALENDARS,
        ]);
    }
}
