<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $service_area_id
 * @property string $postcode Normalised, see App\Support\Countries::normalizePostcode().
 */
class ServiceAreaPostcode extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'postcode';

    /**
     * @var list<string>
     */
    protected $fillable = ['postcode'];
}
