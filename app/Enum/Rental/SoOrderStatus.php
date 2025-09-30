<?php

namespace App\Enum\Rental;

use App\Enum\EnumLikeBase;

class SoOrderStatus extends EnumLikeBase
{
    public const string PENDING           = 'pending';            // 待签约
    public const string CANCELLED         = 'cancelled';          // 已作废
    public const string SIGNED            = 'signed';             // 已签约
    public const string COMPLETED         = 'completed';          // 已完成（正常归还）
    public const string EARLY_TERMINATION = 'early_termination';  // 提前解约

    public const array LABELS = [
        self::PENDING           => '待签约',
        self::CANCELLED         => '已作废',
        self::SIGNED            => '已签约',
        self::COMPLETED         => '已完成',
        self::EARLY_TERMINATION => '提前解约',
    ];

    public const getSignAndAfter
        = [self::SIGNED, self::COMPLETED, self::EARLY_TERMINATION];
}
