<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoostPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'boost_count',
        'boost_duration',
        'platform',
        'product_id',
        'status',
        'position'
    ];

    protected $casts = [
        'boost_count' => 'integer',
        'boost_duration' => 'integer',
        'status' => 'boolean',
        'position' => 'integer'
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeForPlatform($query, $platform)
    {
        return $query->where(function($q) use ($platform) {
            $q->where('platform', $platform)
              ->orWhere('platform', 'both');
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position', 'ASC');
    }

    public static function getPackagesForApi($platform = null)
    {
        $query = self::active()->ordered();
        
        if ($platform) {
            $query->forPlatform($platform);
        }
        
        return $query->get()->map(function($package) {
            return [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'boost_count' => $package->boost_count,
                'boost_duration' => $package->boost_duration,
                'product_id' => $package->product_id,
                'platform' => $package->platform
            ];
        });
    }

    public static function findByProductId($productId)
    {
        return self::where('product_id', $productId)->first();
    }
}
