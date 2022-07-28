<?php

namespace App\Jobs\Generation\Download;

use App\Models\DTO\FieldDTO;
use App\Models\Generation;
use App\Models\SourceFile;
use App\Models\TemplateField;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Ramsey\Uuid\Uuid;
use Storage;

class CopyFromGDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    protected TemplateField $templateField;
    protected FilesystemAdapter $storage;
    protected FilesystemAdapter $drive;

    public function __construct(
        protected Generation $generation,
        protected FieldDTO   $field
    )
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->storage = Storage::disk();
        $this->drive = Storage::cloud();

        $this->templateField = TemplateField::find($this->field->templateFieldId);

        $files = $this->drive->listContents($this->field->folder['path']);
        $this->downloadFilesFromFolder(
            $files,
            "{$this->generation->folder}/source/{$this->templateField->field_id}",
            true
        );
    }

    protected function downloadFilesFromFolder(array $files, string $destination, bool $downloadSubfolder = false): void
    {
        $hasDestination = $this->storage->has($destination);
        $hasDestination && $this->storage->makeDirectory($destination);

        try {
            $folderName = $this->drive->getMetadata($this->field->folder['path']);

            foreach ($files as $file) {
                if ($file['type'] !== 'dir' && in_array($file['extension'], ['png', 'jpg']) && !$hasDestination) {
                    $this->downloadFile($destination, $file);
                    $percent = 20 + (75 / 100) * ($this->batch() ? $this->batch()->progress() : 0);
                    $this->generation->progress(__('generation.progress.download', ['name' => "{$folderName['name']}/{$file['name']}"]), $percent);
                } elseif ($downloadSubfolder && $file['type'] === 'dir' && $file['name'] === $this->templateField->item->imageSize->path_name && !$this->storage->has("$destination/{$file['name']}")) {
                    $subFolder = Storage::cloud()->listContents($file['basename']);
                    $this->downloadFilesFromFolder($subFolder, "$destination/{$file['name']}");
                }
            }
        } catch (Exception $e) {
            Log::error($e->getMessage(), [$e]);
        }
    }

    protected function downloadFile(string $destination, array $file)
    {
        $sourceFile = SourceFile::firstWhere('cloud_name', $file['basename']);

        if ($sourceFile) {
            $this->storage->copy($sourceFile->path, "$destination/$sourceFile->name");
        } else {
            $f = $this->drive->get($file['path']);
            $this->storage->put("$destination/{$file['name']}", $f);

            $this->storage->makeDirectory('cloud');
            $name = Uuid::uuid1();

            $path = "cloud/$name.{$file['extension']}";
            $this->storage->put($path, $f);
            $sourceFile = SourceFile::create([
                'cloud_name' => $file['basename'],
                'name' => $file['name'],
                'path' => $path
            ]);
        }

        $sourceFile->save();
    }
}
