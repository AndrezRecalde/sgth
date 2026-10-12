{{-- Estilos de la marca de la vista previa (fase 6.2). Se incluye dentro del
     <style> de cada documento. --}}
        /* La marca de agua va sin rotar: dompdf no rota texto de forma fiable.
           Es un fijo dentro de la hoja —uno con `bottom` negativo se sale—, y
           dompdf pinta los fijos encima del contenido aunque vayan antes: el
           `z-index: -1` es lo que la deja detrás. Un gris claro, no una
           opacidad, que no todos los visores respetan. */
        .marca-borrador {
            position: fixed;
            top: 42%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 92px;
            font-weight: bold;
            letter-spacing: 14px;
            color: #edf1ef;
            z-index: -1;
        }
        /* Una franja de una línea: un recuadro como el de la anulada empujaba
           las firmas y el registro a otra hoja. */
        .sello-borrador {
            border: 1.5px solid #9a6400;
            background: #fff7e6;
            padding: 4px 10px;
            margin-bottom: 8px;
            font-size: 9px;
            color: #5e4300;
        }
        .sello-borrador strong {
            color: #8a5a00;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .firma-provisional { font-size: 8.5px; color: #8a5a00; font-style: italic; margin: 2px 0 0 0; }
