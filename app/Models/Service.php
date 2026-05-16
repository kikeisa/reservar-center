<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'duration_minutes',
        'price',
        'professional_id',
        'non_refundable',
    ];

    protected $casts = [
        'non_refundable' => 'boolean',
        'price'          => 'decimal:2',
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
