<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'company_name',
        'company_name_en',
        'description',
        'address',
        'phone',
        'email',
        'default_currency',
        'exchange_rate',
        'logo',
    ];
}
