<?php

namespace App\Models;

use Database\Factories\LabTestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single orderable lab test (PROJECT.md §3).
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $slug
 * @property string|null $description
 * @property string $sample_type
 * @property string|null $preparation
 * @property int $turnaround_hours
 * @property int $price_amount
 * @property bool $is_active
 */
class LabTest extends Model
{
    /** @use HasFactory<LabTestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'slug',
        'description',
        'sample_type',
        'preparation',
        'turnaround_hours',
        'price_amount',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'turnaround_hours' => 'integer',
            'price_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<LabPackage, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(LabPackage::class);
    }

    /**
     * @param  Builder<LabTest>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
