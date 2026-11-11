# Importación del Manual de Puestos 2018-2019

Diseño: `docs/diseno-manual-de-puestos.md`, P4. TH confirmó que el manual sigue vigente y que no lo
tiene en Word ni en Excel (D7), así que se lee de los PDF.

La importación tiene dos pasos:

1. **`extraer_fichas.py`**, en desarrollo. Lee los PDF de las fichas y escribe
   `sgth-backend/database/data/manual-de-puestos-2018-2019.json`, que se versiona. Python no va al
   servidor.
2. **`php artisan sgth:manual:importar`**, en el servidor. Carga el JSON en la tabla de staging
   `fichas_manual_puestos`. Al cargarlo, interpreta los niveles de instrucción y los tiempos de
   experiencia, empareja las competencias con el diccionario y recalcula su número. Lo que no
   puede resolver solo lo deja en los avisos de cada ficha.

Después, TH revisa cada ficha, la empareja con su puesto y confirma el perfil (PR 12).

## Volver a generar el JSON

Solo hace falta si cambian los PDF o el extractor.

```bash
pip install pymupdf
python scripts/manual-puestos/extraer_fichas.py "docs/DIGITAL MANUAL DE PUESTOS" sgth-backend/database/data/manual-de-puestos-2018-2019.json
```

- **Carpeta del manual:** la que compartió TH, con las fichas organizadas por proceso y gestión. No se
  versiona.
- **Qué se salta:** el PDF de distribución, que trae los diagramas y el resumen por gestión.
- **Probado con:** PyMuPDF 1.28 y Python 3.14. Leyó las 179 fichas sin errores.

## Qué deja el extractor en cada ficha

| Campo | Contenido |
|---|---|
| `archivo` | La ruta de la ficha dentro del manual. Es la clave para volver a importar. |
| `proceso`, `carpeta` | Las carpetas: el proceso y la gestión o subproceso. Sirven para ubicar la unidad cuando la ficha no la trae. |
| `plazas` | Las plazas que cubre la ficha, si lo dice el nombre del archivo, como en «ANALISTA SP5 (2).pdf». Si no, 1. |
| `denominacion`, `nivel`, `unidad`, `rol`, `grupo_ocupacional`, `grado`, `ambito`, `codigo` | La identificación, tal como está escrita. |
| `mision`, `relaciones`, `area_conocimiento`, `especificidad`, `capacitacion` | Los textos de la ficha. |
| `instruccion` | Cada alternativa con su tiempo: `[{"nivel": "Técnico Superior", "experiencia": "6 años"}]`. |
| `actividades` | `[{"descripcion", "conocimientos"}]`. Si una celda viene partida en dos filas, se une. |
| `tecnicas`, `conductuales` | `[{"nombre", "nivel", "numero", "comportamiento"}]`, como vienen en la ficha. |
| `avisos` | Lo que el extractor no pudo emparejar con seguridad. |

El extractor no interpreta: deja el texto como viene. Las reglas para leer niveles, tiempos y
competencias están en el SGTH (`app/Services/Estructura/Manual/`), donde tienen pruebas.
