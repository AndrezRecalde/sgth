# Diseño de los módulos de Comunicación Social: Fotografías, Credencial e Identidad institucional

**SGTH — GAD Provincial de Esmeraldas** · Versión 2, 14 de octubre de 2026 · Base: `d287834a`

Estado: **diseño decidido**. Todas las decisiones de la sección 10 están tomadas: D2 y D6 con respuestas propias y las demás aceptando la propuesta (14-10-2026).

Marcas que usa el documento:

- **[Hoy]**: lo que existe en el código.
- **[Propuesta]**: lo que se plantea construir.
- **[Dn]**: decisión de Comunicación Social o de Talento Humano (sección 10).

---

## 1. Resumen

1. **Lo que se pide.** Dos módulos para la Gestión de Comunicación Social: el primero, para subir la fotografía de cada servidor y emitir con ella una **credencial digital**; el segundo, para administrar los **logos** que usan las pantallas (sidebar, login) y los documentos que exporta el sistema.
2. **Se propone un quinto subsistema, pequeño: «Comunicación» (`/comunicacion`)** [D1]. La razón no es el tamaño, porque son cuatro pantallas, sino que Comunicación Social es un equipo propio que no tiene nada que hacer en Talento Humano. La alternativa, que entre al SGTH con el menú recortado, repetiría la excepción que ya existe para Recepción y Trabajo Social y que el propio código describe como un parche (sección 2).
3. **Hoy no existe nada de fotografías ni de credenciales.** La tabla `servidores` no tiene foto y el menú de usuario muestra iniciales. Sí existe la pieza que la credencial necesita: la verificación pública del certificado laboral por código aleatorio, que se reutiliza para el QR.
4. **El logo está escrito a mano en 21 lugares** (20 usos en 16 archivos de PDF, más la hoja membretada del informe de actividades). Cambiarlo hoy exige un despliegue. Además conviven **dos lemas distintos**: los documentos dicen «¡Los buenos somos más!» y la hoja membretada dice «¡Juntos haremos historia!» [D4].
5. **Variantes del logo: seis casillas, de las cuales solo dos son obligatorias** (isotipo e imagotipo horizontal). Las otras cuatro son opcionales y cada una tiene un respaldo, así que el sistema funciona completo con dos archivos (sección 4.2).
6. **Los logos tienen fecha de vigencia.** Los PDF no se guardan, se generan en cada descarga, así que sin vigencia un acto de 2026 reimpreso en 2028 saldría con el logo de 2028. Con vigencia, cada documento toma el logo de su fecha [D3].
7. **Plan:** 4 fases y 9 PRs (sección 9). Primero las fotografías, para que Comunicación pueda empezar la sesión de fotos, que es lo que más tarda; después la identidad; al final la credencial, que necesita las dos [D12].

---

## 2. ¿Hace falta un subsistema?

### 2.1 Qué es un subsistema en el SGTH [Hoy]

Hay cuatro: Talento Humano (`/sgth`), Dispensario (`/salud`), Tecnologías (`/tic`) y Portal (`/portal`). Cada uno tiene su propio shell, con color, menú e inicio. Se entra por **listas de roles** (`ROLES_SGTH`, `ROLES_SALUD`, `ROLES_TIC` y `ROLES_PORTAL` en `sgth-frontend/src/config/nav.ts`), y dentro el menú se filtra por permiso. Pero muchas entradas del SGTH no declaran restricción y se muestran a cualquiera que entre.

### 2.2 Las tres salidas

| Opción | Cómo queda | Problema |
|---|---|---|
| **A.** Dentro de Talento Humano | El rol de Comunicación entra al SGTH y ve un grupo «Comunicación» | Hay que repetir la excepción de `ROLES_SGTH_SOLO_PERMISOS`: el comentario de `nav.ts` explica que esos roles no van en `ROLES_SGTH` porque «muchas entradas no declaran restricción y les responderían 403». Comunicación vería además el Dashboard de TH y una cabecera que dice «Talento Humano» |
| **B.** En el Portal | — | El Portal es el espacio de cada uno: lo propio. Subir las fotos de toda la institución no es autoservicio. Lo que sí va al Portal es «Mi credencial» |
| **C.** Subsistema propio «Comunicación» | `/comunicacion`, color propio, cuatro entradas de menú e inicio con el avance | Un PR de cimientos con la lista de archivos que ya tocó TIC en su 0.1 (sección 7.4). Es un costo conocido |

**Propuesta: C** [D1], por cuatro razones:

- **Es una unidad propia del orgánico.** `UnidadAdministrativaSeeder` tiene «Gestión de Comunicación Social» con sus subprocesos, igual que TIC tiene la suya.
- **El costo es fijo y conocido**, mientras que la opción A suma una segunda excepción a un menú que ya tiene una.
- **El inicio tiene contenido real:** cuántos servidores tienen foto por unidad, qué credenciales faltan por imprimir y cuáles quedaron desactualizadas.
- **Separa bien lo que no es de Comunicación.** La credencial del propio servidor va al Portal, la foto se ve en el expediente (TH, solo lectura) y los logos se ven en todo el sistema. En `/comunicacion` queda solo la administración.

Si lo único pedido fueran los logos (que cambian una vez por administración), bastaría una pantalla en «Administración» del SGTH. Con fotos y credenciales, que son trabajo continuo de un equipo, no.

---

## 3. Lo que hay hoy [Hoy]

### 3.1 Fotografías y credenciales

- `servidores` no tiene columna de foto ni hay tabla de fotografías. El avatar del menú de usuario usa iniciales (`UserMenu.tsx:43`).
- **No hay credencial.** Las coincidencias de «carnet» en el código son el carné de discapacidad.
- **Sí hay, y se reutiliza:**
  - la verificación pública del certificado laboral: `GET /api/v1/certificados/verificar/{codigo}` (código aleatorio, `throttle:10,1`, sin sesión) y la página `/verificar/[codigo]`;
  - `simplesoftwareio/simple-qrcode` para el QR;
  - `@mantine/dropzone` para subir archivos y la extensión `gd` de PHP para redimensionar;
  - el disco `local` (privado), donde ya viven los documentos del expediente;
  - `PasoDesvinculacion`, que ya tiene pasos con responsable (bienes, usuario, certificado…).
- **Falta:** una librería de recorte en el frontend; Mantine no la trae.

### 3.2 Logos

| Archivo | Qué es | Dónde se usa |
|---|---|---|
| `sgth-frontend/public/logo.png` (554×581) | Isotipo | Sidebar (32 px) y login (80 px) |
| `sgth-backend/public/images/logo-gadpe.png` (2199×625) | Imagotipo horizontal con el lema «¡Los buenos somos más!» | 20 usos en 16 archivos: acción de personal, registro interno, constancia de autoridad, certificado laboral, nómina, receta, FEMO, certificado de aptitud, organigrama, viáticos, permisos, vacaciones, consolidado, actas de TIC (custodia, préstamo, baja, constatación) y reportes de TIC |
| `sgth-backend/public/images/membrete-gadpe.jpg` (1240×1754) | Hoja membretada A4 a 150 ppp, con el lema «¡Juntos haremos historia!», marca de agua y dirección al pie | Solo el informe de actividades |
| `LogoCompleto.png` | Copia exacta de `logo-gadpe.png` (mismo MD5) | Ninguno |
| `recepcion.png`, `servidor.png` (300×300) | — | Ninguno |

Lo que está mal:

1. **La ruta está fija en 21 lugares.** Cambiar el logo es un despliegue.
2. **Dos lemas conviven.** Uno de los dos está desactualizado [D4].
3. **El login muestra el isotipo**, no el logo.
4. **El frontend no tiene favicon:** la pestaña del navegador muestra el ícono por defecto. En `public/` siguen los archivos de la plantilla de Next (`next.svg`, `vercel.svg`, `file.svg`, `globe.svg`, `window.svg`).
5. **Los datos institucionales están en el código:** el nombre y el RUC en `PdfRecetaService::INSTITUCION`, «GADPE / Esmeraldas» en el sidebar, y la dirección y el teléfono dentro de la imagen de la hoja membretada.
6. **Los PDF no se guardan:** se generan en cada descarga (`->output()`). Si se cambia el archivo, todo documento reimpreso sale con el logo nuevo, aunque sea de otra administración.

---

## 4. Identidad institucional

### 4.1 Términos

- **Isotipo:** el símbolo solo (el círculo).
- **Imagotipo horizontal:** el símbolo junto a «Esmeraldas Prefectura» y el lema, en una línea.
- **Hoja membretada:** la página A4 completa, con el logo arriba, la marca de agua y la dirección al pie.

### 4.2 Variantes [Propuesta, D4]

| # | Variante | Obligatoria | Dónde se usa | Formato y tamaño mínimo | Si falta |
|---|---|---|---|---|---|
| V1 | **Isotipo** | Sí | Sidebar abierto y cerrado, favicon e íconos de la app, marca de agua de la credencial | PNG transparente, cuadrado, desde 512×512 | — |
| V2 | **Imagotipo horizontal** | Sí | Login, cabecera de los correos, anverso de la credencial, página de verificación | PNG transparente, desde 1200 px de ancho | — |
| V3 | Imagotipo horizontal en negativo (blanco) | No | Login y verificación en modo oscuro; credencial sobre una franja de color | PNG transparente, desde 1200 px de ancho | V2 |
| V4 | **Logo para documentos** | No | Cabecera de todos los PDF | PNG, fondo blanco o transparente, desde 1200 px de ancho | V2 |
| V5 | Logo secundario de documentos | No | Lado derecho de la cabecera de los PDF (escudo provincial, marca de una campaña) | PNG transparente | No se muestra |
| V6 | Hoja membretada A4 | No | Fondo de los documentos tipo memorando (hoy, el informe de actividades) | JPG o PNG, A4 vertical: 1240×1754 (150 ppp) o 2480×3508 (300 ppp) | V4 arriba, y la dirección y el teléfono al pie escritos por el sistema |

Los **íconos de la app** (favicon de 32 px, ícono de Apple de 180 px y los de 192 y 512 px) **se generan solos a partir de V1**; nadie los sube.

**Respuesta corta: el mínimo son 2 (V1 y V2). Con ellas funciona todo el sistema.** V4 existe porque se pidió que los documentos puedan llevar «el logo institucional u otro que deseen»; si no se sube, los documentos usan V2.

Lo que **no** se pide, y por qué:

- **Versión vertical o apilada:** ninguna pantalla la necesita. La credencial vertical tiene 54 mm de ancho y el imagotipo horizontal cabe arriba.
- **Monocromo para blanco y negro:** un logo a color se imprime bien en escala de grises. Si algún documento sale mal, se agrega después.
- **SVG:** dompdf dibuja mal muchos SVG, y un SVG puede llevar código ejecutable. Se aceptan PNG (y JPG para V6).
- **Isotipo en negativo:** el isotipo a color se ve bien sobre el sidebar oscuro.

### 4.3 Vigencia e historial

- Cada archivo subido es una **versión con `vigente_desde`**: por defecto hoy, pero puede ser una fecha futura (por ejemplo, el día de posesión de la próxima administración).
- **Nada se borra.** El historial se ve en la pantalla, y «volver a esta versión» crea una versión nueva con el archivo anterior.
- **Cada documento toma la versión vigente a su fecha** [D3]:
  - acción de personal y registro interno: la fecha de suscripción (hoy, si es borrador);
  - certificados: la fecha de emisión;
  - actas y recetas: la fecha del acta o de la receta;
  - reportes y listados: hoy.
- Las pantallas (sidebar, login, correos) usan siempre la versión vigente hoy.
- **Reglas que fijó la 2.1:**
  - una versión nueva **no puede empezar antes que la última** de su variante: reescribiría los documentos que ya se emitieron con ella. Sí puede empezar en el pasado si no hay otra después (el logo de una administración que se sube unos días tarde);
  - solo una versión **programada**, que todavía no rige, se puede **anular**. Una que ya rigió salió en documentos; para volver atrás está «volver a esta versión», que comparte el archivo (ninguno se modifica ni se borra);
  - un documento **anterior a la primera versión** usa la más antigua que ya rige, nunca una programada: así un documento viejo siempre tiene logo. **Corregido en la 2.2:** en una variante con respaldo (V3, V4) primero va el respaldo de esa fecha: un acto de 2026 no lleva el logo de documentos que se cargó en 2027, sino el horizontal que había.
- **Lo que quedó en la 2.2:** `paraDocumento($fecha)` entrega a cada PDF el logo (V4 o V2), el secundario (V5), la hoja membretada (V6) y los datos institucionales vigentes a la fecha del documento:
  - acción de personal, registro interno y constancia: la fecha de suscripción, o la de registro; un borrador, hoy;
  - certificado laboral: `emitido_en`; certificado de aptitud y FEMO: `fecha_evaluacion`; receta: `fecha_emision`;
  - actas de TIC: la entrega (`fecha_asignacion`), el préstamo (`entregado_en`), el informe de baja (`fecha_informe`) y la constatación (`cerrada_en`, o su inicio si sigue abierta);
  - viáticos (solicitud, informe y comprobante): la fecha de la solicitud, para que los tres papeles salgan iguales;
  - permiso: su `fecha`; vacaciones: `fecha_emision`;
  - nómina, organigrama, consolidado de permisos, reportes de TIC e informe de actividades: hoy.
- **Cabecera común** (`pdf/_membrete-institucional` y `pdf/_pie-institucional`): la usan la acción de personal, el registro interno, la constancia de autoridad y los documentos de TIC (actas y reportes). Lleva el nombre oficial, la dependencia que emite y el logo secundario a la derecha, solo si se cargó. Los documentos con un formato propio (FEMO del MSP, receta, viáticos, certificados centrados, permisos, vacaciones) conservan su cabecera: cambian el logo y el nombre oficial, y no tienen lugar para el secundario.
- **Hoja membretada (V6):** el informe de actividades la usa de fondo. Sin ella, el sistema pone el logo arriba y la dirección con el teléfono al pie, en los mismos márgenes.

### 4.4 Validaciones al subir

- Tipo y tamaño mínimo según la tabla de 4.2; hasta 5 MB.
- V1 debe ser cuadrado (con un 5 % de tolerancia) y V6 debe tener la proporción del A4.
- **Vista previa en contexto antes de confirmar:** el sidebar en claro y en oscuro, el login, una cabecera de PDF de muestra y la credencial.

### 4.5 Datos institucionales

Un formulario único con el nombre oficial, las siglas (GADPE), el nombre corto del sidebar, el RUC, la dirección, el teléfono y el sitio web. Lo usan la cabecera de los PDF, el pie que escribe el sistema cuando no hay V6, la receta (que hoy tiene la constante) y el sidebar.

### 4.6 Cómo se sirven

- **Los logos son públicos**, porque van en cualquier documento: se sirven por `GET /api/v1/identidad/{variante}` sin sesión. Lo necesitan el login y los correos, que no tienen sesión. La URL lleva el número de versión, así que el navegador puede guardarla en caché y el cambio se ve al momento.
- Los PDF leen el archivo del disco, porque dompdf necesita una ruta local.
- **El despliegue no rompe nada:** la migración siembra como primera versión los archivos que se usan hoy: el logo horizontal de los PDF (V2), la hoja membretada del informe de actividades (V6) y el isotipo del sidebar (V1), que se copió del frontend a `sgth-backend/resources/identidad/` porque en producción el backend no ve esa carpeta. También siembra los datos institucionales que estaban escritos en el código.
- **Lo que quedó en la 2.1:**
  - `GET /api/v1/identidad`, sin sesión: el manifiesto con la ruta de cada variante vigente (con su respaldo resuelto y la marca `respaldo`), los íconos y los datos institucionales;
  - `GET /api/v1/identidad/{variante}?v={id}`: el archivo vigente hoy. Con la versión exacta en la URL, caché de un año (`immutable`); sin ella, cinco minutos;
  - `GET /api/v1/identidad/icono/{32|180|192|512}`: sacados del isotipo con GD, centrados y sin deformar, uno por versión y tamaño. El de 180 (Apple) va sobre blanco, porque iOS rellena de negro la transparencia;
  - todo lo público lleva `throttle:120,1`. Una versión programada solo se ve con `gestionar-identidad-institucional`, por su id.

---

## 5. Fotografías

### 5.1 Reglas [Propuesta]

- **Las sube Comunicación** [D5], para que todas tengan el mismo fondo y el mismo encuadre.
- **Una foto vigente por servidor.** La anterior queda en el historial, con quién y cuándo la subió, y se puede restaurar. También se puede **retirar** la vigente (por ejemplo, si se subió a la persona equivocada y no había otra antes): el servidor queda sin foto y el archivo sigue en el historial. Ningún archivo se borra, y cada cambio queda en el registro de actividad.
- **Recorte 3:4 en el navegador antes de subir**, con la cara centrada. El servidor guarda una versión de 600×800 en JPEG y una miniatura cuadrada de 160×160 para los avatares.
- **No se guarda el original de la cámara.** Puede pesar 10 MB y trae metadatos, entre ellos la ubicación; al volver a codificar la imagen se eliminan.
- Formatos: JPG, PNG y WebP, hasta 10 MB.
- **Carga masiva:** se eligen los archivos de la carpeta, con la cédula en el nombre (`0801234567.jpg`, o `0801234567 - Ana Pérez.jpg`: se toman los primeros 10 dígitos seguidos). **Sin ZIP** (cambio de 1.1): un lote de cientos de fotos supera el tamaño que acepta una petición. Primero se simula **con los nombres, sin subir ninguna foto**, y se entrega un informe por archivo: foto nueva, reemplaza, ya tiene foto, sin cédula, sin servidor, servidor inactivo y cédula repetida en el lote. Después las fotos se suben en tandas de 10, y la carga vuelve a comprobarlo todo y agrega «ilegible» para la que no se pudo usar, sin tumbar la tanda. Los servidores inactivos se omiten en la carga masiva; de uno en uno sí se les puede subir (un ingreso que todavía no empieza). En la carga masiva el recorte es automático (centrado, tomando un poco más de arriba), así que las fotos deben tomarse ya encuadradas; cada una se puede ajustar después.
- **Privacidad (LOPDP):** la foto es un dato personal. Va al disco privado y se entrega solo con sesión, a quien puede ver al servidor, o mediante la verificación de la credencial. Nunca se muestra en el organigrama público.
- **Cómo se pide la imagen** (1.1): por el id de la foto (`comunicacion/fotografias/{id}/imagen?tamano=miniatura`), no por el servidor, porque ese archivo no cambia nunca y el navegador lo puede guardar. La sesión va en una cookie que el backend solo acepta con la cabecera `X-Requested-With`, así que un `<img src>` directo no sirve: el frontend la pide como blob, igual que los PDF. La vigente la ven el servidor, Talento Humano (ServidorPolicy), Comunicación y quien consulta credenciales; una que ya no es vigente, solo Comunicación. El perfil (`auth/perfil`) y la ficha del expediente traen el id de la foto vigente.

### 5.2 Dónde se ve [D10]

- En el avatar del menú de usuario. Sin foto, siguen las iniciales.
- En la cabecera del expediente (TH, solo lectura).
- En la credencial y en su verificación.
- El directorio telefónico, que ven todos los que tienen sesión, queda fuera por ahora.

---

## 6. Credencial

### 6.1 Qué es

Una por servidor activo, con dos caras de lo mismo:

- **Digital:** en el Portal, «Mi credencial», a pantalla completa en el celular, siempre con los datos actuales del expediente, y descargable en PDF.
- **Impresa:** Comunicación la imprime de una en una o por lotes de una unidad. Cada impresión guarda los datos impresos (nombre, cargo, unidad, tipo de sangre y contacto de emergencia), para saber cuándo una tarjeta quedó desactualizada: por un traslado o un ascenso, pero también porque el servidor cambió el teléfono de su contacto de emergencia.

La credencial **se crea sola** cuando un servidor activo tiene foto. Comunicación no la «emite»: la imprime, la revoca y la repone.

### 6.2 Contenido [D6, D7, D11]

Formato CR80 (85,6 × 54 mm, el de una tarjeta bancaria), vertical, con dos salidas de impresión [D7]: un PDF de una tarjeta por página para impresora de PVC, y hojas A4 con 9 credenciales por cara para recortar y plastificar.

- **Anverso:** V2 arriba, la foto, los nombres y apellidos, el cargo (denominación del puesto), la unidad y la cédula.
- **Reverso:** el QR de verificación; «Válida hasta», solo si el vínculo tiene fecha de fin; el texto «Esta credencial es personal e intransferible. Si la encuentra, devuélvala a…» con la dirección y el teléfono de 4.5, y V1 como marca de agua.
- **Tipo de sangre y contacto de emergencia** (nombre, parentesco y teléfono), en el reverso [D6]. TH y Jurídico aprobaron mostrarlos. Salen del expediente (`tipo_sangre` y `contacto_emergencia_*`); si el servidor no los tiene registrados, la línea no se imprime, en lugar de quedar en blanco. El tipo de sangre es un dato de salud, que la LOPDP trata como sensible: va en la tarjeta que lleva el propio servidor y en su credencial digital, **nunca en la verificación pública**.
- **Sin firma:** la credencial la valida el QR [D11].

### 6.3 Ciclo de vida

| Estado | Cuándo | Qué pasa con el QR |
|---|---|---|
| Vigente | El servidor está activo y tiene foto | Valida |
| Revocada | Pérdida, robo o datos erróneos (motivo obligatorio) | Deja de validar. **Reponer** crea una credencial nueva con otro código |
| Vencida | Llega la fecha de fin del contrato del vínculo | Dice «no vigente» |
| Anulada | El vínculo termina (desvinculación o cesación) | Dice «no vigente» |

Vigencia [D8]: los nombramientos y los contratos indefinidos, sin fecha; los ocasionales y los contratos a plazo, hasta el fin del contrato.

### 6.4 Verificación pública

`/verificar/credencial/[codigo]`, con el mismo diseño que la del certificado: código aleatorio de al menos 32 caracteres, sin sesión y limitada por IP.

- **Vigente:** muestra «Credencial vigente», la foto, el nombre, el cargo y la unidad **actuales** [D9]. No muestra la cédula, el tipo de sangre ni el contacto de emergencia.
- **No vigente:** muestra solo «Esta credencial no está vigente», sin ningún dato.

### 6.5 Desvinculación

La credencial digital se anula sola. Se propone además un paso nuevo en `PasoDesvinculacion`, **«Devolución de la credencial»**, a cargo de Comunicación Social [D13].

---

## 7. Arquitectura propuesta

### 7.1 Backend

```
app/
  Http/Controllers/Comunicacion/   FotografiaServidorController, CargaFotografiasController,
                                   CredencialController, IdentidadInstitucionalController
                                   (prefijo /api/v1/comunicacion)
  Http/Controllers/Autoservicio/   MiCredencialController
  Http/Controllers/Expediente/     VerificacionCredencialController   (junto a VerificacionCertificadoController)
  Http/Controllers/                IdentidadPublicaController         (GET /api/v1/identidad/{variante}, sin sesión)
  Services/Comunicacion/           FotografiaService, CargaFotografiasService, CredencialService,
                                   PdfCredencialService, IdentidadInstitucionalService
  Contracts/…                      una interfaz por servicio, enlazada en AppServiceProvider
  Policies/Comunicacion/
  Enums/                           VarianteIdentidad, EstadoCredencial, MotivoRevocacionCredencial,
                                   FormatoImpresionCredencial
```

`IdentidadInstitucionalService::archivo(VarianteIdentidad $variante, ?CarbonInterface $fecha = null)` resuelve la versión vigente con sus respaldos. Los 16 archivos que hoy llaman a `public_path('images/logo-gadpe.png')` pasan a pedirle el logo con la fecha del documento, y las cabeceras repetidas se juntan en una sola vista parcial.

Las reglas son las de siempre: controlador delgado, `ApiResponse`, `ReglaNegocioException`, enums comparados por `->value` y `America/Guayaquil`.

### 7.2 Modelo de datos

```
fotografias_servidor
  id, servidor_id, archivo, miniatura, vigente (bool), origen (individual | carga_masiva),
  activada_en, activada_por (quién la dejó vigente: al subirla o al restaurarla),
  subida_por, timestamps
  -- una sola vigente por servidor: índice único parcial WHERE vigente

credenciales
  id, servidor_id, codigo (aleatorio, único), estado, valida_hasta (null = sin fin),
  reemplaza_a_id (la revocada a la que repone),
  revocada_en, revocada_por, motivo_revocacion, detalle_revocacion, anulada_en,
  devuelta_en, devolucion_registrada_por, devolucion_nota (la tarjeta al salir), timestamps
  -- una sola viva (vigente o vencida) por servidor: índice único parcial

impresiones_credencial
  id, credencial_id, impresa_por, formato (cr80 | a4), lote (nullable),
  datos_impresos (json: nombre, cargo, unidad, cédula, tipo de sangre, contacto de emergencia,
  válida hasta y la foto), created_at

recursos_identidad
  id, variante, archivo, mime, ancho, alto, peso, vigente_desde (date),
  origen (carga_inicial | subida | reutilizada), reutiliza_id, nota,
  subido_por, anulada_en, anulada_por, timestamps
  -- la versión vigente a una fecha: la de mayor vigente_desde <= fecha, sin las anuladas

datos_institucionales   (una sola fila)
  nombre_oficial, siglas, nombre_corto, ruc, direccion, telefono, sitio_web, actualizado_por, timestamps
```

### 7.3 Roles y permisos

Dos roles nuevos [D2]:

- **`comunicacion-social`** («Comunicación Social»): el equipo que toma las fotos e imprime las credenciales.
- **`responsable-identidad`** («Responsable de Identidad Institucional»): la persona que designe el director de Comunicación, o la designada de Comunicación Social, para cambiar los logos y los datos institucionales. **No es el director por su cargo:** el rol se asigna a una persona desde Usuarios, y se le quita del mismo modo cuando cambia la designación.

Los dos roles se suman: si la persona designada también toma fotos, tiene los dos. Si es alguien de otra unidad, con `responsable-identidad` solo entra a Identidad institucional.

Cada permiso va en el enum, en `RolPermisoSeeder` y en una migración con `Permission::firstOrCreate`, como se hizo con TIC.

| Permiso | `comunicacion-social` | `responsable-identidad` | TH (`admin-uath`) | Servidor (todos) | Auditor |
|---|---|---|---|---|---|
| `gestionar-fotografias`: subir, reemplazar, restaurar, retirar y cargar en lote | ✓ | | | | |
| `gestionar-credenciales`: imprimir, revocar y reponer | ✓ | | | | |
| `ver-credenciales`: consultar el estado y el historial de impresiones | ✓ | | ✓ | | ✓ |
| `gestionar-identidad-institucional`: logos, vigencias y datos institucionales | | ✓ | | | |
| Ver la foto de un servidor: quien puede ver su expediente | | | ✓ | | |
| «Mi credencial» en el Portal: con `acceso-autoservicio`, que ya tienen todos | ✓ | ✓ | ✓ | ✓ | ✓ |

`admin-ti` lo tiene todo por `Gate::before`, pero recibe los permisos de forma explícita para que la pantalla de Usuarios lo muestre [D2].

### 7.4 Frontend

- **Nuevo subsistema `comunicacion`** en `src/app/(comunicacion)/comunicacion/`, con un color propio: **fucsia (`orchid`)**. El naranja del logo no puede ser: la regla 03 rechaza `orange` porque el ámbar significa «advertencia» y el rojo «error». El fucsia es el único tono libre que no choca con un significado: queda a 31° del violeta del Portal y a 67° del rojo (el rosa quedaba a 27° del rojo), y el blanco sobre `orchid-6` da 4.71:1, así que llega al AA sin oscurecer el relleno. Toca los mismos archivos que tocó TIC: el tipo `Subsistema` (`routes.ts` y las uniones de `nav.ts`), `subsistemas.ts`, `NAV_COMUNICACION` y `ROLES_COMUNICACION` (`comunicacion-social`, `responsable-identidad` y `admin-ti`, más `admin-uath` y `auditor`, que consultan las credenciales), `tokens.css`, `design.tokens.ts`, `mantine.theme.ts`, la lista de colores de `eslint.config.mjs` y el glob de la regla `pagina-con-pageshell`.
- El código va en `features/comunicacion/` (fotografías, credenciales e identidad), con el patrón componente → hook → servicio y esquemas Zod `zod/v4`.
- **Recorte de la foto:** `react-easy-crop` 6.2.3, con versión fija (1.2; la 6.2.4 tenía una semana). El navegador recorta y sube un JPEG de hasta 1200 px de ancho; el backend lo deja en 600×800.
- **La imagen se pide con axios y se guarda como `data:` URL** en la caché de TanStack Query (1.2): una URL `blob:` hay que revocarla al desmontar, y en desarrollo React monta dos veces. `FotoServidor` es el avatar con foto o iniciales que usan el menú de usuario, la cabecera del expediente y la pantalla Fotografías.
- **La identidad en todo el sistema:** un hook `useIdentidad()` con las URLs vigentes, que usan el `Sidebar`, el login y `metadata.icons`.
- **Portal:** «Mi credencial» en «Mi Espacio».
- **Público:** `/verificar/credencial/[codigo]`.
- **Limpieza:** se retiran `LogoCompleto.png`, `recepcion.png`, `servidor.png` y los SVG de la plantilla de Next.

---

## 8. Pantallas y navegación

```
Comunicación (/comunicacion)
├─ Inicio                       avance: servidores activos con y sin foto por unidad,
│                               credenciales por imprimir, impresas desactualizadas,
│                               próximas a vencer
├─ Fotografías                  filtros: unidad, con o sin foto, antigüedad de la foto;
│                               subir con recorte, historial y restaurar; carga masiva con simulación
├─ Credenciales                 estado por servidor; imprimir una o por unidad (lote);
│                               revocar y reponer; historial de impresiones
└─ Identidad institucional      las seis variantes con vista previa en contexto,
                                historial y vigencia; datos institucionales

Portal › Mi Espacio › Mi credencial      digital a pantalla completa y descarga en PDF
TH › Expediente                           la foto en la cabecera (solo lectura)
Público › /verificar/credencial/[codigo]
```

---

## 9. Plan de trabajo

Cada punto es un PR con sus pruebas Pest y, si hay pantalla, revisión en el navegador. Cada fase deja algo usable.

### Fase 0: Cimientos

| PR | Contenido |
|---|---|
| 0.1 | Subsistema `comunicacion` (shell, color, navegación, inicio vacío), roles `comunicacion-social` y `responsable-identidad` y permisos (enum, seeder, migración). Las policies llegan con sus modelos, en 1.1, 2.1 y 3.1 |

### Fase 1: Fotografías

| PR | Contenido |
|---|---|
| 1.1 | Backend: tabla e historial, disco privado, versión de 600×800 y miniatura con GD, endpoints, carga masiva por cédula con simulación e informe (sin ZIP: archivos de la carpeta en tandas de 10) |
| 1.2 | Pantalla Fotografías con recorte 3:4 y carga masiva; la foto en el avatar y en la cabecera del expediente |

### Fase 2: Identidad institucional

| PR | Contenido |
|---|---|
| 2.1 | Backend: `recursos_identidad` con vigencia y respaldos, datos institucionales, endpoint público, íconos sacados del isotipo y siembra con los archivos actuales |
| 2.2 | Los 16 archivos de PDF dejan la ruta fija: una cabecera común, el logo según la fecha del documento, V5 y V6, el nombre y el RUC de la receta desde los datos |
| 2.3 | Pantalla Identidad institucional con vista previa en contexto; sidebar, login, favicon e íconos, modo oscuro y correos con el logo; limpieza de imágenes sin uso |

**Lo que quedó en la 2.3:** la pantalla `/comunicacion/identidad` (seis tarjetas con la versión vigente o el respaldo atenuado, «Subir versión» con aviso temprano de tamaño y proporción y una imitación del sitio donde se usa —sidebar claro y oscuro, inicio de sesión, cabecera de documento, hoja A4—, historial con «Volver a esta versión» y «Anular» para las programadas, y el formulario de los datos); el sidebar toma el isotipo, las siglas y el nombre corto del manifiesto público, con `/logo.png` de respaldo si el backend no responde; el login, el imagotipo horizontal (el negativo en modo oscuro); el favicon y los íconos salen de `GET /api/v1/identidad/icono/{lado}`; los correos llevan el imagotipo por su URL pública (`FRONTEND_URL`), con el nombre de la app si no hay logo. Se retiraron las imágenes sin uso (`LogoCompleto.png`, `recepcion.png`, `servidor.png` y los SVG de la plantilla de Next). Queda escrito a mano el nombre de la institución en la cabecera de «Mi portal».

### Fase 3: Credencial

| PR | Contenido |
|---|---|
| 3.1 | Backend: credenciales e impresiones, estados, vencimiento y anulación automáticos, verificación pública, PDF CR80 y hoja A4 (una y por lote) y el paso de la desvinculación |
| 3.2 | Pantalla Credenciales en `/comunicacion`, «Mi credencial» en el Portal y la página de verificación |
| 3.3 | Inicio de Comunicación con el avance |

**Lo que quedó en la 3.1** (y lo que se decidió al implementarla):

- **Activo es `estado`, no el contrato.** La credencial nace cuando el servidor tiene `estado` activo y foto vigente; el contrato vigente solo aporta «Válida hasta». Desde la fase 1.5 de Acciones de Personal los dos dicen lo mismo, pero un expediente antiguo sin su contrato cargado sigue siendo un servidor activo y debe tener credencial. La sincronizan la foto al quedar vigente (subir, restaurar, carga masiva), `ContratoServidorService::sincronizarPuestoDesdeVinculo` —la única vía que cambia `estado`— y la reprogramación del plazo.
- **Vencida no es final.** Si el contrato se prorroga o se renueva sin interrupción, la misma credencial vuelve a vigente con el mismo código: la tarjeta impresa sigue validando y queda marcada como desactualizada por su «Válida hasta». Revocada y anulada sí son finales. La base admite una sola credencial viva (vigente o vencida) por servidor.
- **Revocar repone en el acto.** No hay un estado «sin credencial» para un servidor activo con foto, así que revocar crea la nueva con otro código (`reemplaza_a_id`). Los motivos son pérdida, robo, datos erróneos y otro, que pide el detalle. Una tarjeta deteriorada no se revoca: se reimprime con el mismo código.
- **La tarea de cada noche**, `sgth:credenciales:conciliar` a las 00:45 (después del motor de efectos), vence las credenciales por la fecha y crea las que falten. Después de migrar en producción hay que lanzarla una vez a mano para crear las de quienes ya tienen foto. Aunque no corra, la verificación compara la fecha: una vencida nunca pasa por vigente.
- **Imprimir registra.** Generar el PDF de impresión guarda una impresión por credencial con lo impreso (nombre, cargo, unidad, cédula, tipo de sangre, contacto de emergencia, «Válida hasta» y la foto), en un mismo lote si son varias. Hasta 90 por vez (diez hojas A4). Solo se imprimen las vigentes con foto. El historial que devuelve la API no repite el tipo de sangre ni el contacto impresos: dice qué cambió.
- **El PDF** lleva los logos vigentes hoy (la tarjeta se imprime ahora). En la hoja A4 el reverso va en espejo por columnas, para imprimir a doble cara volteando por el borde largo, con guías de corte. La credencial digital del Portal es el mismo PDF CR80 y no cuenta como impresión.
- **La verificación** es `GET /api/v1/credenciales/verificar/{codigo}` (40 caracteres hexadecimales, 10 por minuto por IP). Vigente: nombre, cargo, unidad y la foto en `data:`. No vigente: `{ vigente: false }` y nada más.
- **El paso de la desvinculación** lee la última credencial del servidor, la que se anuló al salir: está hecho si nunca se imprimió, si la tarjeta se devolvió o si se revocó por pérdida o robo; si no, queda pendiente para Comunicación, que registra la devolución desde Credenciales (`devuelta_en`). Talento Humano puede marcarlo como que no aplica, con su motivo, como los demás.
- **Endpoints:** `comunicacion/credenciales` (listado con `situacion`: sin foto, pendiente, sin imprimir, desactualizada, al día, vencida), `comunicacion/servidores/{id}/credenciales` (historial), `comunicacion/credenciales/imprimir`, `…/{id}/revocar` y `…/{id}/devolucion`; `autoservicio/mi-credencial` y su `/pdf`.

**Lo que quedó en la 3.2:**

- **Credenciales** (`/comunicacion/credenciales`, con `ver-credenciales`): cada servidor activo con su foto y su situación —sin foto, sin imprimir, por reimprimir con lo que cambió, impresa, vencida—; imprimir una, las elegidas en la tabla o las de una unidad, en CR80 o A4; revocar y reponer; el historial con las impresiones. Talento Humano y Auditoría entran a consultar: imprimir, revocar y recibir solo se ofrecen con `gestionar-credenciales`.
- **Tarjetas por recibir:** el listado de activos no enseñaba a quien ya se fue, y el paso de su desvinculación dice que la tarjeta la recibe Comunicación «en Credenciales». Se añadió al backend la situación `por_recibir`: quienes salieron con su última tarjeta impresa sin devolver (salvo perdida o robada), la misma regla del paso. Desde el historial se registra la devolución. El filtro de unidad no los encuentra: al salir, el servidor queda sin unidad.
- **Mi credencial** (`/portal/mi-credencial`): las dos caras de la tarjeta con los datos de hoy, el QR sobre blanco también en modo oscuro, y «Descargar en PDF». Sin credencial, dice por qué (sin foto, sin vínculo o se crea esta noche).
- **La verificación** (`/verificar/credencial/[codigo]`, sin sesión): vigente, no vigente y no encontrada. Un 429 del límite por IP, o un fallo de red, no se presentan como «no encontrada»: eso acusaría de falsa a una tarjeta auténtica.
- En la desvinculación, el tipo del paso suma `credencial`; la pantalla ya pintaba los pasos que le llegan.

**Lo que quedó en la 3.3:**

- **El Inicio** (`/comunicacion`) sale de `GET comunicacion/tablero` (`TableroComunicacionService`), con un bloque por permiso, como el tablero de Tecnologías:
  - Fotografías, con `gestionar-fotografias` o `ver-credenciales`;
  - Credenciales, con `ver-credenciales`;
  - Identidad, con `gestionar-identidad-institucional`.

  Talento Humano y Auditoría ven fotos y credenciales; la persona designada para los logos ve solo los logos.
- **Credenciales:** vigentes, por imprimir, por reimprimir, por vencer en 30 días con su lista, vencidas y tarjetas por recibir.
  - «Por reimprimir» se cuenta con `DatosCredencial`, igual que el listado.
  - «Por recibir» usa el scope `Credencial::tarjetaPorRecibir`, la misma regla del paso de la desvinculación.

  Así las cifras del Inicio y las del listado no se contradicen.
- **Fotografías:** activos con y sin foto, y por unidad, empezando por la que más debe. Se nombran ocho unidades; las demás se suman en «Otras».
- **Identidad:** las variantes obligatorias sin versión propia, las que se ven con su respaldo y los cambios programados.
- **Cada cifra abre su listado ya filtrado:**
  - `?situacion=` en Credenciales;
  - `?foto=sin&unidad=` en Fotografías.

  Las páginas leen `searchParams` en el servidor y se los pasan a la vista como filtro inicial, sin `useSearchParams`. El Inicio se pide de nuevo cada vez que se vuelve a él, porque imprimir y subir fotos ocurren en otras pantallas.

---

## 10. Decisiones

| # | Pregunta | PR que afecta | Decisión |
|---|---|---|---|
| D1 | ~~¿Subsistema propio «Comunicación» o un grupo dentro de Talento Humano?~~ | 0.1 | **Aceptada (14-10-2026):** subsistema propio (sección 2) |
| D2 | ~~¿Quién cambia los logos: todo el equipo de Comunicación o solo su director?~~ | 0.1 | **Resuelta (14-10-2026):** ninguno de los dos. Un **rol propio, `responsable-identidad`**, para la persona que designe el director de Comunicación o la designada de Comunicación Social; se asigna y se retira desde Usuarios. `admin-ti` también puede (sección 7.3) |
| D3 | ~~Un documento reimpreso, ¿lleva el logo vigente a su fecha o el de hoy?~~ | 2.2 | **Aceptada (14-10-2026):** el de su fecha en actos, certificados, actas y recetas; el de hoy en reportes y listados |
| D4 | ~~¿Confirman las seis variantes (dos obligatorias)? ¿Hay un escudo u otra marca que deba ir en los documentos (V5)? ¿Cuál es el lema vigente?~~ | 2.1 | **Aceptada (14-10-2026):** las seis, con V1 y V2 obligatorias. Comunicación sube los archivos vigentes, y con ellos queda resuelto el lema |
| D5 | ~~¿Quién sube la foto: solo Comunicación, o también el servidor desde el Portal con aprobación?~~ | 1.1 | **Aceptada (14-10-2026):** solo Comunicación, por uniformidad |
| D6 | ~~¿La credencial lleva tipo de sangre y contacto de emergencia?~~ | 3.1 | **Resuelta (14-10-2026):** sí. TH y Jurídico aprobaron mostrarlos en el reverso y en la credencial digital; nunca en la verificación pública (sección 6.2) |
| D7 | ~~¿Vertical u horizontal? ¿Hay impresora de tarjetas PVC?~~ | 3.1 | **Aceptada (14-10-2026):** vertical, con los dos formatos de impresión: un PDF CR80 por tarjeta para impresora de PVC, y hojas A4 con 9 credenciales por cara para recortar y plastificar |
| D8 | ~~¿La credencial vence?~~ | 3.1 | **Aceptada (14-10-2026):** solo con la fecha de fin del contrato; sin renovación periódica |
| D9 | ~~¿La verificación pública muestra la foto?~~ | 3.2 | **Aceptada (14-10-2026):** sí: foto, nombre, cargo, unidad y estado; nunca la cédula |
| D10 | ~~¿Dónde más se ve la foto?~~ | 1.2 | **Aceptada (14-10-2026):** avatar, expediente, credencial y verificación. El directorio telefónico queda fuera por ahora. Nunca el organigrama público |
| D11 | ~~¿La credencial lleva firma?~~ | 3.1 | **Aceptada (14-10-2026):** no; la valida el QR |
| D12 | ~~¿En qué orden?~~ | todo | **Aceptada (14-10-2026):** fotografías, identidad y credencial |
| D13 | ~~¿Se recoge la credencial impresa al desvincularse?~~ | 3.1 | **Aceptada (14-10-2026):** sí, como paso de la desvinculación a cargo de Comunicación Social |
