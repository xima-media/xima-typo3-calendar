<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

enum EventStatus: int
{
    case DRAFT = 0;
    case LIVE = 1;
    case REJECTED = 2;
}
