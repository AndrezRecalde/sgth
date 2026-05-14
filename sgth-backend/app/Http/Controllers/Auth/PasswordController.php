<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;

final class PasswordController extends Controller
{
    /**
     * Controlador para futuras gestiones de contraseñas.
     * 
     * El cambio de contraseña inicial obligatorio se maneja en el AuthController
     * por convención de la arquitectura (04-api-response-standard.md).
     * Este controlador puede expandirse luego para "Olvidé mi contraseña" 
     * o cambios de contraseña voluntarios.
     */
}
