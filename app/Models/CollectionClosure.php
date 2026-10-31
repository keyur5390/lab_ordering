<?php

namespace App\Models;

use Database\Factories\CollectionClosureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A holiday or other closure: whole day(s), or part of one day. Applies to one service area,
 * or one branch (B-17), or everywhere when neither is set.
 *
 * @property int $id
 * @property int|null $service_area_id
 * @property int|null $branch_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $starts_at HH:MM[:SS], local time
 * @property string|null $ends_at HH:MM[:SS], local time
 * @property string|null $reason
 */
class CollectionClosure extends Model
{
    /** @use HasFactory<CollectionClosureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['service_area_id', 'branch_id', 'starts_on', 'ends_on', 'starts_at', 'ends_at', 'reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ServiceArea, $this>
     */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isWholeDay(): bool
    {
        return $this->starts_at === null;
    }
}
