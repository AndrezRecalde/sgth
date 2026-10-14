<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\CatalogoInventarioServiceInterface;
use App\Contracts\InventarioTi\CustodiaServiceInterface;
use App\Enums\CondicionFisicaBien;
use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\ContratoVigencia;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\SubtipoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los catálogos del inventario (docs/diseno-subsistema-tic.md, 4.2).
 *
 * Hasta la fase 1.1 los tipos, las marcas y los orígenes existían como tablas
 * pero ninguna ruta los creaba: registrar un equipo pedía ids que nadie podía
 * dar de alta.
 *
 * Un registro de catálogo que algún equipo usa no se borra: el equipo se
 * quedaría apuntando a nada en su ficha.
 */
final class CatalogoInventarioService implements CatalogoInventarioServiceInterface
{
    public function __construct(private readonly CustodiaServiceInterface $custodia) {}

    public function paraFormularios(): array
    {
        return [
            'tipos'       => TipoBien::with('subtipos:id,tipo_bien_id,nombre')->orderBy('nombre')->get(['id', 'nombre', 'anios_vida_util', 'ficha']),
            'marcas'      => Marca::orderBy('nombre')->get(['id', 'nombre']),
            'ubicaciones' => Ubicacion::ordenadas()->get(['id', 'sede', 'piso']),
            'contratos'   => ContratoVigencia::orderByDesc('fecha_inicio')->orderBy('proceso')->get(['id', 'proceso', 'fecha_inicio', 'fecha_fin']),
            'estados'     => array_map(fn (EstadoBien $e) => ['valor' => $e->value, 'etiqueta' => $e->etiqueta()], EstadoBien::cases()),
            'condiciones' => array_map(fn (CondicionFisicaBien $c) => ['valor' => $c->value, 'etiqueta' => $c->etiqueta()], CondicionFisicaBien::cases()),
            // Hasta el arranque de la mesa de ayuda se anota el número de
            // soporte del sistema antiguo (D1).
            'acepta_soporte_anterior' => $this->custodia->aceptaSoporteAnterior(),
        ];
    }

    public function listarTipos(): Collection
    {
        return TipoBien::with('subtipos')->withCount('bienes')->orderBy('nombre')->get();
    }

    public function listarMarcas(): Collection
    {
        return Marca::withCount('bienes')->orderBy('nombre')->get();
    }

    public function listarUbicaciones(): Collection
    {
        return Ubicacion::withCount('bienes')->ordenadas()->get();
    }

    public function listarContratos(): Collection
    {
        return ContratoVigencia::with(['administrador:id,nombre,apellido,cedula', 'cronograma'])
            ->withCount('bienes')
            ->orderByDesc('fecha_inicio')
            ->orderBy('proceso')
            ->get();
    }

    public function guardarTipo(?TipoBien $tipo, array $datos): TipoBien
    {
        return $this->guardar($tipo ?? new TipoBien, $datos);
    }

    public function eliminarTipo(TipoBien $tipo): void
    {
        $this->siNoEstaEnUso(BienInformatico::where('tipo_bien_id', $tipo->id)->count(), 'este tipo');

        if ($tipo->subtipos()->exists()) {
            throw new ReglaNegocioException("El tipo «{$tipo->nombre}» tiene subtipos: elimínelos primero.");
        }

        $tipo->delete();
    }

    public function guardarSubtipo(?SubtipoBien $subtipo, array $datos): SubtipoBien
    {
        // Cambiar el tipo de un subtipo en uso dejaría equipos con un subtipo
        // que no es de su tipo.
        if ($subtipo && isset($datos['tipo_bien_id']) && (int) $datos['tipo_bien_id'] !== (int) $subtipo->tipo_bien_id
            && BienInformatico::where('subtipo_bien_id', $subtipo->id)->exists()) {
            throw new ReglaNegocioException("El subtipo «{$subtipo->nombre}» ya está en uso: no puede pasar a otro tipo.");
        }

        return $this->guardar($subtipo ?? new SubtipoBien, $datos);
    }

    public function eliminarSubtipo(SubtipoBien $subtipo): void
    {
        $this->siNoEstaEnUso(BienInformatico::where('subtipo_bien_id', $subtipo->id)->count(), 'este subtipo');
        $subtipo->delete();
    }

    public function guardarMarca(?Marca $marca, array $datos): Marca
    {
        return $this->guardar($marca ?? new Marca, $datos);
    }

    public function eliminarMarca(Marca $marca): void
    {
        $this->siNoEstaEnUso(BienInformatico::where('marca_id', $marca->id)->count(), 'esta marca');
        $marca->delete();
    }

    public function guardarUbicacion(?Ubicacion $ubicacion, array $datos): Ubicacion
    {
        return $this->guardar($ubicacion ?? new Ubicacion, $datos);
    }

    public function eliminarUbicacion(Ubicacion $ubicacion): void
    {
        $this->siNoEstaEnUso(BienInformatico::where('ubicacion_id', $ubicacion->id)->count(), 'esta ubicación');
        $ubicacion->delete();
    }

    /**
     * El contrato con su cronograma. El cronograma llega entero y reemplaza al
     * anterior: son las ventanas de mantenimiento del contrato, no un
     * historial.
     */
    public function guardarContrato(?ContratoVigencia $contrato, array $datos): ContratoVigencia
    {
        return DB::transaction(function () use ($contrato, $datos) {
            $cronograma = $datos['cronograma'] ?? null;
            unset($datos['cronograma']);

            $contrato ??= new ContratoVigencia(['created_by' => auth()->id()]);
            $contrato->fill([...$datos, 'updated_by' => auth()->id()])->save();

            if (is_array($cronograma)) {
                $contrato->cronograma()->delete();
                $contrato->cronograma()->createMany($cronograma);
            }

            return $contrato->load(['administrador:id,nombre,apellido,cedula', 'cronograma'])->loadCount('bienes');
        });
    }

    public function eliminarContrato(ContratoVigencia $contrato): void
    {
        $this->siNoEstaEnUso($contrato->bienes()->count(), 'este contrato');
        $contrato->delete();
    }

    /**
     * @template T of Model
     *
     * @param  T  $modelo
     * @return T
     */
    private function guardar(Model $modelo, array $datos): Model
    {
        $modelo->fill($datos)->save();

        return $modelo;
    }

    private function siNoEstaEnUso(int $equipos, string $que): void
    {
        if ($equipos > 0) {
            throw new ReglaNegocioException(
                "No se puede eliminar: {$equipos} equipo(s) usan {$que}. Cámbielos primero."
            );
        }
    }
}
