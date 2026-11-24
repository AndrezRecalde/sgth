{{-- El estilo de los cuatro documentos del viático.

     Hasta el 2026-11-24 cada plantilla llevaba sus propias ~250 líneas de CSS,
     en azul marino y con barras de sección rellenas: viáticos era el único
     módulo fuera del estilo de los demás documentos institucionales. Esto es
     el mismo sistema que usan la acción de personal, las actas del inventario
     y los reportes de TIC, con el verde institucional.

     Cuatro copias del mismo CSS fue también la razón de que ajustar el
     comprobante para que cupiera en una hoja no llegara a los otros tres. --}}
<style>
    /* dompdf no soporta flexbox ni grid: la maquetación va con tablas. */
    @page { margin: 26mm 14mm 20mm 14mm; }

    body {
        font-family: "DejaVu Sans", Arial, sans-serif;
        font-size: 10.5px;
        line-height: 1.45;
        color: #1c2321;
        margin: 0;
        padding: 0;
    }

    /* ── Membrete y pie, fijos en todas las hojas ───────────────── */
    /* Con `body { padding }`, que es lo que había antes, una segunda hoja
       salía sin membrete: un itinerario largo o una liquidación con muchos
       comprobantes desbordan. */
    .membrete { position: fixed; top: -21mm; left: 0; right: 0; height: 17mm; }
    .membrete table { width: 100%; border-collapse: collapse; }
    .membrete td { vertical-align: middle; border: none; padding: 0; }
    .membrete img { max-height: 15mm; }
    .membrete .institucion {
        text-align: right;
        font-size: 9.5px;
        color: #5b6b63;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .membrete .institucion strong {
        display: block;
        font-size: 11px;
        color: #17593f;
        letter-spacing: 0;
        margin-bottom: 1px;
    }

    .footer {
        position: fixed;
        bottom: -14mm; left: 0; right: 0;
        text-align: center;
        font-size: 11px;
        color: #5b6b63;
        border-top: 0.7px solid #cfdad4;
        padding-top: 4px;
    }

    /* ── Título del acto ────────────────────────────────────────── */
    .titulo { text-align: center; margin: 0 0 10px 0; }
    .titulo .supra { font-size: 9px; letter-spacing: 2px; color: #5b6b63; text-transform: uppercase; }
    .titulo h1 {
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        margin: 2px 0 0 0;
        color: #17593f;
    }
    .titulo .subtipo { font-size: 11px; color: #3d4d46; margin-top: 1px; }
    .titulo .regla { border-bottom: 1.6px solid #17593f; width: 56px; margin: 5px auto 0 auto; }

    /* ── Cintillo de código y fecha ─────────────────────────────── */
    .cintillo { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .cintillo td { border: none; padding: 0; font-size: 9.5px; }
    .cintillo .codigo {
        border: 1px solid #17593f;
        border-radius: 2px;
        padding: 4px 9px;
        font-weight: bold;
        font-size: 11px;
        color: #17593f;
        letter-spacing: 0.5px;
    }
    .cintillo .lugar-fecha { text-align: right; color: #3d4d46; }

    /* ── Secciones ──────────────────────────────────────────────── */
    .bloque-titulo {
        font-size: 9px;
        font-weight: bold;
        letter-spacing: 1.4px;
        text-transform: uppercase;
        color: #17593f;
        border-bottom: 0.7px solid #cfdad4;
        padding-bottom: 3px;
        margin: 10px 0 5px 0;
        page-break-after: avoid;
    }
    .bloque-titulo:first-of-type { margin-top: 0; }

    /* ── Tablas de datos ────────────────────────────────────────── */
    table.datos { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.datos td {
        border: 0.7px solid #c6d2cc;
        padding: 4px 8px;
        font-size: 10px;
        vertical-align: middle;
    }
    table.datos td.label {
        font-weight: bold;
        font-size: 8.5px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #3d4d46;
        background: #f2f6f4;
        width: 21%;
    }
    /* Los nombres van en mayúscula por CSS, para no alterar el dato
       guardado: `strtoupper` además se come las tildes. */
    table.datos td.mayus { text-transform: uppercase; }
    table.datos td.fuerte { font-weight: bold; }

    /* ── Listados ───────────────────────────────────────────────── */
    table.listado { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    /* Repite los títulos de columna si el listado pasa a la hoja siguiente. */
    table.listado thead { display: table-header-group; }
    table.listado th {
        background: #f2f6f4;
        border: 0.7px solid #c6d2cc;
        padding: 4px 6px;
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #3d4d46;
        text-align: left;
    }
    table.listado td { border: 0.7px solid #c6d2cc; padding: 3px 6px; font-size: 9.5px; vertical-align: top; }
    table.listado tr { page-break-inside: avoid; }
    table.listado .num, table.listado th.num { text-align: right; white-space: nowrap; }
    table.listado tr.total td { font-weight: bold; background: #f6faf8; }
    .vacio { color: #9aa8a1; font-style: italic; }

    /* ── Cifras ─────────────────────────────────────────────────── */
    table.cifras { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.cifras td { border: 0.7px solid #c6d2cc; padding: 3px 8px; font-size: 10px; }
    table.cifras td.val { text-align: right; white-space: nowrap; width: 26%; }
    table.cifras tr.total td { background: #f2f6f4; font-weight: bold; }
    /* El total del documento cierra en verde: entre filas iguales se perdía. */
    table.cifras tr.cierre td {
        background: #17593f;
        color: #ffffff;
        font-weight: bold;
        font-size: 11px;
        border-color: #17593f;
    }

    /* ── Párrafos y avisos ──────────────────────────────────────── */
    .parrafo { text-align: justify; font-size: 10.5px; line-height: 1.55; margin: 0 0 6px 0; }
    .clausula {
        text-align: justify;
        font-size: 10px;
        line-height: 1.6;
        padding: 8px 12px;
        border-left: 2.5px solid #17593f;
        background: #f6faf8;
        margin: 10px 0 0 0;
    }
    .aviso {
        text-align: justify;
        font-size: 10px;
        line-height: 1.6;
        padding: 8px 12px;
        border-left: 2.5px solid #8a5a00;
        background: #fff7e6;
        color: #6b4600;
        margin-bottom: 4px;
    }

    /* ── Firmas ─────────────────────────────────────────────────── */
    /* El rótulo va arriba: así el espacio para firmar queda antes de la
       línea, y las líneas de las tres columnas caen a la misma altura
       aunque un nombre ocupe dos renglones. */
    .firmas { width: 100%; margin-top: 10px; page-break-inside: avoid; }
    .firmas table { width: 100%; border-collapse: collapse; }
    .firmas td { text-align: center; vertical-align: top; border: none; padding: 0 8px; }
    .firma-rotulo {
        font-size: 8.5px;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #6b7c74;
        margin-bottom: 24px;
    }
    .firma-linea { border-top: 0.9px solid #1c2321; width: 92%; margin: 0 auto 4px auto; }
    .firma-nombre { font-weight: bold; font-size: 9.5px; margin: 0; text-transform: uppercase; }
    .firma-cargo { font-size: 9px; color: #3d4d46; margin: 1px 0 0 0; }
    .firma-aviso { font-size: 8.5px; color: #8a5a00; margin: 3px 0 0 0; font-style: italic; }

    .nota { font-size: 9px; color: #5b6b63; line-height: 1.55; margin-top: 10px; }
</style>
