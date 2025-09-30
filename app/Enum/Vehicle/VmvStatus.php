<?php

namespace App\Enum\Vehicle;

use App\Enum\EnumLikeBase;

class VmvStatus extends EnumLikeBase
{
    public const int UNPROCESSED = 0;

    public const int PROCESSED = 1;

    public const array LABELS = [
        self::UNPROCESSED => '未处理',
        self::PROCESSED   => '已处理',
    ];
}
