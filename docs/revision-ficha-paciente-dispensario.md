# Revisión de la ficha del paciente en el Dispensario

**Versión 2, del 2026-10-29.** Sigue a #610, que recortó la agenda y la cola de triaje con
`AgendaService::PACIENTE`. Aquí se revisan las ocho cargas de `servidor` o `cargaFamiliar.servidor` sin
columnas que quedaban en el Dispensario. Durante la revisión aparecieron tres más con el mismo patrón
(cargas 9 a 11).

**El problema.** `Servidor` no tiene `$hidden`. Si se carga `servidor` sin columnas, el JSON lleva la ficha
entera:

- identidad: cédula, fecha de nacimiento, género, estado civil, pasaporte y papeleta de votación;
- contacto: celular, teléfono, correo personal, dirección y contacto de emergencia (nombre, parentesco y
  teléfono);
- salud: tipo de sangre, y las marcas de discapacidad y de enfermedad catastrófica;
- trabajo: el código médico y los datos del nombramiento.

`CargaFamiliar` tampoco oculta nada: lleva cédula, nacimiento, género, discapacidad, enfermedad catastrófica
y observaciones.

**Decisiones:** el usuario aprobó las siete recomendaciones (D1–D7, todas la opción a) el mismo
2026-10-29. Se aplican en un PR, en la rama `fix/dispensario-sin-ficha-del-paciente`, como describe la
sección 6. La prueba es `tests/Feature/Dispensario/FichaDelPacienteRecortadaTest.php`. Las líneas citadas
en el informe son las de `main` antes de ese PR.

## 1. Resumen

| # | Carga | Endpoint | Quién lo recibe | Qué lee la pantalla del paciente | Propuesta |
|---|---|---|---|---|---|
| 1 | `HistoriaClinicaService::listar` (:30) | `GET historias-clinicas` | médico, odontólogo, enfermera, admin-dispensario | **Ninguna pantalla lo llama** | **Retirar la ruta** |
| 2 | `HistoriaClinicaService::obtener` (:77) | `GET historias-clinicas/{id}` | los mismos | Ninguna pantalla lo llama | **Retirar la ruta** |
| 3 | `HistoriaClinicaService::buscarPorCedula` (:178) | `GET historias-clinicas/buscar-por-cedula` | los mismos | Ninguna pantalla lo llama | **Retirar la ruta** |
| 4 | `ConsultaMedicaController::index` (:71) | `GET consultas` | médico, odontólogo, con alcance clínico | Nada de `historia_clinica` | Solo el nombre |
| 5 | `ConsultaMedicaController::show` (:255) | `GET consultas/{id}` | los mismos | Nada de `historia_clinica` | Solo el nombre |
| 6 | `AtencionEnfermeriaService::RELACIONES` (:23) | `GET` y `POST atenciones-enfermeria`, `PATCH …/anular` | leer: los 4 roles clínicos; registrar y anular: enfermera, admin-dispensario | Nombre y apellido | Solo el nombre |
| 7 | `SolicitudCertificacionController::show` (:279) | `GET solicitudes-certificacion/{id}` | medico-ocupacional, admin-dispensario, admin-uath, asistente-uath | id, género, tipo de sangre, discapacidad y puesto | **Lo que usa la FEMO** |
| 8 | `CertificadoMedicoService::generarPdf` (:215) | `GET certificados-medicos/{id}/pdf` | médico, odontólogo, admin-dispensario | — (es un PDF, no va en el JSON) | `RELACIONES` y el titular, por orden |
| 9 | `HistoriaClinicaService::obtenerContextoConsulta` (:547) | `GET historias-clinicas/{id}/contexto-consulta` | los 4 roles clínicos, sin alcance | Nada del paciente | Solo el nombre |
| 10 | `HistoriaClinicaService::registrarConsulta` (:335) y `actualizarConsulta` (:497) | `POST consultas`, `PATCH consultas/{id}` | médico, odontólogo | Nada de `historia_clinica` | Solo el nombre |
| 11 | `medico` sin columnas en las cargas 4, 5 y 10 | los de 4, 5 y 10 | médico, odontólogo | `medico.nombre_completo` | Las columnas de `nombre_completo` (patrón de #595) |

**Lo que más pesa:**

- **Las cargas 1 a 3 están en rutas que ya no usa ninguna pantalla.** El frontend dejó de llamarlas el
  2026-10-21, en 997307f0 («fuera el código muerto de Consultas y Recetas»), pero las rutas siguen activas.
  - **La 1 es la peor del informe.** Con `?per_page=100&search=`, cualquiera de los cuatro roles clínicos
    puede recorrer, de cien en cien, las fichas de todos los pacientes con historia.
  - **La 2 se salta el PR 5.** Manda descifradas las diez últimas consultas a la enfermera y a la
    administración, que desde el PR 5 del plan de roles no leen consultas.
- **La 7 es la única con uso clínico real.** La FEMO toma de ahí el género, el tipo de sangre y la
  discapacidad. El resto de la ficha (contacto de emergencia, dirección, celular, correo personal,
  pasaporte) llega también a la administración del Dispensario, que no tiene `ver-expediente-todos`.
- **Ningún recorte rompe el backend.** Ningún servicio vuelve a leer un campo que se quitaría:
  - `CondicionDeclaradaService` busca al paciente por su id;
  - el buscador del listado filtra con `whereHas`;
  - el PDF del certificado lee solo lo que la propuesta deja: nombre, cédula y nombre del titular.
- **Los recortes no tocan el frontend.** Sus tipos ya piden menos de lo que la propuesta deja. Solo retirar
  las rutas cambia `api.generated.ts`.

## 2. Cómo se revisó

- **Backend.** Se leyó cada carga con el resto del método y se siguió qué se hace con el modelo después:
  servicios, accesores y vistas PDF. En los modelos implicados se buscaron accesores y `#[Appends]` que
  leyeran al paciente. Solo hay uno, `HistoriaClinica::nombre_paciente`: usa nombre y apellido, y no está en
  `$appends`.
- **Permisos.** Las rutas salen de `routes/api.php` y los roles, de `RolPermisoSeeder`. En producción puede
  haber además permisos directos dados desde Usuarios.
- **Frontend.**
  - Las llamadas a cada URL en `sgth-frontend/src`; los servicios están en `features/dispensario/services`.
  - Para cada respuesta, el tipo TypeScript y los componentes que la pintan, en
    `features/dispensario/components`, en `hooks` y en `app/(salud)`.
- **Pruebas.** Las que revisan estas respuestas, en `tests/Feature/Dispensario`.
- **Barrido.** Un grep de `'servidor'`, `'cargaFamiliar'` y sus relaciones anidadas en
  `app/Services/Dispensario`, `app/Http/Controllers/Dispensario` y `app/Http/Resources`. De ahí salieron las
  cargas 9 a 11 y las que se revisaron sin fuga (sección 5.3).
- **Nada se probó con peticiones**, porque el worktree no tiene `vendor`: todo sale de leer el código. La
  carga 11 es el mecanismo que documentó #595: `nombre_completo` está en `#[Appends]`, el accesor carga el
  `servidor` entero y la serialización lo incluye.
- **Base:** `main` en 4419ff4d, sin #610, que sigue abierto. Las líneas citadas son las de `main`.

## 3. Carga por carga

### Carga 1. Listado de historias clínicas

| | |
|---|---|
| **Dónde** | `HistoriaClinicaService::listar`, `HistoriaClinicaService.php:29-31`: `with(['servidor', 'cargaFamiliar.servidor'])`. |
| **Endpoint** | `GET /api/v1/dispensario/historias-clinicas`, en el grupo de `permission:ver-historia-clinica` (`routes/api.php:2013-2016`). Pagina hasta 100 y acepta `search` por nombre, apellido o cédula. |
| **Quién lo recibe** | médico, odontólogo, enfermera y admin-dispensario. No hay alcance clínico: cualquier paciente. |
| **Qué lee el frontend** | Nada: ninguna pantalla lo llama. 997307f0 borró `historiaClinicaService.listar` porque nadie lo usaba. |
| **Pruebas** | Tres lo llaman y ninguna revisa al paciente: `AgendaTriajePorPermisosTest.php:47` y `:59` (la matriz de quién entra con `ver-historia-clinica`), `AuxiliarFarmaciaTest.php:182` (el auxiliar no entra) y `PerPageConTopeTest.php:31` (el techo de `per_page`). |
| **Uso clínico** | Ninguno: es un listado sin pantalla. |
| **Uso posterior en el backend** | Ninguno. El filtro `search` va por `whereHas` y no depende de la carga. |
| **Propuesta** | **Retirar la ruta**, junto con el método del servicio y su firma en `HistoriaClinicaServiceInterface` (D1). |
| **Si se deja** | `servidor:id,nombre,apellido,cedula` y `cargaFamiliar:id,nombres,apellidos,cedula,parentesco`, que es lo que declara el tipo `HistoriaClinica` (`historiaClinicaService.ts:30-42`). Sin `cargaFamiliar.servidor`, porque nadie lee al titular. La cédula ya viaja en `cedula_paciente`, así que no expone nada nuevo. |

### Carga 2. Detalle de la historia clínica

| | |
|---|---|
| **Dónde** | `HistoriaClinicaService::obtener`, `:76-83`: `servidor`, `cargaFamiliar.servidor`, alergias, antecedentes y **las diez últimas `consultasMedicas`**. |
| **Endpoint** | `GET historias-clinicas/{id}` (`routes/api.php:2020`), con `ver-historia-clinica`. |
| **Quién lo recibe** | Los mismos cuatro roles, para cualquier historia, por su id. |
| **Qué lee el frontend** | Nada: 997307f0 también borró `historiaClinicaService.obtener`. Ninguna prueba lo llama. |
| **Uso clínico** | Ninguno en pantalla. |
| **Uso posterior en el backend** | Ninguno. |
| **Además** | Las consultas salen **descifradas**: `ConsultaMedica` cifra el motivo, la enfermedad actual, el examen físico, el diagnóstico, el plan y las notas, pero no oculta nada al serializar. Es una puerta trasera a lo que cerró el PR 5 del plan de roles. `GET consultas` solo deja leer al médico y al odontólogo que atienden al paciente; esta ruta las da también a la enfermera, a la administración y a cualquier médico. |
| **Propuesta** | **Retirar la ruta** (D1). |
| **Si se deja** | Lo mismo que la carga 1, y **sin `consultasMedicas`** (D2). |

### Carga 3. Historia por cédula

| | |
|---|---|
| **Dónde** | `HistoriaClinicaService::buscarPorCedula`, `:175-183`. |
| **Endpoint** | `GET historias-clinicas/buscar-por-cedula?cedula=` (`routes/api.php:2018`), con `ver-historia-clinica`. Busca la cédula exacta en `cedula_paciente`. |
| **Quién lo recibe** | Los mismos cuatro roles. |
| **Qué lee el frontend** | Nada. La recepción busca por `pacientes/buscar`, y `PacienteService` arma su respuesta campo a campo. 997307f0 borró `historiaClinicaService.buscarPorCedula`. |
| **Pruebas** | La llama `HistoriaUnicaPorPacienteTest.php:84`, que solo revisa `datos.id`. |
| **Uso clínico** | Ninguno. |
| **Uso posterior en el backend** | Ninguno. |
| **Propuesta** | **Retirar la ruta** (D1). La prueba comprobaría lo mismo con `HistoriaClinica::where('cedula_paciente', …)` o con `pacientes/buscar`. |
| **Si se deja** | Lo mismo que la carga 1. |

### Carga 4. Listado de consultas

| | |
|---|---|
| **Dónde** | `ConsultaMedicaController::index`, `:70-77`: `historiaClinica.servidor`, `historiaClinica.cargaFamiliar` y `medico`, los tres sin columnas. |
| **Endpoint** | `GET consultas` (`routes/api.php:2047-2050`), con `crear-consulta`. Si llega `historia_clinica_id`, exige `AlcanceClinico::atiendeHistoria`; si no, devuelve las consultas propias. |
| **Quién lo recibe** | médico y odontólogo, sobre los pacientes que atienden. |
| **Qué lee el frontend** | Lo piden `TabHistorial` (el historial dentro de la atención) y `SubirResultadoModal` (para elegir la consulta del resultado), siempre con `historia_clinica_id`, por `consultaMedicaService.listarPorHistoria`. El tipo `ConsultaMedica` (`consultaMedicaService.ts:40-71`) **no declara `historia_clinica`** y ningún componente la lee: el paciente ya está en la cabecera de la atención. Del médico, `ConsultaItem.tsx:69` pinta `medico.nombre_completo`. |
| **Uso clínico** | Ninguno de la ficha: quien lo pide ya está atendiendo al paciente. |
| **Uso posterior en el backend** | Ninguno: pagina y devuelve. |
| **Propuesta** | `historiaClinica.servidor:id,nombre,apellido` y `historiaClinica.cargaFamiliar:id,nombres,apellidos` (D3). Se deja la identidad aunque ninguna pantalla la pinte, como hizo `CertificadoMedicoService::RELACIONES`. Los datos de la historia (grupo sanguíneo, medicación habitual) se quedan: son del paciente que se está atendiendo. Lo del `medico`, en la carga 11. |

### Carga 5. Detalle de la consulta

| | |
|---|---|
| **Dónde** | `ConsultaMedicaController::show`, `:254-264`: lo mismo que la carga 4, más alergias, antecedentes, recetas, diagnósticos y resultados. |
| **Endpoint** | `GET consultas/{id}` (`routes/api.php:2074`), con `crear-consulta`. `puedeLeer` deja leer a quien firmó la consulta o a quien atiende al paciente. |
| **Quién lo recibe** | médico y odontólogo. |
| **Qué lee el frontend** | `DetalleConsultaDrawer.tsx:43` y `AtencionPanel` (`useConsultaMedicaDetalle`). Ninguno lee `historia_clinica`, ni siquiera sus alergias o antecedentes, que el panel de contexto trae por su lado. `DetalleConsultaDrawer.tsx:76` pinta `medico.nombre_completo`. |
| **Uso clínico** | Ninguno de la ficha. |
| **Uso posterior en el backend** | Ninguno. |
| **Propuesta** | Lo mismo que la carga 4. Las alergias y los antecedentes se quedan aunque hoy nadie los lea desde aquí: son datos clínicos y van a quien atiende. |

### Carga 6. Atenciones de enfermería

| | |
|---|---|
| **Dónde** | `AtencionEnfermeriaService::RELACIONES`, `:18-24`: `servidor` y `cargaFamiliar.servidor`. La usan listar, registrar y anular. |
| **Endpoint** | `GET atenciones-enfermeria`, con `ver-historia-clinica` (`routes/api.php:1991-1994`). `POST` y `PATCH {id}/anular`, con `registrar-triaje` (`:1997-2005`). |
| **Quién lo recibe** | **Leer:** médico, odontólogo, enfermera y admin-dispensario, con todas las atenciones de todos los pacientes (sin alcance). **Registrar y anular:** enfermera y admin-dispensario. |
| **Qué lee el frontend** | `atencionesEnfermeria.columns.tsx:17-19` pinta `servidor.nombre`, `servidor.apellido`, `carga_familiar.nombres` y `carga_familiar.apellidos`. Del alta solo se usa el `folio` (`EnfermeriaAtenderPacienteView.tsx:83`). El tipo (`atencionEnfermeriaService.ts:33-42`) declara eso y nada más, y nadie lee al titular de la carga. |
| **Uso clínico** | Ninguno: es la lista de servicios del día. |
| **Uso posterior en el backend** | Ninguno: `registrar` y `anular` recargan solo para la respuesta. |
| **Propuesta** | `servidor:id,nombre,apellido` y `cargaFamiliar:id,nombres,apellidos`, sin `cargaFamiliar.servidor`. |

### Carga 7. Detalle de la solicitud de certificación (la FEMO)

| | |
|---|---|
| **Dónde** | `SolicitudCertificacionController::show`, `:278-292`: `servidor` sin columnas (con su puesto, cargo, unidad y discapacidades) y `postulante` sin columnas. |
| **Endpoint** | `GET solicitudes-certificacion/{id}` (`routes/api.php:2252-2260`), con `ver-certificaciones-medicas`. |
| **Quién lo recibe** | medico-ocupacional (rol complementario de `medico`), admin-dispensario, admin-uath y asistente-uath. Los dos de Talento Humano ya ven el expediente con `ver-expediente-todos`. **admin-dispensario no lo ve**, pero aquí recibe la ficha entera. |
| **Qué lee el frontend** | Solo la llama `NuevaFemoView` (`app/(salud)/salud/sso/femo/nueva/[solicitudId]`), por `useSolicitudDetalle`. **Del servidor:** `id`, `puesto` (cargo con su CIUO y unidad), `tiene_discapacidad` y `discapacidades[].porcentaje` (`useFemoDesdeSolicitud.ts:39-63`), `genero` y `tipo_sangre` (`NuevaFemoView.tsx:149-171`). **Del postulante:** `id`, `puesto`, `genero` y `tipo_sangre`. El nombre y la cédula salen de `nombres_paciente` y `cedula_paciente`, que son columnas de la solicitud. El tipo (`solicitudCertificacion.types.ts:92-118`) declara además nombre, apellido y cédula. |
| **Uso clínico** | **Sí: es la única carga del informe que lo tiene.** El género decide si se muestra el bloque reproductivo, el tipo de sangre llena el grupo sanguíneo y la discapacidad llega marcada con el porcentaje mayor. **La enfermedad catastrófica no se usa:** la FEMO tiene la casilla (`FemoSeccionDatosUsuario.tsx:103`), pero no la prellena (D5). La edad la calcula el PDF del FEMO por su cuenta (`femo/_pagina-1.blade.php:4`) y la pantalla no la muestra. La FEMO no usa el contacto de emergencia, la dirección ni el celular. |
| **Uso posterior en el backend** | Ninguno: devuelve la solicitud tal cual. Las relaciones anidadas, eso sí, piden columnas del padre: `servidor.puesto` necesita `puesto_id` y `servidor.discapacidades`, el `id`. En el postulante, igual. |
| **Pruebas** | `CondicionDeclaradaTest.php:112-115` revisa `datos.servidor.tiene_discapacidad` y `discapacidades.0.porcentaje`. Con la propuesta siguen pasando. |
| **Propuesta** | `servidor:id,nombre,apellido,cedula,genero,tipo_sangre,tiene_discapacidad,puesto_id` y `postulante:id,nombres,apellidos,cedula,correo,genero,tipo_sangre,puesto_id`. Las relaciones anidadas quedan como están. Al postulante se le quitan el teléfono, el estado civil, el nacimiento y la provincia y el cantón de nacimiento (D6). |

### Carga 8. PDF del certificado médico

| | |
|---|---|
| **Dónde** | `CertificadoMedicoService::generarPdf`, `:214-219`: `consultaMedica.historiaClinica.servidor`, `…cargaFamiliar.servidor`, `emisor.servidor` y `anulador.servidor`, todo sin columnas. |
| **Endpoint** | `GET certificados-medicos/{id}/pdf` (`routes/api.php:2118`), con `crear-consulta\|supervisar-dispensario`. |
| **Quién lo recibe** | médico, odontólogo y admin-dispensario, **como PDF**: lo cargado no viaja en un JSON. |
| **Qué lee** | La vista `pdf/dispensario/certificado-medico.blade.php` usa `datosDelPaciente` (`:238-270`) y el `nombre_completo` del emisor y del anulador. `datosDelPaciente` da el nombre, la cédula y, para un familiar, el nombre del titular. |
| **Uso clínico** | — |
| **Uso posterior en el backend** | **Sí:** `datosDelPaciente` lee `servidor->cedula`, `cargaFamiliar->cedula` y el nombre y apellido de `cargaFamiliar->servidor`. Hay que dejarlos. |
| **Propuesta** | **No es una fuga, es orden:** `[...self::RELACIONES, 'consultaMedica.historiaClinica.cargaFamiliar.servidor:id,nombre,apellido']`. `RELACIONES` ya trae la cédula del paciente, el `servidor_id` de la carga y las columnas que pide `nombre_completo` para el emisor y el anulador. Puede ir en el mismo PR o quedarse como está. |

### Carga 9. Contexto de la consulta (encontrada en la revisión)

| | |
|---|---|
| **Dónde** | `HistoriaClinicaService::obtenerContextoConsulta`, `:546-551`: `servidor` y `cargaFamiliar.servidor`. |
| **Endpoint** | `GET historias-clinicas/{id}/contexto-consulta` (`routes/api.php:2021`), con `ver-historia-clinica` y sin alcance clínico. |
| **Quién lo recibe** | médico, odontólogo, enfermera y admin-dispensario, para cualquier historia, por su id. |
| **Qué lee el frontend** | `PanelContextoPaciente.tsx:81-106` usa el triaje, las alergias, los antecedentes y `condicion_declarada`. **Del paciente no usa nada.** El tipo (`contextoConsultaService.ts:41-50`) declara `servidor {id, nombre, apellido}` y `carga_familiar {id, nombres, apellidos}`, pero ningún componente los lee. |
| **Uso clínico** | La discapacidad y la enfermedad catastrófica ya llegan con detalle en `condicion_declarada`. |
| **Uso posterior en el backend** | Ninguno sobre lo cargado: `CondicionDeclaradaService::de` (`:33-35`) busca al servidor o al familiar por su id. |
| **Propuesta** | `servidor:id,nombre,apellido` y `cargaFamiliar:id,nombres,apellidos`, que es lo que declara el tipo. |

### Carga 10. La respuesta al registrar y al corregir una consulta (encontrada en la revisión)

| | |
|---|---|
| **Dónde** | `HistoriaClinicaService::registrarConsulta`, `:334-342`, y `actualizarConsulta`, `:496-504`: `historiaClinica.servidor`, `historiaClinica.cargaFamiliar` y `medico`. |
| **Endpoint** | `POST consultas` y `PATCH consultas/{id}`, con `crear-consulta`. Solo el autor corrige. |
| **Quién lo recibe** | El médico o el odontólogo que escribe la consulta. |
| **Qué lee el frontend** | `TabConsulta` pasa la respuesta del alta a `AtencionPanel`, que la muestra mientras llega el detalle (`AtencionPanel.tsx:73-77`). Usa los campos de la consulta y sus diagnósticos, no `historia_clinica`. |
| **Uso posterior en el backend** | Ninguno. |
| **Propuesta** | Lo mismo que la carga 4. Conviene una sola constante para las cuatro cargas (4, 5 y las dos de esta), que hoy repiten la lista a mano. |

### Carga 11. El médico de la consulta (encontrada en la revisión)

| | |
|---|---|
| **Dónde** | `'medico'` sin columnas en `ConsultaMedicaController.php:73` y `:259`, y en `HistoriaClinicaService.php:337` y `:499`. |
| **El problema** | Es el de #595, pero con el usuario que atiende en vez del paciente. `User` lleva `nombre_completo` en `#[Appends]`. El accesor lee `$this->servidor`; si no está cargado, lo carga entero, y la serialización lo incluye. Así, cada fila del historial lleva a los colegas la ficha del médico que atendió: su cédula, su celular, su tipo de sangre y su contacto de emergencia. |
| **Qué lee el frontend** | Solo `medico.nombre_completo` (`ConsultaItem.tsx:69` y `DetalleConsultaDrawer.tsx:76`). |
| **Propuesta** | `medico:id,usuario_ti,email,servidor_id` y `medico.servidor:id,nombre,apellido`, como en `versiones` del mismo controlador (`:216-217`) y en `AtencionEnfermeriaService::RELACIONES`. El encargo era el paciente, pero es el mismo patrón en las mismas líneas, así que se propone en el mismo PR (D4). |

## 4. Decisiones para el usuario

La opción recomendada va primero.

| | Decisión | Opciones |
|---|---|---|
| **D1** | Las tres rutas que ya no usa ninguna pantalla (cargas 1 a 3) | **a) Retirarlas**, con sus métodos, y cambiar las pruebas que las usan como muestra · b) Dejarlas, recortadas |
| **D2** | Si se dejan (D1 b): las consultas en el detalle de la historia | **a) Quitarlas** · b) Dejarlas y recortar solo al paciente |
| **D3** | El paciente en las consultas (cargas 4, 5 y 10) | **a) Dejar solo el nombre**, como en el certificado médico · b) Quitarlo del todo, porque nadie lo lee |
| **D4** | El médico sin columnas (carga 11) | **a) Arreglarlo en el mismo PR** · b) En un PR aparte |
| **D5** | La enfermedad catastrófica en la FEMO | **a) No mandarla por ahora**, porque la FEMO no la prellena. Si se quiere prellenar, va aparte y con su relación, como la discapacidad · b) Dejar `tiene_enfermedad_catastrofica` en la respuesta para usarla más adelante |
| **D6** | El postulante en la solicitud (carga 7) | **a) Recortarlo también** · b) Recortar solo al servidor, que era lo que pedía el encargo |
| **D7** | Cómo se entrega | **a) Un solo PR**, con una prueba por endpoint · b) Dos PR: uno retira las rutas y otro recorta las cargas |

## 5. Lo que salió en la revisión

### 5.1 La historia clínica no tiene alcance clínico

Varias rutas de la historia piden solo `ver-historia-clinica`: el listado, el detalle, la búsqueda por
cédula y `contexto-consulta`. Así, cualquier médico, odontólogo, enfermera o miembro de la administración lee
cualquier historia sabiendo su id. Las consultas tienen alcance desde el PR 5 (`AlcanceClinico::atiendeHistoria`).

Con D1 a) queda solo `contexto-consulta`, que da alergias, antecedentes, triaje y condición declarada.
**¿Deben leerlo la enfermera y la administración?** Es una pregunta del plan de roles y no entra en este
recorte.

### 5.2 Atenciones de enfermería sin alcance

El listado no filtra por quién lo pide: médicos y odontólogos ven todas las atenciones de enfermería de todos
los pacientes. La pantalla solo filtra por fecha (`AtencionesEnfermeriaTable.tsx:43`). Como en 5.1, es una
pregunta del plan de roles.

### 5.3 Revisadas, sin fuga

- **`PacienteService`** (`pacientes/buscar` y `buscar-por-nombre`): carga `cargaFamiliar.servidor` entero,
  pero arma la respuesta campo a campo.
- **`OrdenExamenController::historiaDeLaFicha`** (`:128`): carga la ficha con `servidor` y `postulante`
  enteros, pero no los devuelve; solo lee la cédula.
- **`PdfRecetaService` y `PdfOrdenExamenService`:** generan PDF, no JSON.
- **`RevisionResultadoController::revisar`** (`:50`): carga `revisor.servidor` entero, pero la respuesta
  sale de `load(ResultadoMedicoController::RELACIONES)`. Esa lista vuelve a cargar `revisor` con columnas y
  reemplaza la relación.
- **`AgendaService` y `TriajeController::pendientes`:** las arregla #610, que sigue abierto.

### 5.4 La constante de prueba de #610

`AgendaSinFichaCompletaTest.php`, de #610, declara `const DATOS_DE_LA_FICHA` en el espacio global. Pest
carga todos los archivos en el mismo proceso, así que una prueba nueva no debe volver a declararla: o la usa
tal cual, o la constante pasa a `tests/Support`.

## 6. Cómo se aplicó

Un solo PR, con las siete opciones a. Puede ir antes o después de #610: no comparten archivos.

**Backend**

1. **Retirar las tres rutas:**
   - `GET historias-clinicas`, `GET historias-clinicas/{id}` y `GET historias-clinicas/buscar-por-cedula`;
   - en `HistoriaClinicaController`, los métodos `index`, `show` y `buscarPorCedula`;
   - en `HistoriaClinicaService`, `listar`, `obtener` y `buscarPorCedula`, y en
     `HistoriaClinicaServiceInterface`, `listar` y `obtener`. Nadie más los llama.
2. **Una constante para las consultas**, `HistoriaClinicaService::PACIENTE_Y_MEDICO_DE_LA_CONSULTA`
   (cargas 4, 5 y 10, con el médico de la 11). La usan `index`, `show`, `registrarConsulta` y
   `actualizarConsulta`, que antes repetían la lista a mano:
   - `historiaClinica.servidor:id,nombre,apellido`
   - `historiaClinica.cargaFamiliar:id,nombres,apellidos`
   - `medico:id,usuario_ti,email,servidor_id`
   - `medico.servidor:id,nombre,apellido`
3. **`AtencionEnfermeriaService::RELACIONES`** (carga 6).
4. **`SolicitudCertificacionController::show`** (carga 7).
5. **`obtenerContextoConsulta`** (carga 9).
6. **`generarPdf`** (carga 8).

**Pruebas**

- **`FichaDelPacienteRecortadaTest`**, una prueba por endpoint: el paciente llega con su nombre (en la FEMO,
  también con lo clínico) y sin nada más de su ficha, y el médico, sin la suya. Comprueba además que las
  tres rutas ya no responden y que el PDF de un familiar sigue nombrando a su titular. Sin el arreglo
  fallan siete de las ocho; la del PDF pasa igual, porque solo vigila que el recorte no le quite el
  titular. No declara constantes globales: usa funciones con nombre propio.
- **Ajustes por las rutas retiradas:**
  - `AgendaTriajePorPermisosTest`: la fila `historias` y la prueba del permiso suelto pasan a
    `historias-clinicas/{id}/contexto-consulta`, que tiene el mismo permiso.
  - `AuxiliarFarmaciaTest:182`: lo mismo.
  - `PerPageConTopeTest`: se quita la fila de historias. `atenciones-enfermeria` no la reemplaza, porque
    responde 422 en vez de acotar.
  - `HistoriaUnicaPorPacienteTest:84`: la búsqueda por la cédula nueva pasa a `crear-por-cedula`, que es
    como la busca la ficha ocupacional.
- **Se corrieron en local** con `php -d memory_limit=3G vendor/bin/pest tests/Feature/Dispensario`.

**Frontend**

- `types:sync`, porque se retiran rutas. No cambia ningún componente.
- Después, `tsc` y lint.

**Producción**

- No hay migraciones.
