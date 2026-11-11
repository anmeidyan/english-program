<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/** PRD 7.3: images are converted to webp; stored on the configured disk (public / MinIO). */
class MediaService
{
    public function storeImageAsWebp(UploadedFile $file, string $directory): string
    {
        $path = trim($directory, '/').'/'.Str::random(32).'.webp';
        $encoded = Image::read($file->getRealPath())->scaleDown(width: 1600)->toWebp(quality: 80);
        Storage::disk(config('englishpath.media.disk'))->put($path, (string) $encoded, 'public');

        return $path;
    }
}
