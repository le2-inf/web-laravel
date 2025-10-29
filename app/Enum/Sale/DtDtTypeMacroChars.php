<?php

namespace App\Enum\Sale;

use App\Enum\EnumTrait;

enum DtDtTypeMacroChars: string
{
    use EnumTrait;
    case Opening = '{{';

    case Closing = '}}';
}
