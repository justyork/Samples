<?php

namespace App\Jobs\Generation;

use App\Contracts\ImageMerger;
use App\Enums\PackStatus;
use App\Events\PackUpdated;
use App\Models\DTO\PackSourceDTO;
use App\Models\Generation;
use App\Models\ImageSize;
use App\Models\Pack;
use App\Models\TemplateField;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Image;
use Ramsey\Uuid\Uuid;
use Storage;

class MakeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    protected FilesystemAdapter $storage;
    protected FilesystemAdapter $publicStorage;
    protected ImageMerger $imageMerger;
    protected string $generationPath;
    protected string $outputPath;
    protected ?string $tmpFolder = null;

    private int $generationIteration = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected Pack       $pack,
        protected ImageSize  $imageSize,
        protected Generation $generation
    )
    {
        $this->storage = Storage::disk();
        $this->publicStorage = Storage::disk('public');

        $this->outputPath = $this->pack->generation->folder . "/output";
        $this->generationPath = $this->pack->generation->folder . "/generations";

        $this->publicStorage->makeDirectory($this->outputPath);
        $this->storage->makeDirectory($this->generationPath);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ImageMerger $imageMerger)
    {
        $this->imageMerger = $imageMerger;

        $image = $this->pack->source
            ->map(fn(PackSourceDTO $source) => $this->prepare($this->getTemplateField($source->field_id), $source))
            ->filter()
            ->reduce(fn(?Image $prev, Image $image, $key) => $this->merge($prev, $image));

        $id = Uuid::uuid1();
        $path = "$this->outputPath/$id.jpg";

        $this->storage->deleteDirectory("tmp/$this->tmpFolder");
        try {
            $image->save($this->publicStorage->path($path));
        } catch (Exception $e) {
            dd($path, $image, $e);
        }

        $this->addMedia($path);
        $this->sendEvent($path);

        $this->pack->status = PackStatus::PROCESSING();
        $this->pack->save();
    }

    /**
     * @param int $id
     * @return TemplateField|null|HasMany
     */
    protected function getTemplateField(int $id): TemplateField|HasMany|null
    {
        return $this->pack->generation->template->items()->where('image_size_id', $this->imageSize->id)
            ->first()->fields()->where('field_id', $id)->first();
    }

    private function prepare(?TemplateField $templateField, PackSourceDTO $source): ?Image
    {
        if (!$templateField) {
            return null;
        }

        if (!$this->tmpFolder) {
            $this->tmpFolder = Uuid::uuid1();
            $this->storage->makeDirectory("tmp/$this->tmpFolder");
        }

        $folder = "{$this->pack->generation->folder}/source/{$templateField->field_id}/prepared/{$this->imageSize->id}";
        $this->storage->makeDirectory($folder);

        $image = $templateField->prepareImage($this->imageSize, $source, "tmp/$this->tmpFolder");

        return $image->save($this->storage->path("$folder/" . basename($source->path)));
    }

    private function merge(?Image $prev, Image $image): Image
    {
        $this->generationIteration++;
        $generationName = sprintf("%s_%s_step_%s.png", $this->pack->id, $this->imageSize->id, $this->generationIteration);

        if (!$prev) {
            return $image->save($this->storage->path("$this->generationPath/$generationName"));
        }

        return $this->imageMerger
            ->merge($prev, $image, $this->storage->path("$this->generationPath/$generationName"));
    }

    private function addMedia(string $path)
    {
        $this->pack->medias()->create([
            'uuid' => Uuid::uuid1(),
            'path' => pathinfo($path)['dirname'],
            'name' => basename($path),
            'image_size_id' => $this->imageSize->id,
            'language_id' => $this->pack->language_id,
            'width' => $this->imageSize->width,
            'height' => $this->imageSize->height,
            'size' => filesize($this->publicStorage->path($path)),
            'is_cover' => $this->generation->template->cover_image_size_id === $this->imageSize->id
        ]);
    }

    private function sendEvent(string $path)
    {
        $percent = 40 + (60 / 100) * ($this->batch() ? $this->batch()->progress() : 0);
        $this->generation->progress(__('generation.progress.media_ready', ['name' => basename($path)]), $percent);

        event(new PackUpdated(
            $this->generation->user_id,
            $this->generation->uuid,
            $this->pack->uuid,
            $this->generation->template->items()->count(),
            ['path' => $this->publicStorage->url($path)],
            $this->generation->template->cover_image_size_id === $this->imageSize->id
        ));
    }
}
