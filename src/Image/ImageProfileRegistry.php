<?php

declare(strict_types=1);

namespace App\Image;

final class ImageProfileRegistry
{
    /**
     * @return array<string, ImageProfile>
     */
    public static function all(): array
    {
        return [
            'shop_banner' => new ImageProfile(
                name: 'shop_banner',
                width: 1920,
                height: 1080,
                minWidth: 1200,
                minHeight: 675,
                maxWidth: 2560,
                maxHeight: 1440,
                quality: 85,
                directory: 'uploads/shops'
            ),
            'shop_logo' => new ImageProfile(
                name: 'shop_logo',
                width: 400,
                height: 400,
                minWidth: 200,
                minHeight: 200,
                maxWidth: 1000,
                maxHeight: 1000,
                quality: 90,
                directory: 'uploads/shops'
            ),
            'product_image' => new ImageProfile(
                name: 'product_image',
                width: 1200,
                height: 1200,
                minWidth: 600,
                minHeight: 600,
                maxWidth: 2000,
                maxHeight: 2000,
                quality: 85,
                directory: 'uploads/products'
            ),
            'avatar' => new ImageProfile(
                name: 'avatar',
                width: 400,
                height: 400,
                minWidth: 200,
                minHeight: 200,
                maxWidth: 1000,
                maxHeight: 1000,
                quality: 90,
                directory: 'uploads/avatars'
            ),
        ];
    }

    public static function get(string $key): ImageProfile
    {
        return self::all()[$key]
            ?? throw new \InvalidArgumentException("Image profile '{$key}' not found");
    }
}
