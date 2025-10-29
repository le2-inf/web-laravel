<?php

namespace App\Enum\Sale;

class SoRentalType_Short extends SoRentalType
{
    public const array LABELS = [
        self::LONG_TERM  => '长租',
        self::SHORT_TERM => '短租',
    ];
}
