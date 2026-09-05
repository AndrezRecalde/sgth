<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['email', 'password', 'usuario_ti', 'primer_login', 'activo', 'servidor_id'])]
#[Hidden(['password', 'remember_token'])]
#[Appends(['nombre_completo'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'sanctum';

    /**
     * Espejo de los DEFAULT de la tabla. Sin esto, un User recién creado tiene
     * activo === null en memoria hasta que se recarga —Eloquent no relee los
     * defaults del motor—, y cualquier comprobación de `activo` sobre esa
     * instancia lo trata como desactivado.
     */
    protected $attributes = [
        'activo'       => true,
        'primer_login' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'primer_login'      => 'boolean',
            'activo'            => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Expediente\Servidor::class,
            'servidor_id'
        );
    }

    public function disponibilidadMedico(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            \App\Models\Dispensario\DisponibilidadMedico::class
        );
    }

    /**
     * Nunca devuelve null, aunque el atributo del que tira no esté cargado.
     *
     * Va en #[Appends], así que se calcula en cada serialización de cualquier
     * User. Un `with('algo:id,usuario_ti,servidor_id')` —hay varios por el
     * dispensario— deja `email` fuera, y para un usuario sin servidor el
     * respaldo era justamente ese: devolvía null contra el tipo declarado y
     * tumbaba la petición entera con un 500, en un listado que no tenía nada
     * que ver. Las consultas siguen pidiendo `email` donde toca; esto es para
     * que la próxima que lo olvide no derribe una pantalla.
     */
    public function getNombreCompletoAttribute(): string
    {
        if ($this->servidor) {
            return trim(
                ($this->servidor->nombre   ?? '') . ' ' .
                ($this->servidor->apellido ?? '')
            );
        }

        return $this->email
            ?? $this->usuario_ti
            ?? "Usuario #{$this->getKey()}";
    }

    public function tieneServidor(): bool
    {
        return $this->servidor_id !== null;
    }

    public function esJefeDeServidor(int $servidorId): bool
    {
        $servidor = \App\Models\Expediente\Servidor::find($servidorId);
        if (!$servidor) return false;

        $mismaUnidad = $servidor->unidad_administrativa_id;
        $miServidor  = $this->servidor;
        if (!$miServidor) return false;

        $esJefeTitular = \App\Models\Estructura\Puesto::where(
            'unidad_administrativa_id', $mismaUnidad
        )
        ->where('es_jefe', true)
        ->whereHas('contratosVigentes', fn($q) => $q
            ->where('servidor_id', $miServidor->id)
        )
        ->exists();

        if ($esJefeTitular) return true;

        // Subrogacion — solo si el modelo existe
        if (class_exists(\App\Models\Expediente\Subrogacion::class)) {
            return \App\Models\Expediente\Subrogacion::where(
                'servidor_subrogante_id', $miServidor->id
            )
            ->where('unidad_administrativa_id', $mismaUnidad)
            ->where('estado', \App\Enums\EstadoSubrogacion::ACTIVA)
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->exists();
        }

        return false;
    }
}
