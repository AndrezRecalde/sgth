<?php

namespace App\Contracts\Dispensario;

interface PacienteServiceInterface
{
    /**
     * Busca un paciente (servidor o beneficiario)
     * por número de cédula.
     *
     * @return array{
     *   tipo: string,
     *   id: int,
     *   cedula: string,
     *   nombre_completo: string,
     *   tiene_historia_clinica: bool,
     *   historia_clinica_id: int|null,
     * }
     */
    public function buscarPorCedula(string $cedula): array;
}
