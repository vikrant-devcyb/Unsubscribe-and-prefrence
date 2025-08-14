<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class ShopStorage
{
    protected static $file = 'private/shops.json';

    public static function getAllShops()
    {
        if (!Storage::exists(self::$file)) {
            return [];
        }

        $json = Storage::get(self::$file);
        return json_decode($json, true) ?? [];
    }

    public static function get($shopDomain)
    {
        $shops = self::getAllShops();
        return $shops[$shopDomain] ?? null;
    }

    public static function set($shopDomain, $accessToken)
    {
        $shops = self::getAllShops();
        $shops[$shopDomain] = encrypt($accessToken); // Encrypt for security
        Storage::put(self::$file, json_encode($shops, JSON_PRETTY_PRINT));
    }

    public static function delete($shopDomain)
    {
        $shops = self::getAllShops();
        unset($shops[$shopDomain]);
        Storage::put(self::$file, json_encode($shops, JSON_PRETTY_PRINT));
    }

    public static function decryptToken($encryptedToken)
    {
        try {
            return decrypt($encryptedToken);
        } catch (\Exception $e) {
            return null;
        }
    }
}
