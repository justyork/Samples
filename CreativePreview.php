<?php

namespace App\Actions\Preview;

use App\Models\Creative;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;

class CreativePreview
{
    public static function video(Creative $creative) : void
    {
        if ($creative->preview) {
            \Storage::disk('public')->delete($creative->preview);
        }

        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe'
        ]);

        $filePath = \Storage::disk('public')->path($creative->file);

        \Log::debug("[PREVIEW] Create preview from $filePath");

        $video = $ffmpeg->open($filePath);

        $path = self::getPreviewPath($creative);
        \Log::debug("[PREVIEW] Creating video preview with path: $path");

        $tmpPath = \Storage::path('tmp/'.basename($path));

        $video->frame(TimeCode::fromSeconds(1))->save($tmpPath);

        $image = \Image::make($tmpPath);

        $size = config('preview.previewSize');
        [$w, $h] = self::getTrueSize($size, $size, $image->width(), $image->height());
        $image->resize($w, $h)->save(\Storage::disk('public')->path($path));

        $creative->preview = $path;
        $creative->save();

    }

    public static function image(Creative $creative) : void
    {
        if ($creative->preview) {
            \Storage::disk('public')->delete($creative->preview);
        }

        $path = self::getPreviewPath($creative);
        \Log::debug("[PREVIEW] Creating image preview with path: $path");

        $file = \Image::make(\Storage::disk('public')->path($creative->file));

        $size = config('preview.previewSize');
        $file->fit($size, $size);

        $file->save(\Storage::disk('public')->path($path));

        $creative->preview = $path;
        $creative->save();
    }

    protected static function getTrueSize($width, $height, $originWidth, $originHeight): array
    {
        if ($originWidth > $originHeight) {
            $a = $originWidth / ($originHeight / $height);
            $b = $height;
        } else {
            $a = $width;
            $b = $originHeight / ($originWidth / $width);
        }

        return [$a, $b];
    }

    public static function getPreviewPath(Creative $creative): string
    {
        $ext = pathinfo($creative->file, PATHINFO_EXTENSION);
        if ($ext === 'mp4') {
            $ext = 'jpg';
        }

        $filename = \Str::of(basename($creative->file))->before('.')->append(".$ext");

        return \Str::of(config('preview.previewPath'))->replace([
            '{pack_id}',
            '{name}'
        ], [
            $creative->pack_id,
            $filename
        ]);
    }
}
