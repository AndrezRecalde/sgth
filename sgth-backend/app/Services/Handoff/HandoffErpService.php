<?php

namespace App\Services\Handoff;

use App\Contracts\Handoff\HandoffErpServiceInterface;
use App\Models\Handoff\HandoffErp;
use App\Models\Nomina\Nomina;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\Viatico;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HandoffErpService implements HandoffErpServiceInterface
{
    /**
     * Genera un archivo de integración (Handoff) XML con la información de la nómina
     * y asegura su integridad generando un hash SHA-256.
     */
    public function generarHandoffNomina(int $nominaId): HandoffErp
    {
        return DB::transaction(function () use ($nominaId) {
            $nomina = Nomina::with(['rolesPago.servidor', 'detalles.concepto'])->findOrFail($nominaId);

            // 1. Construir el XML
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><HandoffNomina></HandoffNomina>');
            $xml->addChild('Periodo', $nomina->periodo);
            $xml->addChild('FechaInicio', $nomina->fecha_inicio->format('Y-m-d'));
            $xml->addChild('FechaFin', $nomina->fecha_fin->format('Y-m-d'));
            $xml->addChild('TotalIngresos', $nomina->total_ingresos);
            $xml->addChild('TotalDescuentos', $nomina->total_descuentos);
            $xml->addChild('TotalNeto', $nomina->total_neto);

            $roles = $xml->addChild('RolesPago');
            foreach ($nomina->rolesPago as $rol) {
                $item = $roles->addChild('Rol');
                $item->addChild('Cedula', $rol->servidor->cedula);
                $item->addChild('Nombres', htmlspecialchars($rol->servidor->nombre_completo));
                $item->addChild('Ingresos', $rol->total_ingresos);
                $item->addChild('Descuentos', $rol->total_descuentos);
                $item->addChild('Neto', $rol->total_neto);
                
                $conceptosXml = $item->addChild('Conceptos');
                $detallesServidor = $nomina->detalles->where('servidor_id', $rol->servidor_id);
                
                foreach ($detallesServidor as $detalle) {
                    $conceptoXml = $conceptosXml->addChild('Concepto');
                    $conceptoXml->addChild('Codigo', $detalle->concepto->codigo);
                    $conceptoXml->addChild('Nombre', htmlspecialchars($detalle->concepto->nombre));
                    $conceptoXml->addChild('Tipo', $detalle->concepto->tipo->value);
                    $conceptoXml->addChild('Valor', $detalle->valor);
                }
            }

            $xmlContent = $xml->asXML();

            // 2. Calcular hash SHA-256
            $hash = hash('sha256', $xmlContent);

            // 3. Guardar el archivo en storage/app/handoff/
            $nombreArchivo = "nomina_{$nomina->periodo}_" . Str::random(8) . ".xml";
            $rutaArchivo = "handoff/{$nombreArchivo}";
            
            Storage::put($rutaArchivo, $xmlContent);

            // 4. Registrar en la base de datos (inmutable)
            $handoff = HandoffErp::create([
                'tipo'            => 'nomina',
                'referencia_id'   => $nomina->id,
                'archivo_nombre'  => $nombreArchivo,
                'archivo_ruta'    => $rutaArchivo,
                'hash_integridad' => $hash,
                'generado_por'    => $nomina->cerrado_por ?? 1, // En contexto asíncrono asume el cerrador
                'generado_en'     => now(),
            ]);

            return $handoff;
        });
    }

    public function generarHandoffCompromisoViatico(int $viaticoId): HandoffErp
    {
        return DB::transaction(function () use ($viaticoId) {
            // El itinerario dejó de ser una lista de destinos sueltos: hoy son
            // tramos, cada uno con su origen, su destino y su empresa. La
            // relación `destinos` desapareció con esa refactorización y este
            // método seguía pidiéndola, así que reventaba con sortBy() sobre
            // null en cuanto se lo llamaba.
            $viatico = Viatico::with([
                'servidor',
                'tramos.destinoCanton',
                'tramos.destinoProvincia',
            ])->findOrFail($viaticoId);

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><HandoffViatico tipo="compromiso"></HandoffViatico>');
            $xml->addChild('NumeroResolucion', htmlspecialchars($viatico->numero_resolucion ?? ''));

            $servidor = $xml->addChild('Servidor');
            $servidor->addChild('Cedula', $viatico->servidor->cedula);
            $servidor->addChild('Nombres', htmlspecialchars($viatico->servidor->nombre_completo));

            $destinosTexto = $viatico->tramos
                ->map(function ($tramo) {
                    if ($tramo->destino_tipo === 'nacional') {
                        return trim(implode(' - ', array_filter([
                            $tramo->destinoCanton?->nombre ?? $tramo->destino_ciudad,
                            $tramo->destinoProvincia?->nombre,
                        ])));
                    }

                    return trim(implode(' - ', array_filter([
                        $tramo->destino_ciudad,
                        $tramo->destino_pais,
                    ])));
                })
                ->filter()
                ->join(', ');

            $xml->addChild('Destino', htmlspecialchars($destinosTexto));
            $xml->addChild('FechaInicio', $viatico->datetime_salida?->format('Y-m-d') ?? '');
            $xml->addChild('FechaFin', $viatico->datetime_llegada?->format('Y-m-d') ?? '');
            $xml->addChild('MontoAprobado', $viatico->monto_anticipo);
            $xml->addChild('PartidaPresupuestaria', htmlspecialchars($viatico->partida_presupuestaria ?? ''));

            $xmlContent = $xml->asXML();
            $hash = hash('sha256', $xmlContent);

            $nombreArchivo = "viatico_compromiso_{$viaticoId}_" . Str::random(8) . ".xml";
            $rutaArchivo = "handoff/{$nombreArchivo}";
            
            Storage::put($rutaArchivo, $xmlContent);

            return HandoffErp::create([
                'tipo'            => 'viatico_compromiso',
                'referencia_id'   => $viatico->id,
                'archivo_nombre'  => $nombreArchivo,
                'archivo_ruta'    => $rutaArchivo,
                'hash_integridad' => $hash,
                'generado_por'    => $viatico->updated_by ?? 1,
                'generado_en'     => now(),
            ]);
        });
    }

    public function generarHandoffDevengadoViatico(int $liquidacionId): HandoffErp
    {
        return DB::transaction(function () use ($liquidacionId) {
            $liquidacion = LiquidacionViatico::with([
                'viatico.servidor',
                'detallesFactura',
            ])->findOrFail($liquidacionId);
            $viatico = $liquidacion->viatico;

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><HandoffViatico tipo="devengado"></HandoffViatico>');
            $xml->addChild('NumeroResolucion', htmlspecialchars($viatico->numero_resolucion ?? ''));
            
            $servidor = $xml->addChild('Servidor');
            $servidor->addChild('Cedula', $viatico->servidor->cedula);
            $servidor->addChild('Nombres', htmlspecialchars($viatico->servidor->nombre_completo));
            
            // Las facturas salieron de un JSON dentro de la liquidación y
            // pasaron a su propia tabla, con categoría y tipo de comprobante.
            // Este método seguía leyendo la columna vieja.
            $facturasXml = $xml->addChild('Facturas');
            foreach ($liquidacion->detallesFactura as $factura) {
                $f = $facturasXml->addChild('Factura');
                $f->addChild('Numero', htmlspecialchars(
                    $factura->numero_factura ?? $factura->numero_ticket ?? ''
                ));
                $f->addChild('Proveedor', htmlspecialchars($factura->nombre_proveedor ?? ''));
                $f->addChild('Monto', number_format((float) $factura->monto, 2, '.', ''));
            }

            $xml->addChild('TotalFacturas', $liquidacion->total_facturas);
            $xml->addChild('DiferenciaDevolver', $liquidacion->diferencia_devolver);

            $xmlContent = $xml->asXML();
            $hash = hash('sha256', $xmlContent);

            $nombreArchivo = "viatico_devengado_{$liquidacionId}_" . Str::random(8) . ".xml";
            $rutaArchivo = "handoff/{$nombreArchivo}";
            
            Storage::put($rutaArchivo, $xmlContent);

            return HandoffErp::create([
                'tipo'            => 'viatico_devengado',
                'referencia_id'   => $liquidacion->id,
                'archivo_nombre'  => $nombreArchivo,
                'archivo_ruta'    => $rutaArchivo,
                'hash_integridad' => $hash,
                'generado_por'    => $liquidacion->created_by ?? 1,
                'generado_en'     => now(),
            ]);
        });
    }
}
