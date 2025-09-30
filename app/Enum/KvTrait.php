<?php

namespace App\Enum;

trait KvTrait
{
    public static function options(): array
    {
        return
            [
                preg_replace('/^.*\\\/', '', get_called_class()).'Options' => array_map(
                    fn ($k, $v) => ['value' => $k, 'text' => $v],
                    array_keys(static::kv),
                    static::kv
                ),
            ];
    }

    public static function kv(): array
    {
        return [preg_replace('/^.*\\\/', '', get_called_class()).'kv' => self::kv];
    }
}
