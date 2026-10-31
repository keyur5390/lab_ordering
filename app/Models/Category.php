<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A catalogue grouping such as Diabetes or Thyroid (client, 2026-10-31).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'slug'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<LabTest, $this>
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(LabTest::class);
    }

    /**
     * @return BelongsToMany<LabPackage, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(LabPackage::class);
    }
}
