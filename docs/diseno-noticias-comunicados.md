# Diseño del módulo «Noticias y comunicados»

**SGTH — GAD Provincial de Esmeraldas** · Versión 2, 17 de octubre de 2026 · Base: `0f127676`

Estado: **diseño decidido**. Todas las decisiones de la sección 10 están tomadas (17-10-2026): D1 y D8 con respuestas propias y las demás aceptando la propuesta.

Marcas que usa el documento:

- **[Hoy]**: lo que existe en el código.
- **[Propuesta]**: lo que se plantea construir.
- **[Dn]**: decisión de Talento Humano (sección 10).

---

## 1. Resumen

1. **Lo que se pide.** Un módulo para que Talento Humano redacte, publique y retire **noticias y comunicados**, y para que cada servidor los lea en el **Portal del servidor**.
2. **No es un subsistema nuevo.** Quien publica es Talento Humano, así que la gestión entra al SGTH (menú *Talento Humano › Noticias y comunicados*), y la lectura al Portal. El subsistema «Comunicación» es de Comunicación Social y no se toca [D2].
3. **El hueco ya está reservado.** El inicio del Portal tiene una tarjeta «Noticias y comunicados» vacía que dice «Funcionalidad próximamente disponible», y la campana de la barra superior está apagada. Las dos se encienden con este módulo.
4. **Dos tipos que se comportan distinto** [D3]: la **noticia** informa (con imagen de portada); el **comunicado** es oficial de TH, puede llevar el número de su circular o memorando, un PDF adjunto y pedir **confirmación de lectura**.
5. **Cada publicación tiene destinatarios** [D4]: todos, unas unidades (con sus dependencias) o un régimen laboral. El Portal solo enseña al servidor lo que le corresponde.
6. **Lo publicado no se enmienda** [D5]: se retira con motivo y, si hace falta, se duplica como borrador corregido. Es la misma regla de las acciones de personal, y aquí importa por el acuse: no se puede cambiar el texto que alguien ya confirmó haber leído.
7. **El único canal es el Portal** [D8]: no se manda correo. El aviso llega por el inicio del Portal y la campana.
8. **Plan:** 4 PRs (sección 9): backend de gestión, pantallas de TH, Portal (lectura y acuse), seguimiento.

---

## 2. Lo que hay hoy [Hoy]

| Pieza | Dónde | Qué aprovecha el módulo |
|---|---|---|
| Tarjeta «Noticias y comunicados» vacía | `sgth-frontend/src/features/portal/components/NoticiasCard.tsx`, al lado de la ficha en `PortalHomeView` | Se rehace con datos reales; el sitio en el inicio ya está |
| Campana de notificaciones apagada (`Indicator disabled`) | `sgth-frontend/src/components/layout/Topbar.tsx` | Primer uso real: el número de comunicados sin leer [D7] |
| Aviso de documentos por acusar | `DocumentosPorAcusarAviso.tsx` (fase 6.3a) | Mismo patrón para «comunicados por confirmar» |
| Editor enriquecido (TipTap) | `features/dispensario/components/RichTextInput.tsx` | Se mueve al catálogo de `components/ui` y lo usan los dos |
| Limpieza de HTML al guardar | `app/Support/HtmlClinico.php` (Symfony HtmlSanitizer, lista blanca) | Mismo enfoque: el cuerpo se limpia al entrar, no al pintar |
| Archivos en disco privado servidos por controlador | Fotografías de Comunicación Social | Portada y adjuntos nunca por URL pública (la lección de LOTAIP en `docs/scheduler.md`) |
| Dropzone y recorte de imagen | `@mantine/dropzone`, `react-easy-crop` (fotografías) | La portada se recorta a 16:9 |
| Unidad y régimen del servidor | `servidores.unidad_administrativa_id`, `servidores.regimen_laboral`; `unidades_administrativas.unidad_padre_id` | Resolver los destinatarios sin pasar por el vínculo |
| Permisos adicionales por usuario | *Usuarios › Permisos adicionales* (`PermisosDrawer.tsx`, `UsuarioService::sincronizarPermisos`) | Dar `publicar-comunicados` a un asistente concreto [D1] |

No existe tabla, modelo ni ruta de noticias en el backend.

---

## 3. Conceptos

### 3.1 Tipos [Propuesta, D3]

| Tipo | Para qué | Portada | Referencia (n.º de documento) | Adjuntos PDF | Confirmación de lectura |
|---|---|---|---|---|---|
| **Noticia** | Eventos, logros, campañas de bienestar, capacitaciones | Sí, recomendada | No | Sí | No |
| **Comunicado** | Circulares, disposiciones, recordatorios de plazos, cambios de horario | Opcional | Sí, opcional (p. ej. «Circular GADPE-DATH-2026-045») | Sí (el documento firmado escaneado) | Opcional, por comunicado |

### 3.2 Estados [Propuesta]

Se guarda solo lo que decide una persona; lo que depende de la fecha se **deduce** al consultar, para no repetir el error de las subrogaciones, cuyo estado guardado mentía si el scheduler no corría.

| Estado | Se guarda | Cuándo | ¿Lo ve el servidor? |
|---|---|---|---|
| Borrador | `borrador` | Al crear o duplicar | No |
| Programado | `publicado` | `publicado_desde` > ahora | No, hasta la fecha |
| Publicado | `publicado` | Dentro de su vigencia | Sí |
| Vencido | `publicado` | `publicado_hasta` < ahora | Solo en el archivo [D6] |
| Retirado | `retirado` | TH lo retira, con motivo | No |

`publicado_hasta` es opcional: sin fecha, no vence.

### 3.3 Destinatarios [Propuesta, D4]

- **Todos** los usuarios con acceso al Portal.
- **Por unidad**: una o varias unidades administrativas; cada una con la casilla «incluir sus dependencias».
- **Por régimen**: LOSEP, Código del Trabajo, Servicios Profesionales.
- Unidad y régimen se pueden combinar (se cumplen los dos).

Los destinatarios se calculan **al leer**, no se congelan al publicar: quien ingresa la semana siguiente ve los comunicados vigentes de su unidad. Las dependencias sí se expanden al publicar (se guardan las unidades concretas), para que un cambio del orgánico no cambie en silencio a quién iba dirigido.

Una cuenta sin servidor asociado (`servidor_id` nulo) solo ve lo dirigido a todos.

### 3.4 Lectura y confirmación [Propuesta]

- **Leído**: se registra al abrir el detalle. Sirve para el punto de «nuevo», la campana y las estadísticas.
- **Confirmado**: solo en comunicados que lo piden. Botón «Confirmo que he leído este comunicado», con fecha y hora; no se deshace.
- Al vencer un comunicado con confirmación, las confirmaciones se cierran y las cifras quedan como estaban.

### 3.5 Destacados [Propuesta]

Hasta **3 publicaciones fijadas** a la vez; salen primero en el inicio y en el listado. Fijar una cuarta devuelve un aviso pidiendo desfijar otra.

---

## 4. Reglas [Propuesta]

1. **Solo el borrador se edita libremente.** En lo publicado se puede cambiar la fecha de fin y el fijado; el título, el cuerpo, la portada, los adjuntos y los destinatarios quedan congelados [D5].
2. **Corregir es retirar y duplicar.** «Duplicar» crea un borrador con todo copiado (adjuntos incluidos) y la referencia al original.
3. **El borrador se puede eliminar; lo publicado nunca se borra.** Se retira con motivo, sale del Portal y queda en el historial de TH con sus lecturas.
4. **Para publicar** se exigen título (máx. 150), resumen (máx. 300), cuerpo y, si hay fecha de fin, que sea posterior a la de inicio. La fecha de inicio puede ser futura (queda programado), pero no anterior a hoy.
5. **El cuerpo se limpia al guardar** con una lista blanca: párrafos, negrita, cursiva, subrayado, títulos, listas, citas, enlaces (`http`, `https`, `mailto`) y resaltado. Sin imágenes dentro del texto: la imagen va como portada.
6. **Archivos:** portada JPG, PNG o WebP de hasta 5 MB, recortada a 16:9; hasta 5 adjuntos PDF de hasta 10 MB cada uno. Se guardan en el disco privado y se sirven por controlador, que comprueba que quien pide es destinatario o de TH.
7. **El aviso de publicación muestra cuántos lo verán** («Lo verán 312 servidores») antes de confirmar.
8. **Auditoría:** quién creó, quién publicó y cuándo, quién retiró, cuándo y por qué.

---

## 5. Portal del servidor [Propuesta]

### 5.1 Inicio

La tarjeta «Noticias y comunicados» (misma posición, junto a la ficha) pasa a mostrar:

- Hasta 5 publicaciones: fijadas primero, luego las más recientes.
- Por cada una: insignia del tipo, título, fecha («hace 2 días»), resumen en dos líneas y un punto si no la ha leído.
- Al pie, «Ver todas» con el número sin leer.
- Sin publicaciones: el estado vacío del catálogo («Talento Humano no ha publicado novedades»), sin el «próximamente».

Encima de la tarjeta, como el aviso de documentos laborales: **«Tiene 2 comunicados que requieren su confirmación de lectura»**, con enlace.

### 5.2 Listado — `/portal/comunicados`

- Entrada nueva en el menú del Portal, grupo *Mi Espacio*: **Noticias y comunicados**.
- Tarjetas con portada (o el icono del tipo si no tiene), tipo, título, fecha y resumen.
- Filtros: Todas · Noticias · Comunicados · Sin leer · Por confirmar; búsqueda por título.
- Pestaña **Archivo** con los vencidos [D6]. Paginado.

### 5.3 Detalle — `/portal/comunicados/{id}`

- Cabecera: tipo, referencia del documento si la tiene, «Talento Humano», fecha de publicación.
- Portada, cuerpo y adjuntos para descargar.
- Si pide confirmación: botón **«Confirmo que he leído este comunicado»** con confirmación; después, «Confirmó la lectura el 17/10/2026 a las 10:42».
- Abrirlo lo marca como leído.

### 5.4 Campana [D7]

La campana de la barra superior se enciende con el número de comunicados sin leer (se consulta cada 5 minutos) y abre un menú con los últimos, que enlaza al detalle.

---

## 6. Talento Humano [Propuesta]

### 6.1 Menú

*SGTH › Talento Humano › **Noticias y comunicados*** (`/sgth/comunicados`), visible con `redactar-comunicados` o `ver-lecturas-comunicados`.

### 6.2 Listado

Tabla (`SgthTable`) con: título, tipo, estado (con su color), destinatarios en una línea («Todos», «Dirección de TH y 3 más», «Código del Trabajo»), vigencia, lecturas (`leídos / destinatarios` y, si aplica, confirmados), acciones. Filtros por estado, tipo y año; búsqueda por título o referencia.

### 6.3 Formulario — `/sgth/comunicados/nuevo` y `/{id}/editar`

Página completa en cuatro bloques:

1. **Datos:** tipo, título, resumen, referencia del documento (solo comunicado).
2. **Contenido:** editor enriquecido; portada con recorte 16:9; adjuntos PDF.
3. **Destinatarios:** Todos / Por unidad / Por régimen; selector de unidades con «incluir dependencias»; el conteo de destinatarios se actualiza al cambiar.
4. **Publicación:** fecha de inicio (hoy por defecto), fecha de fin opcional, fijar, pedir confirmación de lectura (solo comunicado).

Botones: *Guardar borrador*, *Vista previa*, *Publicar*. *Publicar* solo aparece a quien tiene `publicar-comunicados`; quien no lo tiene deja el borrador listo y lo publica otra persona [D1]. La vista previa usa **el mismo componente** que el detalle del Portal, para que lo que TH revisa sea exactamente lo que ve el servidor.

### 6.4 Seguimiento — `/sgth/comunicados/{id}`

- El comunicado tal como se publicó, con su historial (creado, publicado, retirado y por quién).
- Tarjetas: destinatarios · leído (%) · confirmado (%).
- Tabla de destinatarios con su estado (sin leer, leído, confirmado) y fecha, filtrable por unidad.
- **Excel de pendientes de confirmación**, para el seguimiento de circulares que deben acatarse [D9].

### 6.5 Acciones

| Acción | Sobre | Permiso |
|---|---|---|
| Crear, editar, adjuntar, vista previa, duplicar | Borrador | `redactar-comunicados` |
| Eliminar | Borrador | `redactar-comunicados` |
| Publicar (ahora o programado) | Borrador | `publicar-comunicados` |
| Retirar (con motivo) | Publicado | `publicar-comunicados` |
| Fijar / desfijar, cambiar fecha de fin | Publicado | `publicar-comunicados` |
| Ver lecturas y descargar Excel | Publicado o retirado | `ver-lecturas-comunicados` |

---

## 7. Arquitectura [Propuesta]

### 7.1 Backend

El espacio de nombres es `Comunicados`, distinto de `Comunicacion` (el subsistema de Comunicación Social).

- **Modelos** (`app/Models/Comunicados`): `Comunicado`, `ComunicadoAdjunto`, `ComunicadoLectura`.
- **Enums**: `TipoComunicado` (noticia, comunicado), `EstadoComunicado` (borrador, publicado, retirado: lo guardado), `VigenciaComunicado` (lo que se enseña: suma programado y vencido, deducidos de las fechas) y `AudienciaComunicado` (todos, segmentada).
- **Servicios** (`app/Services/Comunicados`, cada uno con su interfaz en `app/Contracts/Comunicados`): `ComunicadoService` (el ciclo: borrador, publicar, retirar, duplicar, fijado y fecha de fin, listado), `DestinatariosComunicadoService` (a quién llega y cuántos son) y `ArchivosComunicadoService` (portada y adjuntos).
- **Sin policy** [Hecho, PR 1]: cada ruta pide su permiso (`permission:` como el resto del API) y las reglas de estado viven en `ComunicadoService`. Así valen también para `admin-ti`, a quien el `Gate::before` le aprobaría cualquier habilidad de una policy: tampoco él edita lo publicado.
- **HTML**: `app/Support/HtmlEnriquecido.php` con la lista blanca de la regla 5; `HtmlClinico` se queda como está. De lo que no se permite se quita la etiqueta y se deja el texto cuando es un contenedor (`div`, `span`, un `h1` pegado); `script`, `style`, `img`, `iframe` y similares se van con su contenido.

Rutas de TH (`/v1/comunicados`):

| Método | Ruta | Qué hace |
|---|---|---|
| GET | `/comunicados` | Listado con filtros |
| POST | `/comunicados` | Crea un borrador |
| GET | `/comunicados/{id}` | Detalle e historial |
| PUT | `/comunicados/{id}` | Edita el borrador |
| DELETE | `/comunicados/{id}` | Elimina el borrador |
| GET / POST / DELETE | `/comunicados/{id}/portada` | La ve, la sube o cambia, la quita |
| GET | `/comunicados/{id}/adjuntos/{adjunto}` | Descarga un PDF (vista previa y seguimiento) |
| POST / DELETE | `/comunicados/{id}/adjuntos[/{adjunto}]` | Agrega o quita un PDF |
| POST | `/comunicados/destinatarios/conteo` | Cuántos lo verían |
| POST | `/comunicados/{id}/publicar` | Publica o programa |
| POST | `/comunicados/{id}/retirar` | Retira con motivo |
| POST | `/comunicados/{id}/duplicar` | Nuevo borrador desde este |
| PATCH | `/comunicados/{id}/publicacion` | Fijado y fecha de fin |
| GET | `/comunicados/{id}/lecturas` | Destinatarios y su estado |
| GET | `/comunicados/{id}/lecturas/excel` | Pendientes de confirmación |

Rutas del Portal (`/v1/autoservicio/comunicados`, con `acceso-autoservicio`):

| Método | Ruta | Qué hace |
|---|---|---|
| GET | `/comunicados` | Los visibles para el usuario, paginados, con filtros y archivo |
| GET | `/comunicados/resumen` | Los 5 del inicio, sin leer y por confirmar (inicio y campana) |
| GET | `/comunicados/{id}` | Detalle; registra la lectura |
| POST | `/comunicados/{id}/confirmacion` | Confirma la lectura |
| GET | `/comunicados/{id}/portada` | La imagen |
| GET | `/comunicados/{id}/adjuntos/{adjunto}` | El PDF |

Un comunicado no visible para el usuario responde 404, no 403: no se revela que existe.

### 7.2 Modelo de datos

**`comunicados`**

| Columna | Tipo | Nota |
|---|---|---|
| `id` | bigint | |
| `tipo` | varchar | `TipoComunicado` |
| `titulo` | varchar(150) | |
| `resumen` | varchar(300) null | Se exige al publicar |
| `cuerpo` | text null | HTML ya limpio; se exige al publicar |
| `referencia` | varchar(100) null | N.º de circular o memorando |
| `portada_path` | varchar null | Disco privado |
| `estado` | varchar | `EstadoComunicado` |
| `audiencia` | varchar | `todos` o `segmentada` |
| `regimenes` | jsonb null | Vacío = cualquier régimen |
| `publicado_desde` | timestamp null | Al publicar: ahora, o el inicio del día programado |
| `publicado_hasta` | timestamp null | El final de ese día (23:59:59) |
| `fijado` | boolean | |
| `requiere_confirmacion` | boolean | Solo comunicados |
| `duplicado_de_id` | FK null | El original, si se duplicó |
| `creado_por`, `publicado_por`, `retirado_por` | FK users | |
| `publicado_at`, `retirado_at` | timestamp null | |
| `motivo_retiro` | varchar(500) null | |
| `created_at`, `updated_at` | | |

Índice en `(estado, publicado_desde)`.

**`comunicado_unidades`**: `comunicado_id`, `unidad_administrativa_id`, `seleccionada` (boolean: la eligió TH o entró como dependencia al publicar) e `incluye_dependencias` (boolean, en las elegidas: si se marcó «incluir dependencias»). Clave compuesta. En un borrador solo están las elegidas; al publicar se agregan sus dependencias con `seleccionada` en falso.

**`comunicado_adjuntos`**: `id`, `comunicado_id`, `nombre_original`, `path`, `tamano`, `orden`, timestamps.

**`comunicado_lecturas`**: `id`, `comunicado_id`, `user_id`, `leido_at`, `confirmado_at` null. Único en `(comunicado_id, user_id)`.

### 7.3 Roles y permisos

Tres permisos nuevos en `App\Enums\Permiso`, en `RolPermisoSeeder` y en una migración que los crea y asigna (como `2026_11_01_000000_roles_y_permisos_de_comunicacion`). Ojo con la prueba de paridad entre migración y seeder.

| Permiso | admin-uath | asistente-uath | analista-uath | auditor |
|---|---|---|---|---|
| `redactar-comunicados` | ✓ | ✓ | | |
| `publicar-comunicados` | ✓ | Solo como permiso adicional [D1] | | |
| `ver-lecturas-comunicados` | ✓ | ✓ | | ✓ |

`publicar-comunicados` **no se agrega al rol** `asistente-uath`. Se le da a la persona concreta desde *Usuarios › Permisos adicionales*, la pantalla que ya existe para eso. Así TH decide qué asistente publica sin que todos los asistentes puedan hacerlo, y se le quita igual de fácil. Una prueba Pest cubre los dos casos: el asistente sin el permiso recibe 403 al publicar; con el permiso adicional, publica.

Leer en el Portal usa el `acceso-autoservicio` que ya existe.

### 7.4 Frontend

- **TH** [Hecho, PR 2]: las pantallas en `src/app/(sgth)/sgth/comunicados` (`ComunicadosView`, `NuevoComunicadoView`, `EditarComunicadoView`, `DetalleComunicadoView`) y el módulo en `src/features/comunicados`, con sus `hooks`, `services`, `schemas` (zod) y `utils`. **La vista previa es el detalle**, no un cajón aparte: se guarda el borrador, el detalle muestra cómo lo verá el servidor y desde ahí se publica. La portada y los documentos se agregan en la pantalla de edición, porque necesitan que el borrador exista. El seguimiento con su Excel queda para el PR 4.
- **Portal** (`src/features/portal`): `NoticiasCard` rehecha, `MisComunicadosView`, `ComunicadoDetalleView`, `ComunicadosPorConfirmarAviso`.
- **Compartido**: `ComunicadoContenido` pinta título, portada, cuerpo y adjuntos; lo usan la vista previa de TH y el detalle del Portal.
- **Catálogo** [Hecho, PR 2]: `RichTextInput` sale de `features/dispensario` a `src/components/ui`, con la prop `completo` (títulos, citas y enlaces). El recorte de imagen y la imagen como `data:` URL pasan a `src/lib/` (`recortarImagen`, `dataUrl`), que ahora comparten Comunicación Social y este módulo.
- **Rutas**: `ROUTES.SGTH.COMUNICADOS` y `ROUTES.PORTAL.COMUNICADOS`; entradas en `NAV_SGTH` y `NAV_PORTAL`.
- **Tipos**: `npm run types:sync`. En el PR 1 no se regeneraron: llevaban sin regenerarse desde el PR #430 y el diff arrastraba unas 88 000 líneas de otros módulos. Se regeneraron en el PR 2, con `tsc` limpio. Para eso se nombraron las dos rutas GET de las reglas de préstamo de TIC, que compartían método y el generador les daba el mismo identificador. El tipo del comunicado va escrito a mano en `features/comunicados/services/tipos.ts`, como en los módulos de TIC y de Comunicación: Scramble deja `unidades` como `string` y `regimenes` como `unknown[]`.

---

## 8. Sin aviso por correo [D8, resuelta]

No se manda correo al publicar (decisión del 17-10-2026). El servidor se entera por tres vías, todas dentro del Portal:

- la tarjeta del inicio, con el punto de «no leído»;
- el aviso de «comunicados que requieren su confirmación», arriba del inicio;
- el número de la campana, en cualquier pantalla del sistema.

Consecuencias:

- **No hay tarea programada.** Un comunicado programado aparece solo al llegar su fecha, porque el estado se deduce al consultar (sección 3.2); no hace falta tocar `docs/scheduler.md`.
- **Quien no entra al Portal no se entera.** En los comunicados con confirmación, el seguimiento de TH (sección 6.4) y su Excel de pendientes son la forma de saber quién falta y avisarle por otro medio.

---

## 9. Plan de trabajo

| PR | Contenido | Pruebas |
|---|---|---|
| **1. Backend de gestión** [Hecho] | Migraciones, modelos, enums, permisos (enum, seeder, migración, módulo «Noticias y comunicados» en Permisos adicionales), `HtmlEnriquecido`, servicios, rutas de TH, portada y adjuntos | Pest: permisos por rol, asistente con y sin el permiso adicional, reglas 1–8, limpieza de HTML, archivos fuera del disco público |
| **2. Pantallas de TH** [Hecho] | Menú, listado, formulario, portada y documentos, detalle con vista previa, publicar, retirar, duplicar, fijar, fecha de fin; regenerar los tipos | Lint, tipos, revisión en el navegador |
| **3. Portal** | Rutas de autoservicio, tarjeta del inicio, listado, detalle, lectura, confirmación, aviso de pendientes, campana | Pest: visibilidad por unidad, dependencias, régimen, cuenta sin servidor, 404 al que no es destinatario; navegador |
| **4. Seguimiento** | Pantalla de seguimiento con las cifras de lectura, tabla de destinatarios y Excel de pendientes de confirmación | Pest: cifras por unidad, Excel solo con `ver-lecturas-comunicados` |

Producción: `php artisan migrate` (crea tablas y permisos). Sin datos que conciliar: el módulo nace vacío.

---

## 10. Decisiones

| # | Pregunta | Sección | Propuesta |
|---|---|---|---|
| D1 | ~~¿Quién publica?~~ | 7.3 | **Resuelta (17-10-2026):** redactan `admin-uath` y `asistente-uath`; publica `admin-uath` por su rol, y un asistente solo si se le da `publicar-comunicados` como permiso adicional desde Usuarios |
| D2 | ~~¿Comunicación Social también publica noticias institucionales?~~ | 1 | **Aceptada (17-10-2026):** no por ahora. Los permisos lo permiten después sin rehacer nada |
| D3 | ~~¿Bastan dos tipos (noticia y comunicado)? ¿Hace falta «evento», con fecha y lugar?~~ | 3.1 | **Aceptada (17-10-2026):** dos tipos; el evento queda fuera de esta versión |
| D4 | ~~¿Destinatarios por unidad y por régimen, o siempre todos?~~ | 3.3 | **Aceptada (17-10-2026):** todos, por unidad (con dependencias) y por régimen |
| D5 | ~~¿Se corrige lo publicado?~~ | 4 | **Aceptada (17-10-2026):** no: se retira y se duplica |
| D6 | ~~¿Los vencidos siguen visibles en el Portal?~~ | 3.2 | **Aceptada (17-10-2026):** sí, en la pestaña Archivo, sin límite de tiempo |
| D7 | ~~¿Se enciende la campana con los comunicados sin leer?~~ | 5.4 | **Aceptada (17-10-2026):** sí |
| D8 | ~~¿Aviso por correo?~~ | 8 | **Resuelta (17-10-2026):** no. El Portal es el único canal: inicio, aviso de pendientes y campana |
| D9 | ~~¿TH necesita el Excel de quién no confirmó?~~ | 6.4 | **Aceptada (17-10-2026):** sí |
| D10 | ~~¿La portada es obligatoria en las noticias?~~ | 4 | **Aceptada (17-10-2026):** no; sin portada se muestra el icono del tipo |
