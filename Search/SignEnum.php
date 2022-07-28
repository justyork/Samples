<?php

namespace App\Services\Search;

use Spatie\Enum\Enum;

/**
 *
 * @method static self EQUAL()
 * @method static self NOT_EQUAL()
 * @method static self IN()
 * @method static self NOT_IN()
 */
final class SignEnum extends Enum
{
    protected static function values()
    {
        return [
            'EQUAL' => '=',
            'NOT_EQUAL' => '!=',
            'IN' => 'in',
            'NOT_IN' => 'not in',
        ];
    }
}
