<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

/** PRD 7.3: images are converted to webp; stored on the configured disk (public / MinIO). */
class MediaService
{
    public function storeImageAsWebp(UploadedFile $file, string $directory): string
    {
        $path = trim($directory, '/').'/'.Str::random(32).'.webp';
        $encoded = Image::decodePath((string) $file->getRealPath())->scaleDown(width: 1600)->encodeUsingFormat(Format::WEBP, quality: 80);
        Storage::disk(config('englishpath.media.disk'))->put($path, (string) $encoded, 'public');

        return $path;
    }
}
