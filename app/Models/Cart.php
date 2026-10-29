<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Managed through App\Services\CartService.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_token
 */
class Cart extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'guest_token'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
