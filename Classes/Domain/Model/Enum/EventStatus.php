<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Enum;

enum EventStatus: int
{
    case DRAFT = 0;
    case REVIEW = 1;
    case LIVE = 2;
    case REJECTED = 3;
}
