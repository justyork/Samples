<?php

namespace App\Jobs\Generation;

use App\Enums\GenerationBatchStatus;
use App\Enums\GenerationStatus;
use App\Enums\PackStatus;
use App\Events\MergingStarted;
use App\Events\PacksInitialised;
use App\Models\DTO\FieldDTO;
use App\Models\DTO\PackSourceDTO;
use App\Models\Generation;
use App\Models\GenerationBatch;
use App\Models\Pack;
use App\Models\Template;
use App\Models\TemplateItem;
use Bus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;
use Storage;
use Throwable;

class CombineFolders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected FilesystemAdapter $storage;
    protected Collection $combines;
    protected array $jobs = [];

    /**
     * @param Generation $generation
     * @param Template $template
     * @param Collection $fields
     */
    public function __construct(protected Generation $generation)
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Throwable
     */
    public function handle()
    {
        $this->storage = Storage::disk();
        $this->generation->setStatus(GenerationStatus::COMBINING());
        $this->generation->progress(__('generation.progress.randomize'), 30);

        $packUuidList = [];
        $variants = $this->generateVariants();

        $variants->each(function ($comb, int $index) use (&$packUuidList, $variants) {
            $uuid = Uuid::uuid1();
            $packUuidList[] = $uuid;

            $pack = Pack::create([
                'uuid' => $uuid,
                'generation_id' => $this->generation->id,
                'language_id' => $this->language_id ?? $this->generation->language_id,
                'parent_id' => null,
                'status' => PackStatus::INIT(),
                'source' => $comb
            ]);
            $pack->save();

            $this->generation->template->items
                ->each(fn(TemplateItem $item) => $this->jobs[] = new MakeImage($pack, $item->imageSize, $this->generation));
        });

        event(new PacksInitialised($this->generation->user_id, $this->generation->uuid, $packUuidList));

        $this->generation->setStatus(GenerationStatus::MERGING());
        $this->generation->progress(__('generation.progress.start_merging'), 40);

        $batch = Bus::batch($this->jobs)
            ->name('merge-images')
            ->allowFailures()
            ->onQueue(env('QUEUE_GENERATE_IMAGE'))
            ->dispatch();

        $generationBatch = GenerationBatch::firstOrCreate(['generation_id' => $this->generation->id]);
        $generationBatch->merge_batch_id = $batch->id;
        $generationBatch->merge_status = GenerationBatchStatus::PROCESS();
        $generationBatch->save();

        event(new MergingStarted($this->generation->user_id, $this->generation->uuid, $batch->id));
    }

    /** Combine images
     *
     * @return Collection
     */
    protected function generateVariants(): Collection
    {
        $baseFolder = $this->generation->folder;

        $collection = $this->generation->source
            ->groupBy(fn(FieldDTO $field) => $field->fieldId)
            ->map(fn(Collection $fields) => $fields->first());

        $folderCollection = $collection
            ->filter(fn(FieldDTO $field) => $field->folder)
            ->map(fn(FieldDTO $field) => collect($this->storage->files("$baseFolder/source/$field->fieldId"))
                ->map(fn($el) => ['path' => $el, 'field_id' => $field->fieldId, 'value' => $field->value])
            );

        $combine = collect($folderCollection->first())->crossJoin(...$folderCollection->slice(1)->all());

        $combinations = $combine->count() < $this->generation->count ?
            $combine :
            $combine->random($this->generation->count);

        return $combinations->map(function ($arr) use ($collection) {
            $combination = collect($arr);

            return $collection->map(function(FieldDTO $field) use ($combination) {
                $item = $combination->firstWhere('field_id', $field->fieldId);

                if ($field->tImage) {
                    $translatedPath = "{$this->generation->folder}/source/$field->fieldId/{$field->tImage['name']}";
                }

                return new PackSourceDTO(
                    field_id: $field->fieldId,
                    value: $field->value,
                    path: $item['path'] ?? $translatedPath ?? null
                );
            });

        });
    }

}
