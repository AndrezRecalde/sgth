<x-mail::message>
# Novedades para nómina de {{ $mes }}

Talento Humano entregó las novedades del mes para el rol de pagos: quién entra
y quién sale, los cambios de remuneración, los días sin remuneración, las
subrogaciones y los encargos, las multas y lo que se paga por una restitución.
Disponga a quien corresponda su aplicación. Se adjunta el detalle en Excel, con
el acto de cada una.

@if($novedades === 0 && $ajustes === 0)
Este mes no hay novedades.
@else
<x-mail::table>
| Novedad | Cantidad | Monto referencial |
|:--|--:|--:|
@foreach($resumen as $fila)
| {{ $fila['etiqueta'] }} | {{ $fila['cantidad'] }} | {{ $fila['monto'] }} |
@endforeach
</x-mail::table>
@endif

@if($ajustes > 0)
Incluye **{{ $ajustes }} {{ $ajustes === 1 ? 'ajuste' : 'ajustes' }}** de meses
ya entregados: lo que cambió en ellos después de su entrega.
@endif

Los montos son una **referencia** calculada por el sistema de Talento Humano,
en meses de treinta días. El valor definitivo lo establece Financiero en su
sistema de nómina.

Unidad de Administración del Talento Humano<br>
GAD Provincial de Esmeraldas
</x-mail::message>
