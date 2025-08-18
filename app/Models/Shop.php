<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_domain',
        'access_token',
        'installed_at'
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'installed_at' => 'datetime'
    ];

    /**
     * Get shop by domain
     */
    public static function findByDomain($domain)
    {
        return self::where('shop_domain', $domain)->first();
    }

    /**
     * Create or update shop
     */
    public static function createOrUpdateShop($domain, $accessToken)
    {
        return self::updateOrCreate(
            ['shop_domain' => $domain],
            [
                'access_token' => $accessToken,
                'installed_at' => now()
            ]
        );
    }

    /**
     * Check if shop exists
     */
    public static function shopExists($domain)
    {
        return self::where('shop_domain', $domain)->exists();
    }
}