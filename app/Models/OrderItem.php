<?php

namespace App\Models;

use App\Enums\CartItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One test or package for one patient, as booked.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $patient_id
 * @property string $patient_name
 * @property Carbon $patient_date_of_birth
 * @property string $patient_sex
 * @property CartItemType $item_type
 * @property int|null $lab_test_id
 * @property int|null $lab_package_id
 * @property string $name
 * @property string $code
 * @property list<string>|null $test_names
 * @property int $price_amount
 */
class OrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'order_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_date_of_birth' => 'date',
            'item_type' => CartItemType::class,
            'test_names' => 'array',
            'price_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
