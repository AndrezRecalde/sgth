<?php

namespace Database\Seeders;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de PRUEBA para recorrer los flujos de Acciones de Personal.
 *
 * NO está en DatabaseSeeder a propósito: es data ficticia. Se ejecuta a mano:
 *
 *     php artisan db:seed --class=DatosPruebaAccionesPersonalSeeder
 *
 * Cada vínculo nace de una acción de personal de tipo ingreso llevada hasta
 * 'registrada', igual que en la operación real. La Prefecta, del «Inicio del
 * período» con su credencial del CNE: la autoridad electa no ingresa con
 * acción de personal (fase 4.1). La versión anterior creaba los
 * contratos directamente y eso, además de saltarse la regla, dejaba contratos
 * sin remuneración y un 'novedad_contrato' fantasma por cada uno — la bitácora
 * de emergencia que genera ContratoServidorService cuando un vínculo aparece
 * sin acción que lo respalde.
 *
 * Es idempotente: borra sus propios datos antes de recrearlos.
 */
class DatosPruebaAccionesPersonalSeeder extends Seeder
{
    /** Prefijo reconocible para identificar y limpiar estos datos. */
    private const PREFIJO_CEDULA = '081234560';

    public function __construct(
        private readonly MovimientoPersonalService $movimientoService,
        private readonly MovimientoPersonalStateService $stateService,
    ) {
    }

    public function run(): void
    {
        $this->limpiar();

        $unidadAdministrativa = $this->unidad('Gestión Administrativa');
        $unidadTic = $this->unidad('Gestión de Tecnologías de la Información y Comunicación');
        $partida = PartidaPresupuestaria::where('codigo', '510105')->first();

        // Grupos ocupacionales solo para los puestos LOSEP: de ahí sale su RMU.
        // El puesto de obreros va sin grupo a propósito — bajo Código del
        // Trabajo la remuneración se pacta en cada contrato.
        $grupoAsistente = DB::table('grupos_ocupacionales')->where('grado_codigo', 'SPA1')->value('id');
        $grupoProfesional = DB::table('grupos_ocupacionales')->where('grado_codigo', 'SPS2')->value('id');

        $puestoObrero = $this->puesto('Trabajador de Mantenimiento', $unidadAdministrativa, [
            'plazas' => 5, 'regimen_laboral' => 'codigo_trabajo',
            'grupo_ocupacional_id' => null, 'nivel_complejidad' => 'bajo',
            'rol_puesto' => 'codigo_trabajo', 'partida_presupuestaria_id' => $partida?->id,
        ]);

        $puestoConsultor = $this->puesto('Consultor de Proyectos', $unidadTic, [
            'plazas' => 5, 'regimen_laboral' => 'losep',
            'grupo_ocupacional_id' => $grupoProfesional, 'nivel_complejidad' => 'alto',
            'rol_puesto' => 'ejecucion_procesos', 'partida_presupuestaria_id' => $partida?->id,
        ]);

        $puestoAsistente = $this->puesto('Asistente Administrativo', $unidadAdministrativa, [
            'plazas' => 5, 'regimen_laboral' => 'losep',
            'grupo_ocupacional_id' => $grupoAsistente, 'nivel_complejidad' => 'medio',
            'rol_puesto' => 'administrativo', 'partida_presupuestaria_id' => $partida?->id,
        ]);

        $anioPasado = now()->subYear()->year;

        // Las dos jefaturas que firman. Ningún seeder de la base las crea, así
        // que en una base recién sembrada no existían, y sin ellas tampoco hay
        // a quién anclar las firmas: se crean si faltan y se anclan antes de
        // vincular a nadie. El anclaje no toca lo que ya esté anclado.
        $puestoPrefecto = $this->puestoDeJefatura('Prefecto/a Provincial', 'Prefectura Provincial', 'NJS-10', 'dignatario');
        $puestoDirectorTh = $this->puestoDeJefatura(
            'Director/a de Talento Humano', 'Gestión de Talento Humano y Riesgos Laborales', 'NJS-5', 'ejecucion_coordinacion'
        );
        $this->call(AnclajeFirmantesSeeder::class);

        // El orden importa: los dos primeros ocupan las jefaturas ancladas, así
        // que las acciones siguientes ya sellan sus nombres como firmantes.
        $this->vincular('1', 'Marcela', 'Quiñónez', 'femenino', TipoNombramiento::ELECCION_POPULAR,
            $puestoPrefecto, '2023-05-15', 4200.00);

        $this->vincular('2', 'Rodrigo', 'Valencia', 'masculino', TipoNombramiento::PERMANENTE,
            $puestoDirectorTh, '2019-03-01', 2034.00);

        // Obrero: única vía para probar el visto bueno. Su remuneración es la
        // pactada, no la del puesto — el puesto ni siquiera tiene RMU.
        $this->vincular('3', 'Segundo', 'Caicedo', 'masculino', TipoNombramiento::CODIGO_TRABAJO,
            $puestoObrero, '2021-06-01', 620.00);

        // Servicios Profesionales YA VENCIDO: 'sgth:contratos:detectar-vencidos'
        // debe generarle la cesación por contrato finalizado.
        $this->vincular('4', 'Lorena', 'Bone', 'femenino', TipoNombramiento::SERVICIOS_PROFESIONALES,
            $puestoConsultor, "{$anioPasado}-03-01", 1800.00, "{$anioPasado}-03-01");

        // Servicios Profesionales VIGENTE: control, no debe aparecer.
        $this->vincular('5', 'Andrés', 'Mina', 'masculino', TipoNombramiento::SERVICIOS_PROFESIONALES,
            $puestoConsultor, now()->startOfYear()->toDateString(), 1750.00,
            now()->startOfYear()->toDateString());

        // Permanente con antigüedad: comisión de servicios (exige un año, fase 2.3)
        // y traspasos, sin tocar al usuario real.
        $this->vincular('6', 'Gabriela', 'Ortiz', 'femenino', TipoNombramiento::PERMANENTE,
            $puestoAsistente, '2018-02-01', 986.00);

        // Permanente con antigüedad que además se va de comisión: es la única
        // forma de que la pestaña de Ausencias y Reemplazos tenga algo que
        // mostrar, y de poder ensayar la contratación del suplente.
        $ausente = $this->vincular('7', 'Nelson', 'Arroyo', 'masculino', TipoNombramiento::PERMANENTE,
            $puestoAsistente, '2017-09-01', 1010.00);

        $this->comisionar($ausente, $puestoAsistente);

        $this->command?->newLine();
        $this->command?->info('Datos de prueba listos — cada vínculo nació de su acto registrado: el ingreso, o el inicio del período de la Prefecta.');
        $this->command?->line("  · Un contrato de Servicios Profesionales vencido en {$anioPasado} → detección de vencidos.");
        $this->command?->line('  · El obrero (Código del Trabajo) tiene RMU pactada; su puesto no define ninguna.');
        $this->command?->line('  · Arroyo Nelson está en comisión de servicios y su plaza espera reemplazo.');
    }

    /**
     * Borra los datos de corridas anteriores. Sin esto, repetir el seeder
     * chocaría con la cédula única y dejaría vínculos huérfanos.
     */
    private function limpiar(): void
    {
        $ids = Servidor::withTrashed()
            ->where('cedula', 'like', self::PREFIJO_CEDULA.'%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('solicitudes_certificacion_medica')->whereIn('servidor_id', $ids)->delete();

        // Las subrogaciones apuntan al servidor por dos columnas distintas, y
        // ninguna se borra en cascada. Sin esto la limpieza fallaba con una
        // violación de clave foránea en cuanto alguien hubiera ensayado una
        // subrogación con estos datos, y el seeder dejaba de poder ejecutarse.
        DB::table('subrogaciones')
            ->whereIn('servidor_subrogante_id', $ids)
            ->orWhereIn('servidor_subrogado_id', $ids)
            ->delete();

        MovimientoPersonal::whereIn('servidor_id', $ids)->delete();
        ContratoServidor::withTrashed()->whereIn('servidor_id', $ids)->forceDelete();
        Servidor::withTrashed()->whereIn('id', $ids)->forceDelete();

        $this->command?->warn('Se limpiaron '.$ids->count().' servidor(es) de prueba anteriores.');
    }

    private function unidad(string $nombre): UnidadAdministrativa
    {
        $unidad = UnidadAdministrativa::where('nombre', 'ilike', $nombre)->first();

        if (! $unidad) {
            throw new \RuntimeException(
                "No se encontró la unidad '{$nombre}'. Ejecute antes UnidadAdministrativaSeeder."
            );
        }

        return $unidad;
    }

    /**
     * La jefatura con ese cargo, o la de su unidad si nadie la creó todavía:
     * en una base recién sembrada no existía, y el seeder se detenía aquí.
     */
    private function puestoDeJefatura(string $nombreCargo, string $nombreUnidad, string $grado, string $rol): Puesto
    {
        $existente = Puesto::whereHas('cargo', fn ($q) => $q->where('nombre', $nombreCargo))
            ->where('es_jefe', true)
            ->first();

        if ($existente) {
            return $existente;
        }

        $unidad = $this->unidad($nombreUnidad);

        return Puesto::create([
            'cargo_id'                  => Cargo::firstOrCreate(['nombre' => $nombreCargo])->id,
            'unidad_administrativa_id'  => $unidad->id,
            'grupo_ocupacional_id'      => DB::table('grupos_ocupacionales')->where('grado_codigo', $grado)->value('id'),
            'partida_presupuestaria_id' => PartidaPresupuestaria::where('codigo', '510105')->value('id'),
            'plazas'                    => 1,
            'regimen_laboral'           => 'losep',
            'rol_puesto'                => $rol,
            'es_jefe'                   => true,
            'activo'                    => true,
        ]);
    }

    /** @param  array<string, mixed>  $atributos */
    private function puesto(
        string $nombreCargo,
        UnidadAdministrativa $unidad,
        array $atributos
    ): Puesto {
        $cargo = Cargo::firstOrCreate(['nombre' => $nombreCargo]);

        // updateOrCreate para que una corrida posterior complete los puestos
        // que hubieran quedado a medias.
        return Puesto::updateOrCreate(
            ['cargo_id' => $cargo->id, 'unidad_administrativa_id' => $unidad->id],
            [...$atributos, 'es_jefe' => false, 'activo' => true]
        );
    }

    /**
     * Crea el servidor y lo vincula por el camino formal: acción de ingreso →
     * suscrita → registrada. El contrato lo materializa la propia acción.
     */
    private function vincular(
        string $sufijo,
        string $nombre,
        string $apellido,
        string $genero,
        TipoNombramiento $nombramiento,
        Puesto $puesto,
        string $fechaIngreso,
        float $remuneracion,
        ?string $fechaEfectiva = null
    ): Servidor {
        $cedula = self::PREFIJO_CEDULA.$sufijo;

        $servidor = Servidor::create([
            'cedula' => $cedula,
            'nombre' => $nombre,
            'apellido' => $apellido,
            // Obligatorio al inscribir por pantalla, y aquí también: la ficha
            // FEMO habilita los bloques gineco-obstétricos según este dato, así
            // que un servidor sembrado sin género nace con la ficha incompleta.
            'genero' => $genero,
            'correo_personal' => strtolower($nombre).'.'.strtolower($apellido).'@prueba.local',
            // `regimen_laboral` NO se escribe aquí. Es un campo derivado del
            // contrato, y ContratoServidorService::sincronizarRegimenServidor()
            // lo pone al materializar la acción de ingreso, unas líneas más
            // abajo. Escribirlo a mano con `esLosep() ? ... : 'codigo_trabajo'`
            // —como hacía antes— dejaba a los de Servicios Profesionales como
            // Código del Trabajo: `esLosep()` es binario y no conoce el tercer
            // régimen, así que un `migrate:fresh --seed` deshacía la migración
            // que los reclasificó.
            'fecha_ingreso_institucion' => $fechaIngreso,
            'numero_papeleta_votacion' => '00'.$sufijo.'-0001',
            'estado' => true,
        ]);

        $esAutoridad = $nombramiento === TipoNombramiento::ELECCION_POPULAR;

        // La autoridad electa no ingresa con acción de personal: su período
        // empieza con la credencial del CNE y termina en su fecha, la del
        // período legal de cuatro años (fase 4.1).
        $delActo = $esAutoridad
            ? [
                'tipo_movimiento'       => TipoMovimientoPersonal::INICIO_PERIODO->value,
                'subtipo_movimiento'    => SubtipoMovimientoPersonal::ELECCION->value,
                'descripcion'           => 'Inicio del período de prueba, con la credencial del CNE.',
                'credencial_cne_numero' => 'CNE-PRUEBA-'.$sufijo,
                'credencial_cne_fecha'  => Carbon::parse($fechaIngreso)->subDays(20)->toDateString(),
                'fecha_fin_propuesta'   => Carbon::parse($fechaIngreso)->addYearsNoOverflow(4)->subDay()->toDateString(),
            ]
            : [
                'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
                'tipo_nombramiento_propuesto' => $nombramiento->value,
                // Los datos de prueba no pasan por el dispensario: de lo
                // contrario el ingreso quedaría bloqueado esperando un
                // dictamen médico.
                'requiere_dictamen_medico'    => false,
                'descripcion'                 => "Ingreso de prueba — {$nombramiento->etiqueta()}.",
            ];

        $movimiento = $this->movimientoService->registrar($servidor->id, [
            ...$delActo,
            'puesto_destino_id' => $puesto->id,
            'unidad_destino_id' => $puesto->unidad_administrativa_id,
            'fecha_efectiva' => $fechaEfectiva ?? $fechaIngreso,
        ]);

        $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

        // La remuneración y el número de contrato se aportan al aprobar, que es
        // como funciona en la operación real.
        $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA, [
            // El de la autoridad es el de su credencial, que ya trae el acto.
            'numero_contrato' => $esAutoridad ? null : 'CT-PRUEBA-'.$sufijo,
            'remuneracion_propuesta' => $remuneracion,
            'resolucion_numero' => 'RES-PRUEBA-'.$sufijo,
            'partida_presupuestaria_id' => $puesto->partida_presupuestaria_id,
        ]);

        $this->command?->line(
            "  {$cedula} {$apellido} {$nombre} — {$nombramiento->etiqueta()} · $"
                .number_format($remuneracion, 2)
        );

        return $servidor->fresh('contratoVigente');
    }

    /**
     * Manda al servidor a comisión de servicios con remuneración, registrada y
     * en curso hoy. La comisión no cierra su vínculo ni libera la plaza: deja
     * el hueco que Talento Humano cubre con personal temporal.
     */
    private function comisionar(Servidor $servidor, Puesto $puesto): void
    {
        $inicio = now()->subMonths(3)->startOfMonth();
        $fin    = (clone $inicio)->addYears(2)->subDay();

        $comision = $this->movimientoService->registrar($servidor->id, [
            'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
            'descripcion'        => 'Comisión de servicios con remuneración en el Ministerio de Trabajo.',
            // A qué entidad va: la comisión es servir en otra (fase 2.3).
            'institucion_destino' => 'Ministerio del Trabajo',
            'fecha_efectiva'     => $inicio->toDateString(),
            'fecha_inicio'       => $inicio->toDateString(),
            'fecha_fin'          => $fin->toDateString(),
            'unidad_destino_id'  => $puesto->unidad_administrativa_id,
        ]);

        $comision = $this->stateService->transicionar($comision, EstadoAccionPersonal::SUSCRITA);
        $this->stateService->transicionar($comision->fresh(), EstadoAccionPersonal::REGISTRADA);

        $this->command?->line(
            '  · Comisión vigente del '.$inicio->toDateString().' al '.$fin->toDateString().'.'
        );
    }
}
