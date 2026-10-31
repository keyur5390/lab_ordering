<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One test or one package in a cart. Prices are not stored here: they are read live from the catalogue.
 *
 * @property int $id
 * @property int $cart_id
 * @property int|null $lab_test_id
 * @property int|null $lab_package_id
 * @property-read LabTest|null $test
 * @property-read LabPackage|null $package
 */
class CartItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['lab_test_id', 'lab_package_id'];

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<LabTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    /**
     * @return BelongsTo<LabPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(LabPackage::class, 'lab_package_id');
    }
}
