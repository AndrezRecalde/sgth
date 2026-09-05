<?php

namespace Database\Seeders;

use App\Models\Dispensario\AdquisicionMedicamento;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\InventarioMedicina;
use App\Models\Dispensario\ItemAdquisicion;
use App\Models\Dispensario\ItemReceta;
use App\Models\Dispensario\LoteMedicina;
use App\Models\Dispensario\MovimientoInventarioMed;
use App\Models\Dispensario\RecetaMedica;
use App\Models\Dispensario\Triaje;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Dispensario\AdquisicionService;
use App\Services\Dispensario\InventarioMedicinasService;
use App\Services\Dispensario\RecetaService;
use Illuminate\Database\Seeder;

/**
 * Datos de PRUEBA para recorrer los flujos del Dispensario Médico.
 *
 * NO está en DatabaseSeeder a propósito: es data ficticia, y además clínica.
 * Se ejecuta a mano:
 *
 *     php artisan db:seed --class=DatosPruebaDispensarioSeeder
 *
 * Existe porque revisar Farmacia a mano era imposible: sin recetas no se podía
 * mirar el despacho ni su anulación, y sin medicinas de caducidades variadas
 * había que fabricarlas una a una en cada repaso.
 *
 * Todo nace de los servicios reales, no de `create()` sueltos: el stock entra
 * por adquisición y los despachos pasan por RecetaService, así que el kardex,
 * los folios y los estados quedan como en la operación y no como una foto fija
 * que el código real nunca habría producido.
 *
 * Es idempotente: borra sus propios datos antes de recrearlos. Los reconoce por
 * la marca SEED en `numero_historia`, `numero_documento` y `lote` — nunca por
 * el nombre del medicamento, que borraría uno real homónimo. Si encuentra en el
 * catálogo una medicina que no sembró él, se detiene y lo dice en vez de
 * pisarla.
 */
class DatosPruebaDispensarioSeeder extends Seeder
{
    private const MARCA = 'SEED';

    /**
     * El catálogo cubre los casos que hay que poder mirar en pantalla:
     * disponible, por caducar, ya caducado, agotado y bajo mínimo.
     */
    private const CATALOGO = [
        'vigente' => [
            'nombre' => 'Paracetamol', 'principio_activo' => 'Paracetamol',
            'presentacion' => 'tableta', 'concentracion' => '500mg',
            'stock_minimo' => 50, 'caduca_en_meses' => 18, 'abastecer' => 400,
        ],
        'vigente_alta' => [
            'nombre' => 'Ibuprofeno', 'principio_activo' => 'Ibuprofeno',
            'presentacion' => 'tableta', 'concentracion' => '400mg',
            'stock_minimo' => 40, 'caduca_en_meses' => 12, 'abastecer' => 300,
        ],
        'por_caducar' => [
            'nombre' => 'Amoxicilina', 'principio_activo' => 'Amoxicilina',
            'presentacion' => 'capsula', 'concentracion' => '500mg',
            'stock_minimo' => 30, 'caduca_en_dias' => 20, 'abastecer' => 120,
        ],
        'caducado' => [
            'nombre' => 'Loratadina', 'principio_activo' => 'Loratadina',
            'presentacion' => 'tableta', 'concentracion' => '10mg',
            'stock_minimo' => 20, 'caduca_en_dias' => -15, 'abastecer' => 80,
        ],
        'agotado' => [
            'nombre' => 'Omeprazol', 'principio_activo' => 'Omeprazol',
            'presentacion' => 'capsula', 'concentracion' => '20mg',
            'stock_minimo' => 25, 'caduca_en_meses' => 10, 'abastecer' => 0,
        ],
        'bajo_minimo' => [
            'nombre' => 'Salbutamol', 'principio_activo' => 'Salbutamol',
            'presentacion' => 'spray', 'concentracion' => '100mcg',
            'stock_minimo' => 20, 'caduca_en_meses' => 8, 'abastecer' => 5,
        ],
    ];

    public function __construct(
        private readonly InventarioMedicinasService $inventario,
        private readonly AdquisicionService $adquisiciones,
        private readonly RecetaService $recetas,
    ) {
    }

    public function run(): void
    {
        $medico = User::role('medico')->first();

        if (! $medico) {
            $this->command->warn(
                'No hay ningún usuario con rol medico. Ejecute antes ' .
                'RolPermisoSeeder y AdminTiSeeder.'
            );

            return;
        }

        $this->limpiar();

        $medicinas = $this->crearCatalogo($medico);

        if ($medicinas === null) {
            return;
        }

        $this->abastecer($medicinas, $medico);

        $pacientes = $this->pacientes();

        if ($pacientes->isEmpty()) {
            $this->command->warn(
                'No hay servidores sin historia clínica: se sembró el ' .
                'inventario, pero no las consultas ni las recetas.'
            );

            return;
        }

        $this->crearRecetas($medicinas, $pacientes, $medico);

        $this->command->info('Dispensario sembrado:');
        $this->command->info('  · 6 medicinas (vigente, por caducar, caducada, agotada, bajo mínimo)');
        $this->command->info('  · 2 adquisiciones con su rastro en el kardex');
        $this->command->info('  · 4 recetas: pendiente, parcial, completa y una con medicina caducada');
    }

    /**
     * Pacientes sin historia previa: `cedula_paciente` es única, así que
     * reutilizar un servidor ya atendido chocaría contra el índice.
     */
    private function pacientes()
    {
        return Servidor::where('estado', true)
            ->whereNotIn(
                'cedula',
                HistoriaClinica::whereNotNull('cedula_paciente')
                    ->pluck('cedula_paciente')
            )
            ->orderBy('id')
            ->limit(3)
            ->get();
    }

    /**
     * @return array<string, InventarioMedicina>|null  null si algún nombre ya
     *                                                 lo ocupa una medicina
     *                                                 ajena al seeder.
     */
    private function crearCatalogo(User $medico): ?array
    {
        $medicinas = [];

        foreach (self::CATALOGO as $clave => $datos) {
            // El índice único es (nombre, presentación, concentración): si esa
            // combinación ya existe y no la sembró este seeder, es de alguien
            // más y no se toca.
            $ajena = InventarioMedicina::withTrashed()
                ->where('nombre', $datos['nombre'])
                ->where('presentacion', $datos['presentacion'])
                ->where('concentracion', $datos['concentracion'])
                ->exists();

            if ($ajena) {
                $this->command->error(
                    "Ya existe «{$datos['nombre']} {$datos['concentracion']} " .
                    "({$datos['presentacion']})» y no la sembró este seeder. " .
                    'No se toca nada: retírela del inventario o cambie el ' .
                    'catálogo del seeder antes de volver a ejecutarlo.'
                );

                return null;
            }

            $caducidad = isset($datos['caduca_en_dias'])
                ? now()->addDays($datos['caduca_en_dias'])
                : now()->addMonths($datos['caduca_en_meses']);

            $medicinas[$clave] = $this->inventario->ingresarMedicina([
                'nombre'           => $datos['nombre'],
                'principio_activo' => $datos['principio_activo'],
                'presentacion'     => $datos['presentacion'],
                'concentracion'    => $datos['concentracion'],
                'stock_minimo'     => $datos['stock_minimo'],
                'fecha_caducidad'  => $caducidad->toDateString(),
                'lote'             => self::MARCA . '-' . strtoupper(substr($clave, 0, 6)),
            ], $medico->id);
        }

        return $medicinas;
    }

    /**
     * El stock entra por adquisición, que es la única puerta: una compra y una
     * donación, para que el historial tenga los dos tipos.
     */
    private function abastecer(array $medicinas, User $medico): void
    {
        $porTipo = ['compra' => [], 'donacion' => []];

        foreach (self::CATALOGO as $clave => $datos) {
            if ($datos['abastecer'] === 0) {
                continue;
            }

            // La donación se lleva lo que está por caducar y lo ya vencido,
            // que es de donde suele venir en la práctica.
            $tipo = in_array($clave, ['por_caducar', 'caducado'], true)
                ? 'donacion'
                : 'compra';

            $porTipo[$tipo][] = [
                'inventario_medicina_id' => $medicinas[$clave]->id,
                'cantidad'               => $datos['abastecer'],
                // Conserva la marca: la adquisición sobrescribe el lote de la
                // medicina, y es por el lote por donde se reconocen al limpiar.
                'lote'                   => self::MARCA . '-' . strtoupper(substr($clave, 0, 6)),
                // La caducidad viaja con la entrada, no solo en la ficha: es
                // la del lote que se abre, y sin ella los escenarios de este
                // seeder —«por caducar», «caducado»— nacían sin fecha.
                'fecha_caducidad'        => $medicinas[$clave]->fecha_caducidad
                    ->toDateString(),
                'precio_unitario'        => $tipo === 'compra' ? 0.35 : null,
            ];
        }

        $this->adquisiciones->registrar([
            'tipo'                => 'compra',
            'numero_documento'    => self::MARCA . '-FACT-004521',
            'proveedor_o_donante' => 'Farmaenlace S.A.',
            'fecha_adquisicion'   => now()->subDays(20)->toDateString(),
            'observaciones'       => 'Compra trimestral de medicamentos.',
        ], $porTipo['compra'], $medico->id);

        $this->adquisiciones->registrar([
            'tipo'                => 'donacion',
            'numero_documento'    => self::MARCA . '-ACTA-0087',
            'proveedor_o_donante' => 'Cruz Roja Ecuatoriana',
            'fecha_adquisicion'   => now()->subDays(10)->toDateString(),
            'observaciones'       => 'Donación recibida por acta de entrega.',
        ], $porTipo['donacion'], $medico->id);
    }

    private function crearRecetas($medicinas, $pacientes, User $medico): void
    {
        $paciente = $pacientes->first();
        $consulta = $this->consulta($paciente, $medico, 'Cefalea y malestar general');

        // 1. Pendiente: nada entregado todavía.
        $this->recetas->emitirReceta([
            'consulta_medica_id'     => $consulta->id,
            'fecha_emision'          => now()->toDateString(),
            'indicaciones_generales' => 'Tomar después de las comidas.',
            'created_by'             => $medico->id,
        ], [
            $this->item($medicinas['vigente'], 20, '1 tableta', 'Cada 8 horas', '7 días'),
            $this->item($medicinas['vigente_alta'], 15, '1 tableta', 'Cada 12 horas', '5 días'),
        ]);

        // 2. Parcial: se entregó la mitad de un ítem y nada del otro.
        $parcial = $this->recetas->emitirReceta([
            'consulta_medica_id'     => $consulta->id,
            'fecha_emision'          => now()->subDays(3)->toDateString(),
            'indicaciones_generales' => 'Completar la entrega cuando haya existencias.',
            'created_by'             => $medico->id,
        ], [
            $this->item($medicinas['vigente'], 30, '1 tableta', 'Cada 8 horas', '10 días'),
            $this->item($medicinas['por_caducar'], 21, '1 cápsula', 'Cada 8 horas', '7 días'),
        ])['receta'];

        $primerItem = ItemReceta::where('receta_medica_id', $parcial->id)
            ->orderBy('id')
            ->first();

        $this->recetas->despacharReceta($parcial->id, [
            ['item_receta_id' => $primerItem->id, 'cantidad' => 15],
        ], $medico->id);

        // 3. Completa.
        $completa = $this->recetas->emitirReceta([
            'consulta_medica_id' => $consulta->id,
            'fecha_emision'      => now()->subDays(8)->toDateString(),
            'created_by'         => $medico->id,
        ], [
            $this->item($medicinas['vigente_alta'], 10, '1 tableta', 'Cada 12 horas', '5 días'),
        ])['receta'];

        $itemCompleta = ItemReceta::where('receta_medica_id', $completa->id)->first();

        $this->recetas->despacharReceta($completa->id, [
            ['item_receta_id' => $itemCompleta->id, 'cantidad' => 10],
        ], $medico->id);

        // 4. Con medicina caducada: el despacho debe rechazarla. Es el caso que
        //    no se podía mirar en pantalla sin fabricarlo a mano.
        $otroPaciente = $pacientes->count() > 1 ? $pacientes[1] : $paciente;
        $consultaAlergia = $otroPaciente->is($paciente)
            ? $consulta
            : $this->consulta($otroPaciente, $medico, 'Cuadro alérgico estacional');

        $this->recetas->emitirReceta([
            'consulta_medica_id'     => $consultaAlergia->id,
            'fecha_emision'          => now()->toDateString(),
            'indicaciones_generales' => 'Suspender si aparece somnolencia.',
            'created_by'             => $medico->id,
        ], [
            $this->item($medicinas['caducado'], 10, '1 tableta', 'Diaria', '10 días'),
        ]);
    }

    private function consulta(Servidor $paciente, User $medico, string $motivo): ConsultaMedica
    {
        $historia = HistoriaClinica::create([
            'numero_historia'  => self::MARCA . '-HC-' . $paciente->cedula,
            'cedula_paciente'  => $paciente->cedula,
            'tipo_paciente'    => 'servidor',
            'servidor_id'      => $paciente->id,
            'grupo_sanguineo'  => 'O+',
            'estado'           => true,
            'created_by'       => $medico->id,
        ]);

        return ConsultaMedica::create([
            'historia_clinica_id'   => $historia->id,
            'medico_id'             => $medico->id,
            'fecha_consulta'        => now()->toDateString(),
            'hora_consulta'         => '09:30:00',
            'motivo_consulta'       => $motivo,
            'examen_fisico'         => 'Paciente consciente, orientado, afebril.',
            'diagnostico_detallado' => 'Cuadro leve, se indica tratamiento sintomático.',
            'created_by'            => $medico->id,
        ]);
    }

    private function item(
        InventarioMedicina $medicina,
        int $cantidad,
        string $dosis,
        string $frecuencia,
        string $duracion,
    ): array {
        return [
            'inventario_medicina_id' => $medicina->id,
            'cantidad_prescrita'     => $cantidad,
            'dosis'                  => $dosis,
            'frecuencia'             => $frecuencia,
            'duracion'               => $duracion,
        ];
    }

    /**
     * Borra en orden inverso a las dependencias. El kardex no tiene borrado
     * lógico, así que sus filas se eliminan de verdad; el resto se fuerza para
     * que no queden restos que hagan chocar los índices únicos al resembrar.
     */
    private function limpiar(): void
    {
        $historias = HistoriaClinica::where('numero_historia', 'like', self::MARCA . '-%')
            ->pluck('id');

        $consultas = ConsultaMedica::whereIn('historia_clinica_id', $historias)
            ->pluck('id');

        $recetas = RecetaMedica::withTrashed()
            ->whereIn('consulta_medica_id', $consultas)
            ->pluck('id');

        $medicinas = InventarioMedicina::withTrashed()
            ->where('lote', 'like', self::MARCA . '-%')
            ->pluck('id');

        $adquisiciones = AdquisicionMedicamento::withTrashed()
            ->where('numero_documento', 'like', self::MARCA . '-%')
            ->pluck('id');

        MovimientoInventarioMed::whereIn('referencia_receta_id', $recetas)
            ->orWhereIn('inventario_medicina_id', $medicinas)
            ->delete();

        ItemReceta::withTrashed()->whereIn('receta_medica_id', $recetas)->forceDelete();
        RecetaMedica::withTrashed()->whereIn('id', $recetas)->forceDelete();
        ConsultaMedica::whereIn('id', $consultas)->forceDelete();

        // Los triajes cuelgan de la historia con RESTRICT, así que van antes.
        // No los sembramos nosotros: aparecen cuando alguien atiende a un
        // paciente de prueba, y sin esto la segunda pasada del seeder moría.
        Triaje::whereIn('historia_clinica_id', $historias)->delete();

        HistoriaClinica::whereIn('id', $historias)->forceDelete();

        ItemAdquisicion::whereIn('adquisicion_id', $adquisiciones)->delete();
        AdquisicionMedicamento::withTrashed()->whereIn('id', $adquisiciones)->forceDelete();

        // Los lotes también sujetan la medicina con RESTRICT: son sus
        // existencias, y borrar el catálogo dejándolos huérfanos no tendría
        // sentido.
        LoteMedicina::whereIn('inventario_medicina_id', $medicinas)->delete();

        InventarioMedicina::withTrashed()->whereIn('id', $medicinas)->forceDelete();
    }
}
