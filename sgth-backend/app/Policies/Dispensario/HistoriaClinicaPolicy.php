<?php
namespace App\Policies\Dispensario;

use App\Enums\Permiso;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Por permiso desde el PR 17d de docs/plan-roles-y-accesos.md, como las
 * rutas de historias. Al 2026-10-28 ningún `authorize` la usa: las rutas
 * deciden con `permission:ver-historia-clinica`.
 */
final class HistoriaClinicaPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // NO HAY BYPASS para ningún rol de Talento Humano aquí.
        // Solo retornamos null para que la evaluación continúe al método específico.
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->isPersonalMedico($user);
    }

    public function view(User $user, HistoriaClinica $model): bool
    {
        // El propio servidor puede ver su propia Historia Clínica básica (autoservicio)
        // o la de sus beneficiarios
        if ($model->servidor_id && $user->servidor_id === $model->servidor_id) {
            return true;
        }

        if ($model->carga_familiar_id && $user->servidor_id === $model->cargaFamiliar->servidor_id) {
            return true;
        }

        return $this->isPersonalMedico($user);
    }

    public function create(User $user): bool
    {
        return $this->isPersonalMedico($user);
    }

    public function update(User $user, HistoriaClinica $model): bool
    {
        return $this->isPersonalMedico($user);
    }

    public function delete(User $user, HistoriaClinica $model): bool
    {
        return $user->can(Permiso::SUPERVISAR_DISPENSARIO->value);
    }

    private function isPersonalMedico(User $user): bool
    {
        return $user->can(Permiso::VER_HISTORIA_CLINICA->value);
    }
}
