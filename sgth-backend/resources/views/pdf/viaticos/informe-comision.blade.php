<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Servicios Institucionales</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .header img { max-height: 60px; margin-bottom: 10px; }
        .header h2 { margin: 0; font-size: 14px; text-transform: uppercase; }
        .header p { margin: 5px 0 0 0; font-size: 12px; }
        .section-title { background-color: #f0f0f0; padding: 5px; font-weight: bold; border: 1px solid #000; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: left; }
        th { background-color: #f9f9f9; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-20 { margin-top: 20px; }
        .signatures { width: 100%; margin-top: 50px; page-break-inside: avoid; }
        .signature-box { width: 33%; display: inline-block; text-align: center; vertical-align: top; }
        .signature-line { border-top: 1px solid #000; margin: 0 20px; padding-top: 5px; }
    </style>
</head>
<body>

    <div class="header">
        @if(file_exists(public_path('images/logo-gad.png')))
            <img src="{{ public_path('images/logo-gad.png') }}" alt="Logo">
        @else
            <h2>GAD PROVINCIAL DE ESMERALDAS</h2>
        @endif
        <h2>INFORME DE SERVICIOS INSTITUCIONALES</h2>
        <p>Viático No. {{ $viatico->codigo_viatico }}</p>
    </div>

    <div class="section-title">DATOS GENERALES DEL SERVIDOR</div>
    <table>
        <tr>
            <th>Nombre Completo:</th>
            <td>{{ optional($viatico->servidor)->nombre_completo }}</td>
            <th>Cédula:</th>
            <td>{{ optional($viatico->servidor)->identificacion }}</td>
        </tr>
        <tr>
            <th>Cargo:</th>
            <td>{{ optional(optional($viatico->servidor)->puesto)->denominacion }}</td>
            <th>Unidad Administrativa:</th>
            <td>{{ optional(optional(optional($viatico->servidor)->puesto)->unidadAdministrativa)->nombre }}</td>
        </tr>
        <tr>
            <th>Régimen Laboral:</th>
            <td>{{ optional($viatico->servidor)->regimen_laboral ?? 'LOSEP' }}</td>
            <th>Fecha del Informe:</th>
            <td>{{ date('Y-m-d') }}</td>
        </tr>
    </table>

    <div class="section-title">DATOS DE LA COMISIÓN</div>
    <table>
        <tr>
            <th>Motivo de la Comisión:</th>
            <td colspan="3">{{ optional($viatico->comision)->motivo ?? $viatico->justificacion }}</td>
        </tr>
        <tr>
            <th>Fecha Inicio:</th>
            <td>{{ $viatico->fecha_inicio ? $viatico->fecha_inicio->format('Y-m-d H:i') : '' }}</td>
            <th>Fecha Fin:</th>
            <td>{{ $viatico->fecha_fin ? $viatico->fecha_fin->format('Y-m-d H:i') : '' }}</td>
        </tr>
        <tr>
            <th>Código Comisión:</th>
            <td colspan="3">{{ optional($viatico->comision)->codigo_comision ?? 'N/A' }}</td>
        </tr>
    </table>

    <div class="section-title">ITINERARIO</div>
    <table>
        <thead>
            <tr>
                <th>Orden</th>
                <th>Destino</th>
                <th>Fecha Llegada</th>
                <th>Fecha Salida</th>
                <th>Días</th>
            </tr>
        </thead>
        <tbody>
            @forelse($viatico->destinos->sortBy('orden') as $destino)
            <tr>
                <td class="text-center">{{ $destino->orden }}</td>
                <td>{{ $destino->ciudad_id ? optional($destino->ciudad)->nombre : $destino->pais }}</td>
                <td class="text-center">{{ $destino->fecha_llegada ? \Carbon\Carbon::parse($destino->fecha_llegada)->format('Y-m-d H:i') : 'N/A' }}</td>
                <td class="text-center">{{ $destino->fecha_salida ? \Carbon\Carbon::parse($destino->fecha_salida)->format('Y-m-d H:i') : 'N/A' }}</td>
                <td class="text-center">{{ $destino->dias_pernocte ?? 0 }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">No hay destinos registrados.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">TRANSPORTES UTILIZADOS</div>
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Destino</th>
                <th>Fecha</th>
                <th>Empresa/Placa</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($viatico->transportes as $transporte)
            <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $transporte->tipo)) }}</td>
                <td>{{ $transporte->ciudad_origen_id ? optional($transporte->ciudadOrigen)->nombre : $transporte->pais_origen }}</td>
                <td>{{ $transporte->ciudad_destino_id ? optional($transporte->ciudadDestino)->nombre : $transporte->pais_destino }}</td>
                <td class="text-center">{{ $transporte->fecha_viaje ? \Carbon\Carbon::parse($transporte->fecha_viaje)->format('Y-m-d H:i') : '' }}</td>
                <td>{{ $transporte->empresa_o_aerolinea ?? $transporte->placa_vehiculo ?? 'N/A' }}</td>
                <td class="text-right">${{ number_format($transporte->monto ?? 0, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center">No hay transportes registrados.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">ACTIVIDADES REALIZADAS</div>
    <div style="padding: 10px; border: 1px solid #000; margin-top: 5px;">
        {{ $viatico->justificacion ?? optional($viatico->comision)->motivo ?? 'Sin descripción de actividades.' }}
    </div>

    @if($viatico->liquidacion)
    <div class="section-title">RESUMEN FINANCIERO (FACTURAS)</div>
    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Detalle</th>
                <th>N° Factura</th>
                <th>Proveedor</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($viatico->liquidacion->detallesFactura as $factura)
            <tr>
                <td>{{ $factura->concepto ? $factura->concepto->etiqueta() : 'N/A' }}</td>
                <td>{{ $factura->detalle }}</td>
                <td>{{ $factura->numero_factura }}</td>
                <td>{{ $factura->ruc_proveedor }} - {{ $factura->nombre_proveedor }}</td>
                <td class="text-right">${{ number_format($factura->monto, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">No se presentaron facturas.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-right">Total Facturas Presentadas:</th>
                <th class="text-right">${{ number_format($viatico->liquidacion->total_facturas ?? 0, 2) }}</th>
            </tr>
            <tr>
                <th colspan="4" class="text-right">Anticipo Recibido:</th>
                <th class="text-right">${{ number_format($viatico->monto_anticipo ?? 0, 2) }}</th>
            </tr>
            <tr>
                <th colspan="4" class="text-right">Diferencia (a devolver o cobrar):</th>
                <th class="text-right">${{ number_format($viatico->liquidacion->diferencia_devolver ?? 0, 2) }}</th>
            </tr>
        </tfoot>
    </table>
    @endif

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Servidor Comisionado</strong><br>
            {{ optional($viatico->servidor)->nombre_completo }}<br>
            {{ optional(optional($viatico->servidor)->puesto)->denominacion }}<br>
            Fecha: ___________
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Jefe Inmediato</strong><br>
            (Nombre del Jefe)<br>
            Cargo<br>
            Fecha: ___________
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <strong>Director Financiero</strong><br>
            (Nombre del Director)<br>
            Director Financiero<br>
            Fecha: ___________
        </div>
    </div>

</body>
</html>
