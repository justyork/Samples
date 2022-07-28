<?php

namespace App\Jobs\Generation\Download;

use App\Models\DTO\FieldDTO;
use App\Models\Generation;
use App\Models\TranslatedImage;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Storage;

class CopyTranslatedImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(protected Generation $generation, protected FieldDTO $field)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $translatedImage = TranslatedImage::find($this->field->tImage['id']);
        $path = $translatedImage->src;

        if (Storage::disk('public')->exists($path)) {
            Storage::writeStream("{$this->generation->folder}/source/{$this->field->fieldId}/{$translatedImage->file_name}", Storage::disk('public')->readStream($path));
        }
        $this->generation->progress(__('generation.progress.translated_images'), 15);
    }
}
