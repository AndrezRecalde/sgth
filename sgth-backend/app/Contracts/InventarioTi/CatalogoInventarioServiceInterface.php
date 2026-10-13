<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\ContratoVigencia;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\SubtipoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;

/** Los catálogos del inventario, que TIC mantiene desde Configuración. */
interface CatalogoInventarioServiceInterface
{
    /** Todo lo que el formulario de un equipo necesita, en una sola consulta. */
    public function paraFormularios(): array;

    /** Los listados de Configuración, con cuántos equipos usa cada registro. */
    public function listarTipos(): \Illuminate\Support\Collection;

    public function listarMarcas(): \Illuminate\Support\Collection;

    public function listarUbicaciones(): \Illuminate\Support\Collection;

    public function listarContratos(): \Illuminate\Support\Collection;

    public function guardarTipo(?TipoBien $tipo, array $datos): TipoBien;

    public function eliminarTipo(TipoBien $tipo): void;

    public function guardarSubtipo(?SubtipoBien $subtipo, array $datos): SubtipoBien;

    public function eliminarSubtipo(SubtipoBien $subtipo): void;

    public function guardarMarca(?Marca $marca, array $datos): Marca;

    public function eliminarMarca(Marca $marca): void;

    public function guardarUbicacion(?Ubicacion $ubicacion, array $datos): Ubicacion;

    public function eliminarUbicacion(Ubicacion $ubicacion): void;

    public function guardarContrato(?ContratoVigencia $contrato, array $datos): ContratoVigencia;

    public function eliminarContrato(ContratoVigencia $contrato): void;
}
