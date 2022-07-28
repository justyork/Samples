<?php

namespace Tests\Feature;

use App\Enums\TypeEnum;
use App\Models\Creative;
use App\Models\Language;
use App\Models\Pack;
use App\Models\Platform;
use App\Models\Size;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class FiltersTest extends TestCase
{
    use RefreshDatabase;


    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($user = User::factory()->create());
        Creative::factory()->count(10)->create();

        Size::first()->sources()->create([
            'name' => 'Test source'
        ]);

        Source::first()->platforms()->create([
            'name' => 'Test platform'
        ]);
    }

    public function test_size_equal_can_be_found(): void
    {

        $size = Size::first();
        $this->filterValue([[
            'field' => ['id' => 'size', 'text' => 'Size'],
            'sign' => '=',
            'value' => 1,
        ]], 'size.id', $size->id);

        $this->filterValue([[
            'field' => ['id' => 'size', 'text' => 'Size'],
            'sign' => '!=',
            'value' => 2,
        ]], 'size.id', $size->id);

        $this->filterValue([[
            'field' => ['id' => 'size', 'text' => 'Size'],
            'sign' => '=',
            'value' => $size->label,
        ]], 'size.id', $size->id);
    }

    public function test_size_in_can_be_found(): void
    {
        $size = Size::first();
        $this->filterValue([[
            'field' => ['id' => 'size', 'text' => 'Size'],
            'sign' => 'in',
            'value' => [1, 2],
        ]], 'size.id', $size->id);

        $this->filterValue([[
            'field' => ['id' => 'size', 'text' => 'Size'],
            'sign' => 'not in',
            'value' => [3, 2],
        ]], 'size.id', $size->id);
    }

    public function test_language_equal_can_be_found(): void
    {
        $language = Language::first();
        $this->filterValue([[
            'field' => ['id' => 'language', 'label' => 'Language'],
            'sign' => '=',
            'value' => 1,
        ]], 'language.id', $language->id);

        $this->filterValue([[
            'field' => ['id' => 'language', 'label' => 'Language'],
            'sign' => '!=',
            'value' => 2,
        ]], 'language.id', $language->id);

        $this->filterValue([[
            'field' => ['id' => 'language', 'label' => 'Language'],
            'sign' => '=',
            'value' => $language->code,
        ]], 'language.id', $language->id);

        $this->filterValue([[
            'field' => ['id' => 'language', 'label' => 'Language'],
            'sign' => '=',
            'value' => $language->name,
        ]], 'language.id', $language->id);
    }

    public function test_language_in_can_be_found(): void
    {
        $language = Language::first();
        $this->filterValue([[
            'field' => ['id' => 'language', 'label' => 'Language'],
            'sign' => 'in',
            'value' => [1, 2, 3],
        ]], 'language.id', $language->id);

        $this->filterValue([[
            'field' => ['id' => 'language', 'label' => 'Language'],
            'sign' => 'not in',
            'value' => [2, 3],
        ]], 'language.id', $language->id);
    }

    public function test_type_equal_can_be_found(): void
    {
        $this->filterValue([[
            'field' => ['id' => 'type', 'text' => 'Type'],
            'sign' => '=',
            'value' => TypeEnum::GP()->label,
        ]], 'type_id', TypeEnum::GP()->value);
    }

    public function test_source_equal_can_be_found(): void
    {
        $source = Source::first();
        $this->filterValue([[
            'field' => ['id' => 'source', 'text' => 'Source'],
            'sign' => '=',
            'value' => $source->id,
        ]], 'sources.0.id', $source->id);

        $this->filterValue([[
            'field' => ['id' => 'source', 'text' => 'Source'],
            'sign' => '=',
            'value' => $source->name,
        ]], 'sources.0.id', $source->id);

        $this->filterValue([[
            'field' => ['id' => 'source', 'text' => 'Source'],
            'sign' => '!=',
            'value' => 2,
        ]], 'sources.0.id', $source->id);
    }

    public function test_source_in_can_be_found(): void
    {
        $source = Source::first();
        $this->filterValue([[
            'field' => ['id' => 'source', 'text' => 'Source'],
            'sign' => 'in',
            'value' => [1, 2, 3],
        ]], 'sources.0.id', $source->id);

        $this->filterValue([[
            'field' => ['id' => 'source', 'text' => 'Source'],
            'sign' => 'not in',
            'value' => [2, 3],
        ]], 'sources.0.id', $source->id);
    }

    public function test_platform_equal_can_be_found(): void
    {
        $platform = Platform::first();
        $this->filterValue([[
            'field' => ['id' => 'platform', 'text' => 'Platform'],
            'sign' => '=',
            'value' => $platform->id,
        ]], 'platforms.0.id', $platform->id);

        $this->filterValue([[
            'field' => ['id' => 'platform', 'text' => 'Platform'],
            'sign' => '=',
            'value' => $platform->name,
        ]], 'platforms.0.id', $platform->id);

        $this->filterValue([[
            'field' => ['id' => 'platform', 'text' => 'Platform'],
            'sign' => '!=',
            'value' => 2,
        ]], 'platforms.0.id', $platform->id);

    }

    public function test_platform_in_can_be_found(): void
    {
        $platform = Platform::first();

        $this->filterValue([[
            'field' => ['id' => 'platform', 'text' => 'Platform'],
            'sign' => 'in',
            'value' => [1, 2, 3],
        ]], 'platforms.0.id', $platform->id);

        $this->filterValue([[
            'field' => ['id' => 'platform', 'text' => 'Platform'],
            'sign' => 'not in',
            'value' => [2, 3],
        ]], 'platforms.0.id', $platform->id);
    }

    public function test_find_by_name_can_be_found(): void
    {
        $pack = Pack::first();
        $packName = Str::studly($pack->name);
        $sizeName = Size::first()->label;
        $langCode = Str::upper(Language::first()->code);

        $this->filterText('GP-');
        $this->filterText("GP-$packName");
        $this->filterText("GP-$langCode");
        $this->filterText("GP-$sizeName");
        $this->filterText("GP-$packName-$sizeName");
        $this->filterText("GP-$packName-$sizeName-$langCode");
        $this->filterText("GP-$packName-$langCode");


        Creative::factory([
            'file_type' => 2,
            'duration' => 30
        ])->create();

        $this->filterText("GP-$packName-$sizeName-30sec-$langCode", 1);
        $this->filterText("GP-$packName-$sizeName-30sec", 1);
        $this->filterText("GP-$packName-30sec", 1);
        $this->filterText("GP-$packName-30sec-$langCode", 1);
        $this->filterText("GP-30sec", 1);
    }

    public function test_find_by_full_name_works(): void
    {
        $creative = Creative::first();

        $response = $this->postJson(route('api.creative.list'), ['filters' => [], 'text' => $creative->name, 'version' => 2]);

        $response->assertStatus(200)
            ->assertJson(fn(AssertableJson $json) =>
            $json->count('data', 1)
                ->where('data.0.id', $creative->id)
                ->etc()
            );
    }

    protected function filterValue($filters, $arg, $val = 1): void
    {
        $response = $this->postJson(route('api.creative.list'), ['filters' => $filters, 'text' => null, 'version' => 2]);

        $response->assertStatus(200)
            ->assertJson(fn(AssertableJson $json) =>
            $json->count('data', 10)
                ->where('data.0.'.$arg, $val)
                ->etc()
            );
    }

    protected function filterText($text, $count = 10): void
    {
        $response = $this->postJson(route('api.creative.list'), ['filters' => [], 'text' => $text, 'version' => 2]);

        $response->assertStatus(200)
            ->assertJson(fn(AssertableJson $json) =>
            $json->count('data', $count)
                ->etc()
            );
    }
}
