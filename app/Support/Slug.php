<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Slug
{
    /**
     * A URL slug for $name that is unique in the model's table ("cbc", "cbc-2", ...).
     *
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;

        for ($i = 2; $model::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
