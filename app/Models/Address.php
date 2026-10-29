<?php

namespace App\Models;

use App\Support\Countries;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's address for home collection.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $label
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $region
 * @property string $postcode
 * @property string $postcode_normalized
 * @property string $country
 * @property float|null $latitude
 * @property float|null $longitude
 */
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    /**
     * `user_id` is set through the user's relationship, never from input.
     *
     * @var list<string>
     */
    protected $fillable = ['label', 'line1', 'line2', 'city', 'region', 'postcode', 'country', 'latitude', 'longitude'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Keeps postcode_normalized in step with postcode.
     *
     * @return Attribute<string, string>
     */
    protected function postcode(): Attribute
    {
        return Attribute::make(set: fn (string $value) => [
            'postcode' => strtoupper(trim($value)),
            'postcode_normalized' => Countries::normalizePostcode($value),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
