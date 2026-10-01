<?php

namespace App\Observers\Sso;

use Illuminate\Database\Eloquent\Model;

/**
 * El rastro en `activity_log` que comparten los cinco observers del módulo.
 *
 * Los cinco existían registrados con `#[ObservedBy]` y los quince métodos
 * vacíos, con el comentario «Auditoría automática gestionada globalmente».
 * No hay tal mecanismo global: `spatie/laravel-activitylog` se activa modelo
 * por modelo, con el trait `LogsActivity` o —como hace el resto de este
 * repositorio— con un observer que llama a `activity()`. Así lo hacen
 * `PuestoObserver`, `ContratoServidorObserver` y `DocumentoServidorObserver`.
 *
 * Es decir que los cinco estaban exactamente donde va el gancho de auditoría
 * de este código, y vacíos: quien comprobara si la matriz de riesgos queda
 * auditada encontraba el observer, leía el comentario y concluía que sí.
 *
 * Lo que cada observer registra lo decide él, porque lo que importa de un
 * riesgo laboral (su nivel de intervención) no es lo que importa de un equipo
 * de protección (su código). Lo común es esto.
 */
trait RegistraActividadSso
{
    /**
     * Columnas que no dicen nada en una lista de «qué cambió»: las escribe el
     * propio guardado en cada actualización.
     *
     * @var list<string>
     */
    private const RUIDO = ['updated_at', 'updated_by', 'created_at', 'created_by'];

    /**
     * @param array<string, mixed> $propiedades
     */
    private function registrar(Model $modelo, string $evento, string $mensaje, array $propiedades = []): void
    {
        if ($evento === 'updated') {
            // Lo que un auditor pregunta no es «se actualizó», es QUÉ cambió.
            $propiedades['campos_modificados'] = array_values(
                array_diff(array_keys($modelo->getChanges()), self::RUIDO),
            );
        }

        activity()
            ->performedOn($modelo)
            ->withProperties($propiedades)
            ->event($evento)
            ->log($mensaje);
    }
}
