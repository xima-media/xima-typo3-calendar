<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

enum EventAppointmentType: string
{
    case IN_PERSON = 'inPerson';
    case ONLINE = 'online';
    case HYBRID = 'hybrid';
}
