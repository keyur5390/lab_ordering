<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
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
    use Prunable;

    /** Guest carts nobody has changed for this long are deleted (client, 2026-11-13). */
    public const GUEST_CART_DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'guest_token'];

    /**
     * Guest carts idle for GUEST_CART_DAYS (run daily by `model:prune`). Customers' carts stay.
     *
     * @return Builder<Cart>
     */
    public function prunable(): Builder
    {
        return static::query()->whereNull('user_id')->where('updated_at', '<', now()->subDays(self::GUEST_CART_DAYS));
    }

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
