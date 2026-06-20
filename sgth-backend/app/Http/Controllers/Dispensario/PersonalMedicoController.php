<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PersonalMedicoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rol = $request->string('rol')->value();

        $rolesValidos = ['medico', 'odontologo', 'enfermera'];
        $roles = $rol && in_array($rol, $rolesValidos, true)
            ? [$rol]
            : $rolesValidos;

        $personal = User::role($roles)
            ->where('activo', true)
            ->with('servidor.puesto.cargo')
            ->get()
            ->map(function (User $user) {
                return [
                    'id'              => $user->id,
                    'nombre_completo' => $user->nombre_completo
                        ?? trim(
                            ($user->servidor?->nombre ?? '') . ' ' .
                            ($user->servidor?->apellido ?? '')
                        ),
                    'roles'  => $user->getRoleNames(),
                    'puesto' => $user->servidor?->puesto?->cargo?->nombre,
                ];
            });

        return ApiResponse::ok($personal, 'Personal médico disponible.');
    }
}
