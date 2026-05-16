<?php

namespace App\Models\Geografia;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    use HasFactory;

    protected $table = 'provincias';

    protected $fillable = [
        'nombre',
        'codigo',
    ];

    public function cantones(): HasMany
    {
        return $this->hasMany(Canton::class);
    }
}
