<?php

namespace App\Contracts\Actividades;

use App\Models\Actividades\InformeActividad;

interface ActividadesServiceInterface
{
    public function registrarActividad(array $datos);

    public function generarInformeMensual(int $servidorId, int $mes, int $anio): InformeActividad;

    public function validarCruceBiometrico(int $servidorId, string $fecha): array;
}
