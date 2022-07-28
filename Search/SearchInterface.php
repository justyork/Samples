<?php

namespace App\Services\Search;

interface SearchInterface
{
    public function search();

    public function bind(string $name, $value): self;
}
