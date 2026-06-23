<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get a setting by key and cast it.
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        switch ($setting->type) {
            case 'boolean':
            case 'bool':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
            case 'array':
                return json_decode($setting->value, true);
            case 'integer':
            case 'int':
                return (int) $setting->value;
            default:
                return $setting->value;
        }
    }

    /**
     * Set/update a setting by key.
     */
    public static function set(string $key, $value, string $type = 'string', ?string $description = null)
    {
        $valueStr = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $valueStr,
                'type' => $type,
                'description' => $description
            ]
        );
    }
}
