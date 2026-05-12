<?php

namespace App\Models\Expediente;

use App\Enums\RegimenLaboral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Servidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'servidores';

    protected $fillable = [
        'user_id',
        'cedula',
        'nombre',
        'segundo_nombre',
        'apellido',
        'segundo_apellido',
        'regimen_laboral',
        'unidad_administrativa_id',
        'puesto_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'regimen_laboral' => RegimenLaboral::class,
            'estado' => 'boolean',
        ];
    }

    /**
     * Cuenta de usuario del servidor
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
