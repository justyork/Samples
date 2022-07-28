<?php

namespace App\Jobs\Generation\Download;

use App\Enums\GenerationBatchStatus;
use App\Events\SourceDownloadingStarted;
use App\Models\DTO\FieldDTO;
use App\Models\Generation;
use App\Models\GenerationBatch;
use Bus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CopySourceData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(protected Generation $generation)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Throwable
     */
    public function handle()
    {
        $this->generation->progress(__('generation.progress.start_download'), 10);

        if ($item = $this->generation->source->first(fn(FieldDTO $field) => $field->tImage)) {
            dispatch(new CopyTranslatedImages($this->generation, $item))->onQueue(env('QUEUE_DOWNLOAD_SOURCE'));
        }

        $folderCollection = $this->generation->source
            ->filter(fn(FieldDTO $field) => $field->folder);

        $downloadFolders = $folderCollection->map(fn(FieldDTO $field, int $key) => new CopyFromGDrive($this->generation, $field));

        $batch = Bus::batch($downloadFolders)
            ->name('download-source-from-cloud')
            ->allowFailures()
            ->onQueue(env('QUEUE_DOWNLOAD_SOURCE'))
            ->dispatch();

        $generationBatch = GenerationBatch::firstOrCreate(['generation_id' => $this->generation->id]);
        $generationBatch->download_batch_id = $batch->id;
        $generationBatch->download_status = GenerationBatchStatus::PROCESS();
        $generationBatch->save();

        event(new SourceDownloadingStarted($this->generation->user_id, $this->generation->uuid, $batch->id));
    }
}
