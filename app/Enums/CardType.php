<?php

declare(strict_types=1);

namespace App\Enums;

enum CardType: string
{
    case Link = 'link';
    case Output = 'output';
    case Api = 'api';
}
