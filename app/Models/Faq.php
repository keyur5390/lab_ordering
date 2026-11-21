<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A question and answer on the home and FAQ pages (B-28), in the order admins set.
 */
class Faq extends Model
{
    protected $fillable = ['question', 'answer', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @param  Builder<Faq>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /** The answer as HTML, made the same safe way as pages. */
    public function html(): string
    {
        return Str::markdown($this->answer, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
