<?php

namespace Tests\Feature;

use App\Helpers\GDrive;
use App\Imports\CreativesImport;
use App\Models\Import;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Database\Seeders\ImportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportTest extends TestCase
{

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthSeeder::class);
        $this->actingAs($user = User::factory()->create());
        $user->assignRole(Role::findByName('admin'));

        $this->seed(ImportSeeder::class);
    }

    public function test_import_page_can_be_opened()
    {
        $response = $this->get(route('import'));

        $response->assertStatus(200);
    }

    public function test_import_file_is_upload_correct()
    {
        \Storage::fake();
        $filePath = public_path('data/ImportTest.xlsx');

        $import = Import::create();
        (new CreativesImport($import))->import($filePath);

        $this->assertModelExists($import);
        $this->assertEquals(4, $import->rows->count(), "Not correct count");
    }

    public function test_valid_count_packs(): void
    {
        $items = Import::find(ImportSeeder::GOOD_ID)->new_items;

        $this->assertCount(2, $items['packs']);
        $this->assertEquals(Import::VALID_OK, $items['creatives'][0]['valid']['pack']);
        $this->assertEquals(Import::VALID_NEW, $items['creatives'][1]['valid']['pack']);
    }

    public function test_valid_count_creatives(): void
    {
        $this->assertCount(2, Import::find(ImportSeeder::GOOD_ID)->new_items['creatives']);
    }

    public function test_errors_catches(): void
    {
        $items = (Import::find(ImportSeeder::BAD_ID))->new_items;

        $this->assertCount(4, $items['errors'][ImportSeeder::BAD_CREATIVE_ID], "Incorrect errors count");
        $this->assertArrayHasKey('size', $items['errors'][ImportSeeder::BAD_CREATIVE_ID]);
        $this->assertArrayHasKey('file', $items['errors'][ImportSeeder::BAD_CREATIVE_ID]);
        $this->assertArrayHasKey('type', $items['errors'][ImportSeeder::BAD_CREATIVE_ID]);
        $this->assertArrayHasKey('graphic', $items['errors'][ImportSeeder::BAD_CREATIVE_ID]);
    }
}
