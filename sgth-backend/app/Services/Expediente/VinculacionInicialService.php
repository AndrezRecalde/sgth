<?php

namespace App\Services\Expediente;

use App\Enums\OrigenVinculo;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Support\Facades\DB;

/**
 * Carga inicial de un servidor que YA estaba vinculado antes de que el sistema
 * existiera: crea su ficha y su contrato vigente en un solo acto.
 *
 * Es la única vía que se salta la Acción de Personal, y lo hace a propósito.
 * Fabricar un ingreso "ya registrado" para alguien que entró en 2015 acuñaría
 * un código del año en curso, sellaría como firmantes a las autoridades de hoy
 * y produciría un PDF imprimible de un acto que nunca existió. El acto ocurrió
 * en papel; el sistema registra el hecho, no inventa el documento.
 *
 * A cambio, el vínculo queda marcado con su origen y el contrato genera su
 * 'novedad_contrato' como bitácora —la red que ContratoServidorService ya
 * tiende cuando un vínculo aparece sin acción que lo respalde—.
 *
 * Todo lo que ocurra DESPUÉS sobre este vínculo (traspasos, comisiones,
 * cesaciones) vuelve a pasar por el flujo formal, sin excepción.
 */
class VinculacionInicialService
{
    public function __construct(
        private readonly ContratoServidorService $contratoService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $datosServidor  Ficha personal básica.
     * @param  array<string, mixed>  $datosVinculo   Contrato vigente.
     */
    public function registrar(array $datosServidor, array $datosVinculo): Servidor
    {
        $this->validarPlazo($datosVinculo);

        return DB::transaction(function () use ($datosServidor, $datosVinculo) {
            // No se usa ExpedienteService::crearServidorBasico() a propósito:
            // ese método fabrica un MovimientoPersonal de tipo 'ingreso'
            // fechado hoy, que es justo lo que esta vía existe para evitar.
            // Aquí el rastro lo deja el 'novedad_contrato' del contrato.
            $servidor = Servidor::create([
                ...$datosServidor,
                // La antigüedad gobierna comisiones y jubilación. Si no vino
                // declarada, la del contrato es la mejor aproximación.
                'fecha_ingreso_institucion' => $datosServidor['fecha_ingreso_institucion']
                    ?? $datosVinculo['fecha_inicio'],
            ]);

            $this->contratoService->crear($servidor->id, [
                ...$datosVinculo,
                'estado' => 'vigente',
                'origen' => OrigenVinculo::VINCULACION_INICIAL->value,
            ]);

            // Crear el contrato sincroniza fecha_ingreso_institucion con su
            // fecha de inicio. En el alta ordinaria eso es correcto —el primer
            // contrato ES el ingreso—, pero aquí no: quien se migra suele
            // llevar años en la institución con vínculos anteriores. Si TH
            // declaró la fecha real, se restituye.
            //
            // Se escribe con una consulta directa y no con save(): el modelo
            // en memoria conserva el valor original, así que Eloquent no lo
            // vería sucio y no llegaría a escribir nada.
            if (filled($datosServidor['fecha_ingreso_institucion'] ?? null)) {
                Servidor::whereKey($servidor->id)->update([
                    'fecha_ingreso_institucion' => $datosServidor['fecha_ingreso_institucion'],
                ]);
            }

            return $servidor->fresh([
                'contratoVigente.puesto.cargo',
                'contratoVigente.unidadAdministrativa',
                'unidadAdministrativa',
                'puesto.cargo',
                // ServidorResource expone el accesor `rmu` del puesto, que
                // sale de su grupo ocupacional.
                'puesto.grupoOcupacional',
            ]);
        });
    }

    /**
     * Los vínculos con plazo pactado necesitan su fecha de término. En la
     * operación normal la exige el formulario de la acción; aquí no hay acción,
     * así que la regla se comprueba antes de crear nada.
     *
     * Servicios Profesionales queda fuera: si no se indica, el contrato toma
     * el 31 de diciembre de su año de inicio, que es su plazo por defecto.
     *
     * @param  array<string, mixed>  $datos
     */
    private function validarPlazo(array $datos): void
    {
        $nombramiento = TipoNombramiento::tryFrom((string) ($datos['tipo_nombramiento'] ?? ''));

        if ($nombramiento !== TipoNombramiento::SERVICIOS_OCASIONALES) {
            return;
        }

        if (blank($datos['fecha_fin'] ?? null)) {
            throw new ReglaNegocioException(
                'Un contrato de Servicios Ocasionales necesita fecha de término. '
                    .'Indíquela para poder registrar la vinculación.'
            );
        }
    }

    /**
     * Los vínculos cargados por migración, para revisarlos como cohorte.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ContratoServidor>
     */
    public function listarCargados()
    {
        return ContratoServidor::where('origen', OrigenVinculo::VINCULACION_INICIAL->value)
            ->with(['servidor:id,cedula,nombre,apellido', 'puesto.cargo:id,nombre', 'unidadAdministrativa:id,nombre'])
            ->orderByDesc('created_at')
            ->get();
    }
}
