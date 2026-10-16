{{-- Los estilos de la tarjeta, comunes al PDF CR80 y a la hoja A4
     (docs/diseno-comunicacion-social.md, 6.2). Todo en milímetros y en
     posición absoluta dentro de .tarjeta: dompdf no maqueta con flex y una
     tarjeta de 54 × 85,6 mm no admite que el flujo empuje nada. --}}
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; }

    .pagina { position: relative; overflow: hidden; }

    .tarjeta {
        position: absolute;
        width: {{ \App\Services\Comunicacion\PdfCredencialService::ANCHO }}mm;
        height: {{ \App\Services\Comunicacion\PdfCredencialService::ALTO }}mm;
        overflow: hidden;
        background: #ffffff;
    }
    /* La guía de corte de la hoja A4; en PVC la tarjeta ya viene cortada. */
    .tarjeta.con-guia { border: 0.2pt dashed #9ca3af; }

    .tarjeta .logo { position: absolute; }
    .tarjeta .logo-texto {
        position: absolute; top: 5mm; left: 4mm; width: 46mm;
        text-align: center; font-size: 10pt; font-weight: bold; letter-spacing: 0.5pt;
    }

    .tarjeta .foto {
        position: absolute; top: 18mm; left: 13.5mm; width: 27mm; height: 36mm;
        background: #e5e7eb; border: 0.3mm solid #cbd5e1;
    }
    .tarjeta .foto img { width: 27mm; height: 36mm; }

    .tarjeta .servidor {
        position: absolute; top: 56.5mm; left: 3mm; width: 48mm; height: 23mm;
        overflow: hidden; text-align: center;
    }
    .tarjeta .nombres,
    .tarjeta .apellidos { font-size: 8.5pt; font-weight: bold; line-height: 1.12; }
    .tarjeta .apellidos { text-transform: uppercase; }
    .tarjeta .cargo { font-size: 6.5pt; line-height: 1.15; margin-top: 1.2mm; }
    .tarjeta .unidad { font-size: 6pt; line-height: 1.15; color: #4b5563; margin-top: 0.6mm; }
    .tarjeta .cedula { font-size: 6.5pt; margin-top: 0.8mm; }

    .tarjeta .banda {
        position: absolute; left: 0; top: 80.6mm; width: 54mm; height: 5mm;
        background: #334155; color: #ffffff;
        font-size: 5.5pt; line-height: 5mm; text-align: center; letter-spacing: 0.3pt;
    }

    /* El fondo de la cara (V7 y V8): a sangre, detrás de todo. */
    .tarjeta .fondo { position: absolute; left: 0; top: 0; width: 54mm; height: 85.6mm; }
    /* Sobre un fondo el texto va en un panel claro: se lee sobre cualquier imagen. */
    .tarjeta.con-fondo .servidor,
    .tarjeta.con-fondo .reverso-datos,
    .tarjeta.con-fondo .aviso {
        background: rgba(255, 255, 255, 0.86);
        border-radius: 1.5mm;
    }
    /* Sin franja abajo, el panel toma la altura de lo que lleva. */
    .tarjeta.con-fondo .servidor { top: 56mm; height: auto; padding: 0.8mm 0 1mm; }
    .tarjeta.con-fondo .reverso-datos { height: auto; padding: 1mm 0 0.4mm; }
    .tarjeta.con-fondo .aviso { top: 63mm; height: auto; padding: 0.8mm 0 1mm; }
    .tarjeta.con-fondo .qr-texto { background: rgba(255, 255, 255, 0.86); border-radius: 1mm; }

    /* Reverso */
    .tarjeta .marca-agua { position: absolute; top: 34mm; left: 9mm; width: 36mm; height: 36mm; opacity: 0.07; }
    .tarjeta .qr { position: absolute; top: 5mm; left: 14.5mm; width: 25mm; height: 25mm; }
    .tarjeta .qr img { width: 25mm; height: 25mm; }
    .tarjeta .qr-texto {
        position: absolute; top: 31mm; left: 4mm; width: 46mm;
        font-size: 5.5pt; text-align: center; color: #4b5563;
    }
    .tarjeta .reverso-datos {
        position: absolute; top: 36.5mm; left: 4mm; width: 46mm; height: 24mm;
        overflow: hidden; text-align: center; font-size: 6.5pt; line-height: 1.25;
    }
    .tarjeta .reverso-datos .fila { margin-bottom: 1.4mm; }
    .tarjeta .etiqueta { font-size: 5.5pt; color: #4b5563; text-transform: uppercase; letter-spacing: 0.2pt; }
    .tarjeta .dato { font-size: 8pt; font-weight: bold; }
    .tarjeta .aviso {
        position: absolute; top: 62mm; left: 3.5mm; width: 47mm; height: 17.5mm;
        overflow: hidden; font-size: 5.3pt; line-height: 1.25; text-align: center; color: #374151;
    }
</style>
