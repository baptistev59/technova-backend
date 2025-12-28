<?php

declare(strict_types=1);

namespace App\Image;

final class ImageProfile
{
    public function __construct(
        public readonly string $name,
        public readonly int $width,
        public readonly int $height,
        public readonly int $minWidth,
        public readonly int $minHeight,
        public readonly int $maxWidth,
        public readonly int $maxHeight,
        public readonly int $quality,
        public readonly string $directory,
        public readonly bool $allowUpscale = true,
        public readonly string $background = '#f1f5f9',
    ) {
    }
}
