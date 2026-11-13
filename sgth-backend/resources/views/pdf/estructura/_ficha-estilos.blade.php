{{-- Los estilos de la ficha «Descripción y perfil del puesto»: los usan la
     ficha suelta y el manual por gestión (docs/diseno-manual-de-puestos.md,
     P5, PR 13 y PR 14). Van dentro de un <style> de quien los incluye. --}}
@page { margin: 28px 30px 40px 30px; }

body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 8px;
    color: #1f2937;
    margin: 0;
}

.membrete table { width: 100%; border-collapse: collapse; }
.membrete img { max-height: 42px; }
.membrete .institucion { text-align: center; font-size: 8px; }
.membrete .institucion strong { display: block; font-size: 10px; }

h1 {
    font-size: 11px;
    text-align: center;
    margin: 6px 0 8px 0;
    letter-spacing: 0.5px;
}

table.ficha { width: 100%; border-collapse: collapse; margin-bottom: 6px; table-layout: fixed; }
table.ficha td, table.ficha th {
    border: 0.6px solid #9ca3af;
    padding: 3px 4px;
    vertical-align: top;
}
table.ficha th.seccion {
    background: #2f3a8f;
    color: #fff;
    text-align: center;
    font-size: 8px;
    padding: 4px;
}
table.ficha th.sub {
    background: #e0e3f5;
    color: #1f2a6b;
    font-weight: bold;
    text-align: center;
}
table.ficha td.etiqueta { font-weight: bold; background: #f5f6fb; }
table.ficha tr { page-break-inside: avoid; }
.vacio { color: #9ca3af; }
.centro { text-align: center; }
.texto { white-space: pre-line; }

footer {
    position: fixed;
    bottom: -26px; left: 0; right: 0;
    font-size: 7px;
    color: #6b7280;
}
footer .pagina:after { content: counter(page); }
