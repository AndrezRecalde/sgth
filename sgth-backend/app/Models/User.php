<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'email', 'password', 'usuario_ti', 'primer_login'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'sanctum';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'primer_login' => 'boolean',
        ];
    }

    /**
     * Obtener el servidor asociado al usuario.
     */
    public function servidor(): HasOne
    {
        return $this->hasOne(\App\Models\Expediente\Servidor::class);
    }

    /**
     * Determina si el usuario es jefe del servidor indicado.
     *
     * @param int $servidorId ID del servidor a validar
     * @return bool
     */
    public function esJefeDeServidor(int $servidorId): bool
    {
        $servidor = \App\Models\Expediente\Servidor::find($servidorId);
        if (!$servidor) return false;

        $mismaUnidad = $servidor->unidad_administrativa_id;
        $miServidor  = \App\Models\Expediente\Servidor::where('user_id', $this->id)->first();
        if (!$miServidor) return false;

        // Caso 1: es jefe titular por puesto (es_jefe = true en la misma unidad)
        $esJefeTitular = \App\Models\Expediente\Servidor::where('user_id', $this->id)
            ->whereHas('puesto', fn($q) => $q->where('es_jefe', true))
            ->where('unidad_administrativa_id', $mismaUnidad)
            ->exists();

        return $esJefeTitular;
    }
}
