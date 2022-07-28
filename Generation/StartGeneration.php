<?php

namespace App\Jobs\Generation;

use App\Enums\GenerationStatus;
use App\Jobs\Generation\Download\CopySourceData;
use App\Models\DTO\FieldDTO;
use App\Models\Generation;
use App\Models\Language;
use App\Models\Template;
use App\Models\TemplateField;
use App\Models\TemplateItem;
use App\Models\TranslatedImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;

class StartGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected UuidInterface $uuid,
        protected int           $userId,
        protected Collection    $fields,
        protected Template      $template
    )
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->prepareFields();

        $model = new Generation([
            'uuid' => $this->uuid,
            'user_id' => $this->userId,
            'platform_id' => $this->template->platform_id,
            'template_id' => $this->template->id,
            'language_id' => Language::default()->id,
            'status' => GenerationStatus::INIT(),
            'source' => $this->fields,
        ]);

        $model->save();
        $model->progress(__('generation.progress.init'), 5);

        CopySourceData::dispatch($model)->onQueue(env('QUEUE_DOWNLOAD_SOURCE'));
    }

    protected function prepareFields()
    {
        $newFields = clone $this->fields;
        $this->fields = collect();

        $this->template->items->each(fn(TemplateItem $item) => $item->fields->each(function (TemplateField $templateField) use ($newFields) {
            /** @var FieldDTO $data */
            $data = $newFields->firstWhere('fieldId', $templateField->field_id);

            $folder = $data && $data->folder ? $data->folder :
                ($templateField->field->default_path ? ['path' => $templateField->field->default_path, 'name' => ''] : null);

            if ($templateField->params->imageName) {
                $translatedImage = TranslatedImage::find($templateField->params->imageName);
            }

            $this->fields->add(new FieldDTO(
                templateFieldId: $templateField->id,
                fieldId: $templateField->field_id,
                value: $data->value ?? $templateField->params->value ?? null,
                folder: $folder,
                tImage: isset($translatedImage) ? ['id' => $translatedImage->id, 'name' => $translatedImage->file_name] : null,
            ));
        }));
    }

}
