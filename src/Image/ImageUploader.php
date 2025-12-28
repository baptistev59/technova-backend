<?php

declare(strict_types=1);

namespace App\Image;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageUploader
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public function upload(UploadedFile $file, ImageProfile $profile): string
    {
        [$width, $height, $type] = getimagesize($file->getPathname());
        if (!$width || !$height) {
            throw new \RuntimeException('Impossible de lire les dimensions de l’image.');
        }

        $ratioSrc = $width / $height;
        $ratioTarget = $profile->width / $profile->height;

        if ($ratioSrc > $ratioTarget) {
            $newWidth = $profile->width;
            $newHeight = (int) round($profile->width / $ratioSrc);
        } else {
            $newHeight = $profile->height;
            $newWidth = (int) round($profile->height * $ratioSrc);
        }

        $canvas = imagecreatetruecolor($profile->width, $profile->height);
        [$r, $g, $b] = sscanf($profile->background, '#%02x%02x%02x');
        $bg = imagecolorallocate($canvas, $r, $g, $b);
        imagefill($canvas, 0, 0, $bg);

        $src = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($file->getPathname()),
            IMAGETYPE_PNG => imagecreatefrompng($file->getPathname()),
            IMAGETYPE_WEBP => imagecreatefromwebp($file->getPathname()),
            default => throw new \RuntimeException('Format d’image non supporté.'),
        };

        imagecopyresampled(
            $canvas,
            $src,
            (int) (($profile->width - $newWidth) / 2),
            (int) (($profile->height - $newHeight) / 2),
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        $filename = sprintf('%s-%s.webp', $profile->name, uniqid());
        $absoluteDir = $this->projectDir.'/public/'.$profile->directory;
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }

        $absolutePath = $absoluteDir.'/'.$filename;
        imagewebp($canvas, $absolutePath, $profile->quality);

        imagedestroy($src);
        imagedestroy($canvas);

        return $profile->directory.'/'.$filename;
    }
}
