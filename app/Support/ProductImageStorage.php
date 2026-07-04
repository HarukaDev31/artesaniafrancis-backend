<?php

namespace App\Support;

use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductImageStorage
{
    public const DIRECTORY = 'catalogo/productos';

    public static function fileName(TemporaryUploadedFile $file, ?string $slug = null, ?string $name = null): string
    {
        $base = Str::slug($slug ?: $name ?: 'producto');
        $timestamp = now()->format('Ymd-His');
        $extension = strtolower(
            $file->getClientOriginalExtension()
            ?: $file->extension()
            ?: 'jpg'
        );

        return "{$base}-{$timestamp}.{$extension}";
    }
}
