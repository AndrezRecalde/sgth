<?php

namespace App\Models\Geografia;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Canton extends Model
{
    use HasFactory;

    protected $table = 'cantones';

    protected $fillable = [
        'provincia_id',
        'nombre',
        'codigo',
    ];

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }
}
