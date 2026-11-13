<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A page of site copy written by admins in Markdown (B-27: privacy policy and terms).
 */
class Page extends Model
{
    protected $fillable = ['title', 'body'];

    /** The body as HTML. Raw HTML in the Markdown is stripped and unsafe links are dropped. */
    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
