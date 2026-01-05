<?php

namespace Xima\XimaTypo3Calendar\Domain\Model\Api;

enum DraftStatus: int
{
    case DRAFT = 0;
    case REVIEW = 1;
    case REJECTED = 2;
    case PUBLISHED = 3;
}
