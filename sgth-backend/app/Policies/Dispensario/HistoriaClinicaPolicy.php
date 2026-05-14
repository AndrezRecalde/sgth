<?php
namespace App\Policies\Dispensario;

use App\Models\Dispensario\HistoriaClinica;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class HistoriaClinicaPolicy
{
    use HandlesAuthorization;

    private const ROLES_MEDICOS = ['medico', 'odontologo', 'enfermera', 'admin-dispensario'];

    public function before(User $user, string $ability): ?bool
    {
        // NO HAY BYPASS para 'maxima-autoridad' ni 'admin-uath' aquí.
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
        if ($user->servidor_id === $model->servidor_id) {
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
        return $user->hasRole('admin-dispensario');
    }

    private function isPersonalMedico(User $user): bool
    {
        return $user->hasAnyRole(self::ROLES_MEDICOS);
    }
}
