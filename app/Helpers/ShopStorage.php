<?php

namespace App\Helpers;

class ShopStorage
{
    private static $dataPath = '/app/storage/shops.json';
    
    /**
     * Get access token for a specific shop
     */
    public static function get($shopDomain)
    {
        try {
            $data = self::readData();
            $shop = $data[$shopDomain] ?? null;
            
            if (!$shop) {
                return null;
            }
            
            // Decrypt the access token
            return decrypt($shop['access_token']);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Store or update shop access token
     */
    public static function set($shopDomain, $accessToken, $settings = null)
    {
        try {
            $data = self::readData();
            
            $data[$shopDomain] = [
                'access_token' => encrypt($accessToken),
                'settings' => $settings,
                'installed_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ];
            
            return self::writeData($data);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Delete shop from storage
     */
    public static function delete($shopDomain)
    {
        try {
            $data = self::readData();
            
            if (isset($data[$shopDomain])) {
                unset($data[$shopDomain]);
                return self::writeData($data);
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if shop exists
     */
    public static function exists($shopDomain)
    {
        try {
            $data = self::readData();
            return isset($data[$shopDomain]);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get all shops
     */
    public static function getAllShops()
    {
        try {
            $data = self::readData();
            $shops = [];
            
            foreach ($data as $domain => $shopData) {
                $shops[$domain] = decrypt($shopData['access_token']);
            }
            
            return $shops;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get shop data (for dashboard/admin purposes)
     */
    public static function getShop($shopDomain)
    {
        try {
            $data = self::readData();
            $shop = $data[$shopDomain] ?? null;
            
            if (!$shop) {
                return null;
            }
            
            // Return as object for compatibility with existing code
            return (object) [
                'shop_domain' => $shopDomain,
                'access_token' => decrypt($shop['access_token']),
                'settings' => $shop['settings'],
                'installed_at' => $shop['installed_at'],
                'updated_at' => $shop['updated_at'] ?? null
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get shop settings
     */
    public static function getSettings($shopDomain, $key = null, $default = null)
    {
        try {
            $data = self::readData();
            $shop = $data[$shopDomain] ?? null;
            
            if (!$shop) {
                return $default;
            }
            
            $settings = $shop['settings'] ?? [];
            
            if ($key === null) {
                return $settings;
            }
            
            return data_get($settings, $key, $default);
        } catch (\Exception $e) {
            return $default;
        }
    }
    
    /**
     * Update shop settings
     */
    public static function updateSettings($shopDomain, $key, $value = null)
    {
        try {
            $data = self::readData();
            
            if (!isset($data[$shopDomain])) {
                return false;
            }
            
            $settings = $data[$shopDomain]['settings'] ?? [];
            
            if (is_array($key)) {
                // Update multiple settings
                $settings = array_merge($settings, $key);
            } else {
                // Update single setting
                data_set($settings, $key, $value);
            }
            
            $data[$shopDomain]['settings'] = $settings;
            $data[$shopDomain]['updated_at'] = now()->toISOString();
            
            return self::writeData($data);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Read data from JSON file
     */
    private static function readData()
    {
        try {
            if (!file_exists(self::$dataPath)) {
                return [];
            }
            
            $content = file_get_contents(self::$dataPath);
            
            if ($content === false) {
                return [];
            }
            
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
            
            return $data ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Write data to JSON file
     */
    private static function writeData($data)
    {
        try {
            // Ensure directory exists
            $dir = dirname(self::$dataPath);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    return false;
                }
            }
            
            // Write to temporary file first, then move (atomic operation)
            $tempFile = self::$dataPath . '.tmp';
            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if ($jsonContent === false) {
                return false;
            }
            
            $bytesWritten = file_put_contents($tempFile, $jsonContent, LOCK_EX);
            
            if ($bytesWritten === false) {
                return false;
            }
            
            // Atomic move
            if (!rename($tempFile, self::$dataPath)) {
                unlink($tempFile); // Clean up
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get file info for debugging
     */
    public static function getFileInfo()
    {
        $info = [
            'path' => self::$dataPath,
            'exists' => file_exists(self::$dataPath),
            'readable' => is_readable(self::$dataPath),
            'writable' => is_writable(dirname(self::$dataPath)),
            'size' => file_exists(self::$dataPath) ? filesize(self::$dataPath) : 0,
            'modified' => file_exists(self::$dataPath) ? date('Y-m-d H:i:s', filemtime(self::$dataPath)) : null,
        ];
        
        if ($info['exists']) {
            $data = self::readData();
            $info['shop_count'] = count($data);
            $info['shops'] = array_keys($data);
        }
        
        return $info;
    }
}