<?php

namespace App\Services\InventarioTi\Carga;

use App\Contracts\InventarioTi\BienInformaticoServiceInterface;
use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * La carga del inventario desde el Excel de TIC (docs/diseno-subsistema-tic.md,
 * sección 7; fase 1.4).
 *
 * Cada fila de «Equipos» es un equipo, identificado por su código «ACTUAL»
 * completado a seis dígitos. Lo que el Excel dice de él se lleva así:
 * - el funcionario «ASIGNADO» es el custodio; si hay «CUSTODIA
 *   ADMINISTRATIVO», ese es el custodio y el asignado es quien lo usa (D3);
 * - los números de soporte son historia del sistema antiguo (D1): el
 *   mantenimiento de 2025, la vigencia, el cambio a SSD y el traspaso;
 * - la vigencia tecnológica pasa a contratos con su cronograma;
 * - las bajas parciales, el terremoto y las novedades quedan en observaciones (D9).
 *
 * Nada se inventa: sin fecha en el Excel, el mantenimiento queda sin fecha y la
 * custodia con la de la carga y una nota. Lo que no se pudo emparejar va al
 * informe. Con `simular` todo se deshace al final, y el informe es el mismo.
 */
final class CargaInventarioService
{
    public function __construct(
        private readonly LectorEquiposExcel $lector,
        private readonly InterpretadorVigencia $vigencias,
        private readonly BienInformaticoServiceInterface $bienes,
        private readonly CustodiaServiceInterface $custodia,
    ) {}

    /** @return array{equipos: list<array>, sin_emparejar: list<array>, contratos: list<array>, resumen: array<string, int>} */
    public function cargar(string $ruta, bool $simular): array
    {
        $filas = $this->lector->leer($ruta);
        $informe = new InformeCarga;

        DB::beginTransaction();
        try {
            $servidores = new EmparejadorServidores;
            $catalogos = new CatalogosDeCarga($servidores);
            $codigos = [];
            $series = [];

            foreach ($filas as $fila) {
                $this->cargarFila($fila, $servidores, $catalogos, $informe, $codigos, $series);
            }

            $informe->contratos = $catalogos->contratosCreados;
            $simular ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $informe->comoArreglo();
    }

    /** @param  array<string, mixed>  $f */
    private function cargarFila(array $f, EmparejadorServidores $servidores, CatalogosDeCarga $catalogos, InformeCarga $informe, array &$codigos, array &$series): void
    {
        $codigo = $this->codigo($f['actual']);
        if ($codigo === null) {
            $informe->omitido($f, (string) $f['actual'], 'El código «ACTUAL» no es un número: no se sabe qué equipo es.');
            return;
        }
        if (isset($codigos[$codigo]) || BienInformatico::where('codigo_institucional', $codigo)->exists()) {
            $informe->omitido($f, $codigo, 'Ese código ya está en el inventario (o repetido en el Excel).');
            return;
        }
        if (Normaliza::celda($f['tipo']) === null || Normaliza::celda($f['marca']) === null) {
            $informe->omitido($f, $codigo, 'La fila no dice el tipo o la marca del equipo.');
            return;
        }
        $codigos[$codigo] = true;
        $avisos = [];
        $notas = [];

        $tipo = $catalogos->tipo((string) $f['tipo']);
        $serie = Normaliza::celda($f['serie']);
        if ($serie !== null && (isset($series[Normaliza::texto($serie)]) || BienInformatico::whereRaw('lower(numero_serie) = lower(?)', [$serie])->exists())) {
            $avisos[] = "La serie «{$serie}» ya la tiene otro equipo: este queda sin serie.";
            $serie = null;
        }
        if ($serie !== null) {
            $series[Normaliza::texto($serie)] = true;
        }

        $anterior = Normaliza::celda($f['anterior']);
        if ($anterior !== null && ! preg_match('/^\d{3}-\d{2}-\d{3}-\d{4,6}$/', $anterior)) {
            $notas[] = "En la columna de código anterior decía «{$anterior}».";
            $anterior = null;
        }

        $ubicacion = $catalogos->ubicacion(Normaliza::celda($f['piso']));
        if ($ubicacion === null && Normaliza::celda($f['piso']) !== null) {
            $avisos[] = "No se reconoce el piso «{$f['piso']}».";
        }

        [$custodio, $usuario] = $this->custodioYUsuario($f, $servidores, $informe, $codigo);
        $unidad = $custodio ? null : $catalogos->unidad(Normaliza::celda($f['area']));
        if (! $custodio && $unidad === null && Normaliza::celda($f['area']) !== null) {
            $avisos[] = "El área «{$f['area']}» no tiene equivalencia con una unidad.";
        }

        $vigencia = is_string($f['vigencia']) ? $this->vigencias->interpretar($f['vigencia']) : null;
        [$condicion, $notaEstado] = $this->condicion($f['estado_equipo']);

        $bien = $this->bienes->registrar([
            'codigo_institucional'     => $codigo,
            'codigo_anterior'          => $anterior,
            'numero_serie'             => $serie,
            'tipo_bien_id'             => $tipo->id,
            'subtipo_bien_id'          => $catalogos->subtipo($tipo, Normaliza::celda($f['subtipo']))?->id,
            'marca_id'                 => $catalogos->marca((string) $f['marca'])->id,
            'modelo'                   => Normaliza::celda($f['modelo']),
            'fecha_compra'             => $f['fecha_compra'],
            'ubicacion_id'             => $ubicacion?->id,
            'unidad_administrativa_id' => $unidad?->id,
            'nombre_red'               => Normaliza::celda($f['nombre_red']),
            'usuario_dominio'          => Normaliza::celda($f['usuario_dominio']),
            'contrato_vigencia_id'     => $vigencia ? $catalogos->contrato($vigencia)->id : null,
            'condicion_fisica'         => $condicion,
            'caracteristicas_tecnicas' => in_array(Normaliza::texto($f['tipo_disco']), ['SSD', 'HDD'], true) ? ['tipo_disco' => Normaliza::texto($f['tipo_disco'])] : null,
            'observaciones'            => $this->observaciones($f, [...$notas, ...array_filter([$notaEstado])]),
        ]);

        $this->mantenimientos($bien, $f);

        if ($custodio) {
            try {
                $this->custodia->entregar([
                    'bien_informatico_id' => $bien->id,
                    'servidor_id'         => $custodio->id,
                    'usuario_servidor_id' => $usuario?->id,
                    'fecha_asignacion'    => now()->toDateString(),
                    'memorando_numero'    => Normaliza::celda($f['memorando_traspaso']),
                    'soporte_anterior'    => Normaliza::soporte($f['soporte_traspaso']),
                    'observaciones'       => trim('Custodia registrada en la carga del Excel de TIC: la fecha real de entrega no consta.'
                        . (Normaliza::celda($f['usuario_anterior']) ? " Antes lo tuvo {$f['usuario_anterior']}." : '')),
                ]);
            } catch (ReglaNegocioException $e) {
                $avisos[] = "No se registró la custodia: {$e->getMessage()}";
            }
        }

        $informe->cargado($f, $codigo, $custodio, $usuario, $avisos);
    }

    /** El código «ACTUAL» con sus ceros: Excel guardó 1774 donde Activos Fijos escribe 001774. */
    private function codigo(mixed $actual): ?string
    {
        $texto = is_float($actual) || is_int($actual) ? (string) (int) $actual : trim((string) $actual);

        return preg_match('/^\d{1,6}$/', $texto) ? str_pad($texto, 6, '0', STR_PAD_LEFT) : null;
    }

    /** @return array{0: ?\App\Models\Expediente\Servidor, 1: ?\App\Models\Expediente\Servidor} */
    private function custodioYUsuario(array $f, EmparejadorServidores $servidores, InformeCarga $informe, string $codigo): array
    {
        $administrativo = Normaliza::celda(trim((string) $f['custodia_administrativo'], " ,"));
        $asignado = Normaliza::celda($f['asignado']);

        $buscar = function (?string $nombre, string $columna) use ($servidores, $informe, $f, $codigo) {
            if ($nombre === null) {
                return null;
            }
            $r = $servidores->buscar($nombre);
            if ($r['servidor'] && ! $r['servidor']->estado) {
                $informe->sinEmparejar($f, $codigo, $columna, $nombre, 'Está inactivo en el expediente: el equipo queda sin custodio.');

                return null;
            }
            if (! $r['servidor']) {
                $informe->sinEmparejar($f, $codigo, $columna, $nombre, $r['motivo']);
            }

            return $r['servidor'];
        };

        if ($administrativo !== null) {
            return [$buscar($administrativo, 'Custodia administrativo'), $buscar($asignado, 'Asignado')];
        }

        return [$buscar($asignado, 'Asignado'), null];
    }

    /** @return array{0: string, 1: ?string} la condición y, si el Excel decía otra cosa, la nota */
    private function condicion(mixed $estado): array
    {
        $texto = Normaliza::celda($estado);
        $valor = Normaliza::texto($texto);

        return in_array($valor, ['BUENO', 'REGULAR', 'MALO'], true)
            ? [strtolower($valor), null]
            : ['bueno', $texto !== null ? "Estado según el Excel: «{$texto}»." : null];
    }

    /** @param  list<string>  $notas */
    private function observaciones(array $f, array $notas): ?string
    {
        $baja = array_filter([Normaliza::celda($f['memorando_baja']), Normaliza::soporte($f['soporte_baja']) ? 'soporte N.º ' . Normaliza::soporte($f['soporte_baja']) : null]);
        $terremoto = array_filter([Normaliza::soporte($f['soporte_terremoto']) ? 'soporte N.º ' . Normaliza::soporte($f['soporte_terremoto']) : null, Normaliza::celda($f['tecnico_terremoto'])]);

        $lineas = array_filter([
            Normaliza::celda($f['observacion']),
            Normaliza::celda($f['novedades_dominio']) ? 'Dominio: ' . Normaliza::celda($f['novedades_dominio']) : null,
            $baja ? 'Baja de componentes: ' . implode(', ', $baja) . '.' : null,
            $terremoto ? 'Terremoto de abril de 2025: ' . implode(', ', $terremoto) . '.' : null,
            ...$notas,
        ]);

        return $lineas ? implode("\n", $lineas) : null;
    }

    /** Los números de soporte del Excel, como historia sin fecha (D1). */
    private function mantenimientos(BienInformatico $bien, array $f): void
    {
        $registrar = fn (string $tipo, string $descripcion, ?string $soporte) => MantenimientoBien::create([
            'bien_informatico_id' => $bien->id,
            'tipo_mantenimiento'  => $tipo,
            'fecha_mantenimiento' => null,
            'descripcion'         => $descripcion . ' Del Excel de TIC; la fecha no consta.',
            'costo'               => 0,
            'soporte_anterior'    => $soporte,
        ]);

        if ($s = Normaliza::soporte($f['soporte_mantenimiento_2025'])) {
            $tecnico = Normaliza::celda($f['tecnico_mantenimiento_2025']);
            $registrar('preventivo', 'Mantenimiento preventivo de software de 2025' . ($tecnico ? " (técnico: {$tecnico})." : '.'), $s);
        }
        if ($s = Normaliza::soporte($f['vigencia'])) {
            $registrar('preventivo', 'Mantenimiento por vigencia tecnológica.', $s);
        }
        if (Normaliza::texto($f['tipo_disco']) === 'SSD' || Normaliza::soporte($f['soporte_ssd'])) {
            $registrar('repotenciacion', 'Cambio del disco a SSD.', Normaliza::soporte($f['soporte_ssd']));
        }
    }
}
