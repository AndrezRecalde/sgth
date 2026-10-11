<x-mail::message>
@php($reintegro ??= false)
@if($anulada)
# {{ $reintegro ? 'Reintegro anulado' : 'Terminación anulada' }}

Talento Humano **anuló** {{ $reintegro ? 'el reintegro' : 'la terminación' }} **N.º {{ $numero }}** de **{{ $servidor }}**
(C.I. {{ $cedula }}), cuya liquidación se le envió para pago.

@if($motivo)
**Motivo de la anulación:** {{ $motivo }}
@endif

Si la liquidación ya se pagó, disponga a quien corresponda lo que proceda. Se
adjunta la hoja de registro interno con la marca de anulación.
@elseif($reintegro)
# Reintegro de obrero: remuneraciones pendientes

Talento Humano registró el reintegro **N.º {{ $numero }}** de **{{ $servidor }}**
(C.I. {{ $cedula }}), cuyo despido el juez declaró ineficaz, que rige desde el
{{ $rige }}. Lo ordena la {{ $orden ?? 'orden judicial' }}, en la causa **{{ $causa ?? '—' }}**
({{ $unidadJudicial ?? '—' }}). Disponga a quien corresponda el pago de lo pendiente,
con su recargo (Código del Trabajo, Art. 195.3).
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
