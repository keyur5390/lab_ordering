<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A page of site copy written by admins in Markdown: privacy policy and terms (B-27), about and
 * contact (B-28).
 */
class Page extends Model
{
    /** The pages there are; admins edit them but don't add or remove any. */
    public const SLUGS = ['about', 'contact', 'privacy', 'terms'];

    protected $fillable = ['title', 'body'];

    /** The body as HTML. Raw HTML in the Markdown is stripped and unsafe links are dropped. */
    public function html(): string
    {
        return Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
