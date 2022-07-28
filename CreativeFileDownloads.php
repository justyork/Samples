<?php

namespace App\Listeners;

use App\Events\FileDownloaded;
use App\Events\PreviewCreated;
use App\Helpers\File;
use App\Jobs\CreatePreview;
use App\Models\Creative;
use App\Models\Size;
use FFMpeg\FFMpeg;
use JetBrains\PhpStorm\ArrayShape;

class CreativeFileDownloads
{

    private Creative $creative;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\FileDownloaded  $event
     * @return void
     */
    public function handle(FileDownloaded $event)
    {
        $this->creative = $event->creative;

        $this->creative->file = $event->path;
        $props = $this->generateProps();
        $size = Size::whereDimension($props['width'], $props['height'])->first();

        \Log::debug("Download listener {$event->creative->id}, size: {$size->id}");

        if (!$size) {
            return;
        }

        $this->creative->duration = $props['duration'];
        $this->creative->size_id = $size->id;
        $this->creative->filesize = \Storage::disk('public')->size($this->creative->file);
        $this->creative->save();

        CreatePreview::dispatch($this->creative);
    }

    #[ArrayShape(['duration' => "mixed", 'width' => "int", 'height' => "int"])]
    private function generateProps(): array
    {
        if (File::isImage($this->creative->file)) {
            return $this->getImageFileProps();
        }

        return $this->getVideoFileProps();
    }

    #[ArrayShape(['duration' => "mixed", 'width' => "int", 'height' => "int"])]
    public function getImageFileProps(): array
    {
        $image = \Image::make(\Storage::disk('public')->path($this->creative->file));

        return [
            'width' => $image->width(),
            'height' => $image->height(),
            'duration' => 0,
        ];
    }

    #[ArrayShape(['duration' => "mixed", 'width' => "int", 'height' => "int"])]
    public function getVideoFileProps(): array
    {
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe'
        ]);

        $filePath = \Storage::disk('public')->path($this->creative->file);

        $video = $ffmpeg->open($filePath);

        $data = $video->getStreams()->videos()->first();

        $dimension = $data->getDimensions();
        $duration = $data->get('duration');

        return [
            'duration' => $duration,
            'width' => $dimension->getWidth(),
            'height' => $dimension->getHeight(),
        ];
    }

}
