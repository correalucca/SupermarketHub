<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'total',
        'status',
        'fiscal_protocol',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
