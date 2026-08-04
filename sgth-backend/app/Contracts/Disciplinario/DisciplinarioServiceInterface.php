<?php

namespace App\Contracts\Disciplinario;

use App\Models\Disciplinario\Sumario;

interface DisciplinarioServiceInterface
{
    /**
     * Abre un sumario administrativo. Solo aplica al régimen LOSEP: los
     * obreros bajo Código del Trabajo se tramitan por visto bueno.
     */
    public function abrirSumario(int $servidorId, array $datos, int $userId): Sumario;

    /**
     * Avanza el sumario por su secuencia procesal (notificación, prueba,
     * informe), registrando la fecha del hito correspondiente.
     */
    public function avanzarSumario(Sumario $sumario, string $estadoDestino, array $datos, int $userId): Sumario;

    /**
     * Resuelve un sumario aplicando una sanción y cambiando el estado del servidor si procede.
     */
    public function resolverSumario(int $sumarioId, array $datosSancion, int $userId): Sumario;

    /**
     * Verifica los plazos procesales legales de todos los sumarios abiertos y alerta a la UATH.
     */
    public function controlarPlazosLegales(): void;
}
