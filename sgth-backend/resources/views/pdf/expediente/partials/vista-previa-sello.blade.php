{{-- El sello de la vista previa (fase 6.2; TH 27): una franja de una línea, para
     no empujar las firmas a otra hoja. Recibe `$movimiento`. La marca de agua va
     aparte, al principio del cuerpo (vista-previa-marca). --}}
<div class="sello-borrador">
    <strong>Borrador — vista previa sin validez.</strong>
    {{ $movimiento->estado === \App\Enums\EstadoAccionPersonal::SUSCRITA
        ? 'Está suscrita y todavía no se registra: no tiene número y no surte efecto.'
        : 'Todavía no se suscribe: no tiene número ni firmantes sellados, y no surte efecto.' }}
</div>
