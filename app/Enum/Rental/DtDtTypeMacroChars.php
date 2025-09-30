<?php

namespace App\Enum\Rental;

use App\Enum\EnumTrait;

enum DtDtTypeMacroChars: string
{
    use EnumTrait;
    case Opening = '{{';

    case Closing = '}}';
}
