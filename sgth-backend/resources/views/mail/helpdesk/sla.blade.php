<x-mail::message>
# {{ $aviso === 'vencido' ? 'SLA vencido' : 'SLA por vencer' }}

@if($aviso === 'vencido')
El ticket **{{ $folio }}** pasó su plazo de resolución ({{ $vence }}) y subió al **nivel {{ $nivel }}**. Revise si hay que reasignarlo.
@else
Al ticket **{{ $folio }}** le queda poco de su plazo de resolución: vence el **{{ $vence }}**.
@endif

- **Asunto:** {{ $asunto }}
- **Solicitante:** {{ $solicitante }}
- **Prioridad:** {{ $prioridad }}
- **Estado:** {{ $estado }}
- **Técnico:** {{ $tecnico ?? 'Sin asignar' }}

<x-mail::button :url="$url">
Ver el ticket
</x-mail::button>

Mesa de ayuda de TIC<br>
GAD Provincial de Esmeraldas
</x-mail::message>
