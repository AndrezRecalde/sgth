<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisponibilidadMedico extends Model
{
    use HasFactory;

    protected $table = 'disponibilidad_medicos';

    protected $fillable = [
        'user_id', 'disponible', 'actualizado_en',
    ];

    protected function casts(): array
    {
        return [
            'disponible'     => 'boolean',
            'actualizado_en' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
