<x-mail::message>
@if($anulada)
# Terminación anulada

Talento Humano **anuló** la terminación **N.º {{ $numero }}** de **{{ $servidor }}**
(C.I. {{ $cedula }}), cuya liquidación se le envió para pago.

@if($motivo)
**Motivo de la anulación:** {{ $motivo }}
@endif

Si la liquidación ya se pagó, disponga a quien corresponda lo que proceda. Se
adjunta la hoja de registro interno con la marca de anulación.
@else
# Liquidación de obrero para pago

Talento Humano registró la terminación **N.º {{ $numero }}** del contrato de
**{{ $servidor }}** (C.I. {{ $cedula }}), por **{{ $causal }}**, que rige desde el
{{ $rige }}. Disponga a quien corresponda su liquidación.
@endif

<x-mail::table>
| Rubro | Detalle | Valor |
|:--|:--|--:|
@foreach($rubros as $rubro)
| {{ $rubro['concepto'] }} ({{ $rubro['base_legal'] }}) | {{ $rubro['detalle'] }} | {{ $rubro['monto'] }} |
@endforeach
| **Total referencial** | | **{{ $total }}** |
</x-mail::table>

{{ $nota }}

Unidad de Administración del Talento Humano<br>
GAD Provincial de Esmeraldas
</x-mail::message>
