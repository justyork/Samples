<?php

namespace App\Services\Search;

use App\Enums\GraphicTypeEnum;
use App\Enums\TypeEnum;
use App\Models\Creative;
use App\Models\Language;
use App\Models\Pack;
use App\Models\Pipeline;
use App\Models\Platform;
use App\Models\Size;
use App\Models\Source;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SearchCreatives implements SearchInterface
{

    protected array $binds = [];
    protected array $usedFilters = [];

    public function __construct()
    { }

    public function bind(string $name, $value): self
    {
        $this->binds[$name] = $value;
        return $this;
    }

    public function search(): Builder|Creative
    {
        $query = Creative::active();

        if ($this->binds['text']) {
            $query = $query->whereText($this->binds['text']);
        }

        if ($this->binds['filter']) {
            /** @var Builder $query */
            $query = collect($this->binds['filter'])
                ->map(fn($el) => new SearchField(
                    sign: SignEnum::from($el['sign']),
                    value: $el['value'],
                    field: $el['field'],
                    label: $el['label'],
                ))
                ->reduce(fn($query, SearchField $el) => $this->applyFilter($query, $el), $query)
            ;
        }

        return $query;
    }

    protected function applyFilter(Builder $query, SearchField $field): Builder
    {
        $filterExists = in_array($field->field['id'], $this->usedFilters);
        if (!$filterExists) {
            $this->usedFilters[] = $field->field['id'];
        }

        if (!is_numeric($field->value) && !is_array($field->value)) {
            $field->value = $this->findIdByName($field);
        }

        $fieldName = match ($field->field['id']) {
            'language' => 'language_id',
            'pack' => 'pack_id',
            'pipeline' => 'pipeline_id',
            'format' => 'file_type',
            'import' => 'import_id',
            'type' => 'type',
            'graphic' => 'graphic_id',
            default => false
        };

        if (!$fieldName) {
            $args = [$field->value, $field->sign, $filterExists];

            match ($field->field['id']) {
                'size' => $query->whereSize(...$args),
                'tag' => $query->whereTag(...$args),
                'source' => $query->whereSource(...$args),
                'platform' => $query->wherePlatform(...$args),
                default => throw new \Exception('Unexpected match value')
            };

            return $query;
        }

        $whereFunc = $filterExists ? 'orWhere' : 'where';

        return match ($field->sign) {
            SignEnum::IN() => $query->{$whereFunc.'In'}($fieldName, $field->value),
            SignEnum::NOT_IN() => $query->{$whereFunc.'NotIn'}($fieldName, $field->value),
            default => $query->$whereFunc($fieldName, $field->sign, $field->value)
        };
    }

    protected function findIdByName(SearchField $field): int
    {
        return match ($field->field['id']) {
            'size' => Size::whereLabel($field->value)->first()->id,
            'language' => Language::whereName($field->value)->orWhere('code', $field->value)->first()->id,
            'import' => Creative::firstWhere('import_tag', $field->value)->import_id,
            'pipeline' => Pipeline::firstWhere('name', $field->value)->id,
            'pack' => Pack::firstWhere('name', $field->value)->id,
            'tag' => Tag::firstWhere('name', $field->value)->id,
            'source' => Source::firstWhere('name', $field->value)->id,
            'platform' => Platform::firstWhere('name', $field->value)->id,
            'type' => TypeEnum::from($field->value)->value,
            'graphic' => GraphicTypeEnum::getEnum($field->value)->value,
            default => throw new BadRequestHttpException("Can't find value")
        };
    }

}
