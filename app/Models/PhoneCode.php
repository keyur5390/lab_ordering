<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code texted to a phone (B-30), stored hashed. Managed by App\Services\PhoneCodeService.
 */
class PhoneCode extends Model
{
    protected $fillable = ['phone', 'purpose', 'user_id', 'code', 'attempts', 'expires_at'];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'attempts' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
