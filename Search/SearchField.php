<?php

namespace App\Services\Search;

use Spatie\DataTransferObject\DataTransferObject;

class SearchField extends DataTransferObject
{
    public SignEnum $sign;
    public string $label;
    public mixed $value;
    public array $field;

}
