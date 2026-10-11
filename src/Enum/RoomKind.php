<?php

namespace App\Enum;

enum RoomKind: string
{
    case Auditorium = 'auditorium';
    case MeetingRoom = 'meeting_room';
    case Desk = 'desk';
}
