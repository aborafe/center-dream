<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CenterSetting extends Model
{
    protected $fillable = [
        'center_name',
        'center_phone',
        'address',
        'currency',
        'balance_alerts',
        'daily_summary',
        'daily_report_copy',
    ];

    protected function casts(): array
    {
        return [
            'balance_alerts' => 'boolean',
            'daily_summary' => 'boolean',
            'daily_report_copy' => 'boolean',
        ];
    }
}
