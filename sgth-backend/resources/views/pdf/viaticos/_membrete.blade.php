{{-- El membrete y el pie fijos de los cuatro documentos del viático.

     Los cuatro salen a nombre de la Dirección Financiera, que es donde se
     tramita el viático de principio a fin (decidido el 2026-11-24).

     Hasta este cambio cada plantilla dibujaba su propia cabecera, así que el
     nombre oficial de la institución, la dirección y el teléfono —que mantiene
     Comunicación Social y que cambian con la administración— no aparecían en
     ningún papel de viáticos. --}}
@include('pdf._membrete-institucional', ['dependencia' => 'Dirección Financiera'])

@include('pdf._pie-institucional')
