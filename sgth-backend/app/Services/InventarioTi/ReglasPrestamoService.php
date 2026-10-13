<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\ReglasPrestamoServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Helpers\DiasHabilesHelper;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\InventarioTi\ReglasPrestamo;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Las reglas del préstamo (D16), configurables desde /tic: el plazo máximo en
 * días hábiles, la anticipación, el horario de atención, si se presta en fin de
 * semana, si quien tiene un préstamo vencido puede pedir otro y el margen para
 * retirar.
 *
 * Los errores de horario salen por campo (`inicio_previsto`, `fin_previsto`),
 * para que el formulario los pinte donde van.
 */
final class ReglasPrestamoService implements ReglasPrestamoServiceInterface
{
    use DiasHabilesHelper;

    public function obtener(): ReglasPrestamo
    {
        return ReglasPrestamo::vigentes()->load('actualizadoPor:id,usuario_ti,email,servidor_id', 'actualizadoPor.servidor:id,nombre,apellido');
    }

    public function actualizar(array $datos): ReglasPrestamo
    {
        if ($datos['hora_cierre'] <= $datos['hora_apertura']) {
            throw ValidationException::withMessages(['hora_cierre' => 'El cierre tiene que ser después de la apertura.']);
        }

        ReglasPrestamo::vigentes()->update([...$datos, 'actualizado_por' => auth()->id()]);

        return $this->obtener();
    }

    public function validarHorario(Carbon $inicio, Carbon $fin, bool $directo = false): void
    {
        $reglas = ReglasPrestamo::vigentes();
        $errores = [];

        if ($fin->lte($inicio)) {
            throw ValidationException::withMessages(['fin_previsto' => 'La devolución tiene que ser después de la entrega.']);
        }

        if (! $directo) {
            // Un minuto de gracia: el formulario se llena antes de enviarse.
            $minimo = now()->addMinutes($reglas->anticipacion_minima_minutos)->subMinute();
            if ($inicio->lt($minimo)) {
                $errores['inicio_previsto'] = $reglas->anticipacion_minima_minutos > 0
                    ? "Se pide con al menos {$this->duracion($reglas->anticipacion_minima_minutos)} de anticipación."
                    : 'La hora de entrega ya pasó.';
            } elseif ($motivo = $this->fueraDeAtencion($inicio, $reglas)) {
                $errores['inicio_previsto'] = "La entrega {$motivo}.";
            }
        }

        if ($motivo = $this->fueraDeAtencion($fin, $reglas)) {
            $errores['fin_previsto'] = "La devolución {$motivo}.";
        } elseif ($fin->gt($limite = $this->limite($inicio, $reglas))) {
            $errores['fin_previsto'] = "El préstamo dura hasta {$reglas->plazo_maximo_dias_habiles} día(s) hábil(es): "
                . 'devuélvalo a más tardar el ' . $limite->format('d/m/Y \a \l\a\s H:i') . '.';
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    public function validarServidor(Servidor $servidor): void
    {
        if (! $servidor->estado) {
            throw new ReglaNegocioException('El servidor no está activo: no puede recibir equipos en préstamo.');
        }

        if (ReglasPrestamo::vigentes()->bloquea_con_vencido) {
            $vencido = PrestamoEquipo::vencidos()->where('servidor_id', $servidor->id)->orderBy('fin_previsto')->first();
            if ($vencido) {
                throw new ReglaNegocioException(
                    "Tiene el préstamo {$vencido->folio} vencido desde el {$vencido->fin_previsto->format('d/m/Y H:i')}: "
                    . 'devuelva esos equipos antes de pedir otros.'
                );
            }
        }
    }

    public function margenRetiro(): int
    {
        return ReglasPrestamo::vigentes()->margen_retiro_minutos;
    }

    /** Hasta cuándo puede durar un préstamo que empieza en `$inicio`: el día de la entrega cuenta como el primero. */
    private function limite(Carbon $inicio, ReglasPrestamo $reglas): Carbon
    {
        $dia = $inicio->copy()->startOfDay();
        $ultimo = $reglas->plazo_maximo_dias_habiles > 1
            ? $this->calcularDiasHabiles($dia, $reglas->plazo_maximo_dias_habiles - 1)
            : $dia;

        return $ultimo->setTimeFromTimeString($reglas->cierre());
    }

    /** Por qué un momento queda fuera de la atención de TIC, o null si está dentro. */
    private function fueraDeAtencion(Carbon $momento, ReglasPrestamo $reglas): ?string
    {
        if (! $reglas->presta_fines_de_semana && $momento->isWeekend()) {
            return 'cae en fin de semana, y TIC no presta esos días';
        }
        if (! $reglas->presta_fines_de_semana && $this->esFeriado($momento, $this->feriados())) {
            return 'cae en feriado, y TIC no presta esos días';
        }

        $hora = $momento->format('H:i');
        if ($hora < $reglas->apertura() || $hora > $reglas->cierre()) {
            return "tiene que estar en el horario de atención de TIC, de {$reglas->apertura()} a {$reglas->cierre()}";
        }

        return null;
    }

    private function duracion(int $minutos): string
    {
        return $minutos % 60 === 0 ? ($minutos / 60) . ' hora(s)' : "{$minutos} minutos";
    }
}
