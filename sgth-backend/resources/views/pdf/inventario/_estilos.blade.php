{{-- El estilo de los documentos del inventario de TIC: el acta de entrega y
     el informe técnico de baja. dompdf no soporta flexbox ni grid. --}}
    <style>
        /* dompdf no soporta flexbox ni grid: toda la maquetación va con
           tablas y bordes, que es lo que renderiza de forma predecible. */
        @page { margin: 26mm 14mm 20mm 14mm; }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10.5px;
            line-height: 1.45;
            color: #1c2321;
            margin: 0;
            padding: 0;
        }

        /* ── Encabezado y pie fijos en todas las páginas ───────── */
        .membrete {
            position: fixed;
            top: -21mm; left: 0; right: 0;
            height: 17mm;
        }
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

        /* ── Cintillo de número y fecha ────────────────────────── */
        .cintillo { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .cintillo td { border: none; padding: 0; font-size: 10px; }
        .cintillo .codigo {
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #17593f;
        }
        .cintillo .lugar-fecha { text-align: right; color: #3d4d46; }

        /* ── Título del acto ──────────────────────────────────── */
        .titulo { text-align: center; margin: 0 0 14px 0; }
        .titulo .supra {
            font-size: 9px;
            letter-spacing: 2px;
            color: #5b6b63;
            text-transform: uppercase;
        }
        .titulo h1 {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin: 2px 0 0 0;
            color: #17593f;
        }

        /* ── Secciones de datos ───────────────────────────────── */
        .seccion { margin-bottom: 12px; }
        .seccion h2 {
            font-size: 9px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: #6b7c74;
            margin: 0 0 4px 0;
            padding-bottom: 2px;
            border-bottom: 0.7px solid #cfdad4;
            font-weight: bold;
        }

        table.datos { width: 100%; border-collapse: collapse; }
        table.datos td {
            border: 0.7px solid #cfdad4;
            padding: 4px 7px;
            vertical-align: top;
        }
        table.datos td.rotulo {
            width: 22%;
            background: #f6faf8;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #5b6b63;
        }

        .parrafo {
            text-align: justify;
            font-size: 10.5px;
            line-height: 1.6;
            margin: 0 0 10px 0;
        }

        .clausula {
            text-align: justify;
            font-size: 10px;
            line-height: 1.6;
            padding: 8px 12px;
            border-left: 2.5px solid #17593f;
            background: #f6faf8;
            margin-bottom: 14px;
        }

        /* Un bien ya devuelto no puede imprimirse como si siguiera bajo
           custodia: el aviso va en rojo y antes de las firmas. */
        .devuelto {
            text-align: justify;
            font-size: 10px;
            line-height: 1.6;
            padding: 8px 12px;
            border-left: 2.5px solid #8c2f26;
            background: #fdf5f4;
            color: #6d251e;
            margin-bottom: 14px;
        }

        /* ── Firmas ───────────────────────────────────────────── */
        .firmas { width: 100%; margin-top: 30px; }
        .firmas table { width: 100%; border-collapse: collapse; }
        .firmas td {
            text-align: center;
            vertical-align: bottom;
            border: none;
            padding: 0 12px;
        }
        .firma-rotulo {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #6b7c74;
            margin-bottom: 46px;
        }
        .firma-linea {
            border-top: 0.9px solid #1c2321;
            width: 84%;
            margin: 0 auto 4px auto;
        }
        .firma-nombre { font-weight: bold; font-size: 10.5px; margin: 0; }
        .firma-cargo { font-size: 9.5px; color: #3d4d46; margin: 1px 0 0 0; }
    </style>
