# Plan de roles y accesos por menú

**Versión 1 — 2026-10-25.** Sale de la auditoría del menú contra el backend (los 5 subsistemas) y de
las 8 decisiones que tomó el usuario ese mismo día. Todavía no hay código.

## 1. Las 8 decisiones

1. **admin-ti, solo técnico.** Se quita el atajo general del `Gate::before`. Conserva, con permisos
   explícitos: Usuarios, TIC, Comunicación, Noticias y comunicados, configuración y la vinculación
   inicial. Deja de ver datos de Talento Humano y de salud. Si una persona de TI necesita TH, se le
   asigna además el rol de TH.
2. **`ver-novedades-nomina`, solo para Talento Humano.** Lo conservan admin-uath (ve y entrega),
   asistente-uath y auditor (lectura). Financiero lo pierde: las Novedades le siguen llegando por
   correo, con el Excel, al entregarlas.
3. **Se retira `analista-uath`.**
4. **Se crean `tecnico-sso` y `auxiliar-farmacia`.**
5. **Dispensario acotado.**
   - admin-dispensario: solo lectura en Salud ocupacional. La FEMO es del médico que la inició.
   - Recetas y notas: médico y odontólogo ven solo sus recetas, y las notas de los pacientes que
     atienden. Enfermería, admin-dispensario y auxiliar de farmacia ven todas las recetas para
     despachar, pero no las notas de consulta.
6. **Se retira `maxima-autoridad`.** Su lectura la conserva el auditor.
7. **Usuarios se muda a Tecnologías › Administración.**
8. **Directorio telefónico:** la consulta pasa al Portal y la gestión a Tecnologías. Sale del SGTH.

## 2. Principios

1. **Una sola fuente de verdad.** Cada entrada del menú declara el mismo permiso que pide el backend
   al cargar la pantalla.
2. **El subsistema aparece si el usuario tiene al menos una entrada visible.** Desaparecen las listas
   `ROLES_SGTH`, `ROLES_SALUD`, `ROLES_TIC`, `ROLES_COMUNICACION`, `ROLES_PORTAL` y el atajo
   `soloPermisosEnSgth`.
3. **Cada botón de escritura lleva el permiso de escritura que pide el backend.**
4. **El alcance de los datos** sigue la convención que ya existe: `-propio`, `-unidad` y `-todos`.
5. **Buscador de servidores liviano**, con su propio permiso y separado del Expediente.
6. **El Dispensario, por permisos.** Queda como fase opcional (PR 17): son 73 guardas por rol y 73
   archivos de pruebas, y las decisiones 4b y 5 se cumplen sin esta conversión. Mientras tanto, el
   menú del Dispensario sigue declarando `roles`, iguales a los del backend.
7. **Usuarios de prueba con un solo rol cada uno.** El usuario `crecalde`, con 7 roles, escondió el
   fallo del buscador de servidores.

## 3. Cómo se trabaja

- **Un PR a la vez, cada uno desde `main`** cuando se ha fusionado el anterior. Casi todos tocan
  `RolPermisoSeeder.php`, `nav.ts` y `routes/api.php`, así que no conviene apilarlos.
- **Cada cambio de permisos lleva una migración** con lo mismo que el seeder, porque el seeder no se
  vuelve a correr en producción. `ViaticoAccesoTest` compara la migración del rol financiero con el
  seeder.
- **Suite completa en el CI** para los PR transversales (3, 7 y 9). En los demás basta con correr en
  local lo tocado y los módulos afectados.
- **Producción:** antes de los PR 3 y 7, hay que mirar quién tiene en producción admin-ti (y hace
  trabajo de TH a través del atajo), maxima-autoridad o analista-uath.

## 4. Los PRs

Prioridad: **alta** = rompe trabajo o expone datos; **media** = rol sin camino, o un 403 a la vista;
**baja** = orden y prolijidad.

### Fase 0 — Base de verificación

#### PR 1. Usuarios de prueba con un solo rol (recomendado)
- **Qué:** un seeder solo para el entorno local que crea un usuario por rol, vinculado a un servidor
  de prueba.
- **Por qué:** el fallo del buscador de servidores no se vio porque se probaba con `crecalde`.
- **Cuidado:** la contraseña sale de una variable de `.env`, nunca escrita en el código (AdminTiSeeder
  ya tiene ese problema). El seeder se niega a correr fuera de `local`.
- **Migración:** no.

### Fase 1 — Prioridad alta

#### PR 2. Buscador de servidores con permiso propio
- **Problema:** `BuscarServidorSelect` pide `/expediente/servidores` y `/expediente/servidores/{id}`.
  `ServidorPolicy::verAny` y `ver` solo admiten admin-uath y asistente-uath, así que el personal de TIC
  recibe 403 en cinco formularios:
  - registrar ticket;
  - entregar o traspasar la custodia de un equipo;
  - préstamo directo;
  - solicitud a bodega sin ticket;
  - administrador de contrato.

  Además, el auditor queda sin poder filtrar por servidor en Accidentes, EPP, Consolidado y Períodos.
- **Backend:**
  - `GET /expediente/servidores/buscar?search=` y `GET /expediente/servidores/buscar/{id}`, con
    `permission:buscar-servidores`.
  - Devuelven solo id, nombre completo, cédula y unidad: nada de contacto ni del expediente.
- **Permiso nuevo `buscar-servidores`:** admin-uath, asistente-uath, admin-ti, jefe-tic, tecnico-dtic y
  auditor (tecnico-sso lo recibe en el PR 12).
- **Frontend:** `BuscarServidorSelect` pasa a los endpoints nuevos. Sus 20 usos no cambian.
- **Migración:** sí (crear el permiso y otorgarlo).
- **Pruebas:**
  - matriz de roles del endpoint nuevo;
  - el técnico de TIC puede buscar;
  - un servidor sin el permiso recibe 403.

#### PR 3. admin-ti, solo técnico
- **Backend:**
  - Se quita el `Gate::before` de `AppServiceProvider.php:356`. Se mantienen `viewPulse`,
    `viewApiDocs` y `viewTelescope`.
  - Se saca admin-ti de las rutas de TH: subrogaciones (`api.php:758`, `:771`), partidas
    presupuestarias (`:365`) y catálogo de entidades financieras (`:301`).
  - El directorio telefónico (`:378`) se resuelve en el PR 11.
  - `POST contratos` (`:695`), la carga histórica, no cambia: va con la vinculación inicial, que admin-ti
    conserva.
  - Con el atajo fuera, `registrarOnline` (`MarcacionController.php:141`) ya exige `marcar-en-linea`.
- **Frontend:**
  - Se saca admin-ti de Subrogaciones en `nav.ts`, de `SubrogacionesView.tsx:34` y de
    `MarcacionesTab.tsx:43`.
  - Se actualizan los comentarios de `useAccionesPermiso.ts` y `useAccionesViatico.ts`.
- **Migración:** no; el rol ya tiene sus permisos de forma explícita.
- **Pruebas:**
  - 21 archivos usan admin-ti. Las que esperaban que pasara por endpoints de TH ahora esperan 403.
  - Se añaden pruebas: admin-ti sin `marcar-en-linea` no marca; no ve los permisos de enfermedad; no
    aprueba licencias.
  - Suite completa en el CI.
- **Riesgo:** quien hoy hace trabajo de TH con admin-ti lo pierde. Hay que revisar en producción y
  asignarle el rol de TH antes del despliegue.

#### PR 4. Dispensario: el acto médico es del médico que evalúa
- **Backend:**
  - `solicitudes-certificacion/{id}/iniciar` y `/completar`, y alta y edición de `fichas-sso`, pasan a
    `role:medico`. La lectura sigue para medico y admin-dispensario.
  - `FemoService::actualizar` y la emisión del dictamen exigen que el usuario sea el evaluador de la
    ficha.
  - `CertificadoMedicoService::emitir` exige que la consulta sea de quien emite el certificado de
    reposo.
  - El odontograma: registrar procedimientos queda solo para el odontólogo.
- **Frontend:** «Iniciar», «Continuar», «Guardar borrador», «Emitir dictamen» y «Continuar en el
  asistente» se muestran solo al médico evaluador.
- **Migración:** no.
- **Datos:** si en producción hay fichas en curso iniciadas por admin-dispensario, se listan antes de
  desplegar para reasignarlas.

#### PR 5. Dispensario: datos clínicos acotados
- **Recetas** (`RecetaController` `index`, `show` y `pdf`):
  - médico y odontólogo ven solo las suyas;
  - enfermería y admin-dispensario ven todas.
  - **Hallazgo nuevo:** el listado manda en el JSON la consulta completa (motivo, enfermedad actual,
    examen físico, diagnóstico detallado, plan y notas del médico). Se recorta a lo que necesita el
    despacho: paciente, médico, fecha y los ítems. El código CIE-10 se conserva solo si el impreso
    oficial de la receta lo lleva (se comprueba en el PR).
- **Consultas** (`ConsultaMedicaController` `index` y `show`):
  - médico y odontólogo ven sus consultas y las de los pacientes con los que tienen un turno;
  - enfermería y admin-dispensario no leen notas de consulta.
  - Antes de cerrarlo, se comprueba qué pantallas de Enfermería llaman a esos endpoints.
- **Bandeja de exámenes:** cada profesional ve solo su especialidad; admin-dispensario ve todo.
- **Migración:** no.
- **Pruebas:** la matriz de roles por endpoint, más una prueba de que el JSON de recetas no trae las
  notas.

#### PR 6. Dispensario: agenda y colas con dueño
- **Backend:**
  - `AgendaService`: enfermería y admin-dispensario ven y operan todos los turnos; médico y
    odontólogo, solo los suyos (`index`, `show`, `cancelar`, `noPresentado`, `reactivar`,
    `marcarEnConsulta`).
  - `turnosDelDia` filtra por tipo de atención: Consultas muestra medicina y Odontología muestra
    odontología.
- **Frontend:**
  - Consultas pasa a `roles: ['medico']` y Odontología a `roles: ['odontologo']`. Enfermería y
    admin-dispensario dejan de ver colas vacías.
  - «Cancelar turno» de la Cola y «Servicio» de `PacienteCard` llevan su guarda.
- **Migración:** no.

### Fase 2 — Prioridad media

#### PR 7. Retirar `maxima-autoridad` y `analista-uath`
- **Migración:** si algún usuario tiene uno de estos roles, se detiene y lista quiénes son. Si no, borra
  los roles.
- **Backend:**
  - el enum `Rol` y `RolPermisoSeeder`;
  - las rutas `api.php:758`, `:1821`, `:1823`, `:1834`, `:2355`, `:2463`, `:2479` y `:2482`, y las
    listas de certificaciones que incluyen analista-uath;
  - `SubrogacionPolicy`;
  - el perfil `AUTORIDAD` de `AlcanceReporte`.
- **Frontend:**
  - en `nav.ts`: Subrogaciones, Dashboard y Reportes del Dispensario, `ROLES_SGTH` y `ROLES_PORTAL`;
  - `SaludHomeView.tsx`, `TableroDispensario.tsx` y `usuarios/constants/roles.ts`;
  - regenerar los tipos de la API.
- **Pruebas:** 11 referencias a maxima-autoridad en 9 archivos, más las de analista-uath. Suite
  completa en el CI.
- **No toca:** las firmas (el Prefecto firma como `autoridad_nominadora`, por el puesto) ni el relevo
  de autoridades.

#### PR 8. Novedades del mes, solo para Talento Humano
- **Migración:** se le quita `ver-novedades-nomina` al rol financiero. Se actualizan el seeder y
  `ViaticoAccesoTest`.
- **Frontend:** «Novedades para Financiero» pasa a llamarse «Novedades del mes». «Entregar a
  Financiero» se queda: la entrega sigue enviando el Excel al jefe de Gestión Financiera.

#### PR 9. Menú por permisos, subsistemas por entradas visibles y guarda de página
Es el PR que más cambia el frontend. No toca el backend.

- **Entrada a cada subsistema:** aparece si `buildNav` deja al menos una entrada. Cada Inicio declara
  el permiso de su endpoint:
  - Inicio de TIC: los 5 permisos de `/tic/tablero`;
  - Inicio de Comunicación: los 3 de `/comunicacion/tablero`.

  El Dashboard del SGTH se oculta mientras esté en construcción, y `/sgth` lleva a la primera entrada
  visible (esto sustituye a `soloPermisosEnSgth`).
- **Toda entrada declara su permiso:**

  | Entrada | Permiso que se le declara |
  |---|---|
  | Organigrama, Puestos, Plantilla, Partidas | `ver-estructura` |
  | Marcaciones | `ver-asistencia-unidad` o `ver-asistencia-todos` |
  | Permisos | `ver-permisos-todos`, `ver-asistencia-unidad`, `confirmar-recepcion` o `validar-trabajo-social` (no el permiso base, que tienen todos) |
  | Consolidado | `ver-permisos-todos` |
  | Períodos | `gestionar-vacaciones` o `ver-asistencia-todos` |
  | Riesgos laborales | `gestionar-sso` o `ver-reportes-sso` |
  | Portal | `acceso-autoservicio` |

  Con esto Trabajo Social ve Permisos y Licencias, y Recepción ve Permisos.
- **Guarda de página:**
  - Se busca la entrada más específica del menú completo que contiene la ruta. Si esa entrada no es
    visible para el usuario, la página muestra «No tiene acceso a esta pantalla».
  - Se revisan una a una las 120 páginas, para que cada detalle (folio, `[id]`, `nuevo`) cuelgue de
    su entrada.
- **Se limpia:** los comentarios desactualizados de `nav.ts` («se suman en sus fases») y de
  `routes.ts` (la página de edición de convocatorias sí existe).
- **Pruebas:** pruebas de unidad de `buildNav`, de la entrada a subsistemas y de la guarda, con un
  conjunto de permisos por rol.

#### PR 10. Usuarios a Tecnologías
- La pantalla pasa de `/sgth/usuarios` a `/tic/usuarios` (`ROUTES.TIC.USUARIOS`) y entra en TIC ›
  Administración con `gestionar-usuarios`. `/sgth/usuarios` redirige con `permanentRedirect`.
- El grupo «Administración» del SGTH queda vacío y desaparece del menú.
- El backend no cambia.

#### PR 11. Directorio: consulta en el Portal, gestión en TIC
- **Permiso nuevo `gestionar-directorio`:** tecnico-dtic, jefe-tic y admin-ti. Reemplaza a
  `role:admin-ti|admin-uath` en `extensiones` (`api.php:378`), así que admin-uath deja de editarlo.
- **Frontend:**
  - Portal › «Directorio telefónico» (`/portal/directorio`), en solo lectura para todos.
  - TIC › Administración › «Directorio telefónico», con Nuevo, Editar y Eliminar guardados por el
    permiso.
  - Sale del SGTH, y `/sgth/estructura/directorio` redirige al Portal.
- **Migración:** sí.

#### PR 12. Rol `tecnico-sso`
- **Rol nuevo** con los permisos base y `gestionar-sso`, `registrar-accidente`, `ver-reportes-sso` y
  `buscar-servidores`.
- **Backend:**
  - El prefijo `sso` (`api.php:1683`) pasa de `role:admin-uath|asistente-uath|auditor` a
    `permission:ver-reportes-sso|gestionar-sso`. Las policies de SSO ya van por permiso.
  - Ausentismo tiene su propio endpoint, `GET /sso/ausentismo`, con la misma guarda. Hoy depende de
    `ver-permisos-todos`, que el técnico no tendrá.
- **Frontend:**
  - `AusentismoView` usa el endpoint nuevo y corrige sus dos defectos: la consulta que se relanza
    sola y la exportación de un período distinto al de la pantalla.
  - En `nav.ts`, Riesgos laborales pierde los `roles`.
- **Migración:** sí.

#### PR 13. Rol `auxiliar-farmacia`
Depende del PR 5: sin él, el auxiliar recibiría las notas clínicas en el JSON de las recetas.

- **Rol nuevo** con los permisos base y `despachar-medicamento`.
- **Backend:** el rol se añade a las rutas de farmacia:
  - lectura del inventario, kárdex, stock bajo y baja de existencias;
  - despacho: recetas, despachar, no entregado;
  - la lista de personal médico.

  No entra a historias clínicas, consultas, agenda ni búsqueda de pacientes.
- **Frontend:** Farmacia › Inventario y Despacho con el rol, y `ROLES_INVENTARIO_MED` para el
  contador de stock.
- **Migración:** sí.

### Fase 3 — Prioridad baja

#### PR 14. Botones y páginas sin guarda
- **Partidas presupuestarias:** escribir pasa a `permission:gestionar-distributivo`, que solo tendrá
  admin-uath tras el PR 7. Los botones llevan la misma guarda.
- **«Registrar ficha» en el Expediente:** permiso nuevo `registrar-ficha-servidor` (admin-uath), que
  usan `ServidorPolicy::crear` y el botón.
- **Comunicación:**
  - el bloque Fotografías del Inicio exige `gestionar-fotografias`
    (`TableroComunicacionService.php:40`);
  - los botones de `FotografiasView` llevan su guarda.
- **TIC:**
  - Nuevo y Editar equipo, y Entregar consumibles, comprueban `gestionar-inventario-ti`;
  - el selector de nota interna, solo con `gestionar-tickets`.
- **SGTH:**
  - `comunicados/nuevo` exige `redactar-comunicados`;
  - `convocatorias/nueva` exige `gestionar-convocatorias`;
  - Plantillas: botones con guarda, y se quita el `editable: true` fijo.
- **Viáticos:** la Bandeja indica «solo consulta» a quien no opera.
- **Adquisiciones:** el historial muestra los errores.

#### PR 15. Portal para cuentas sin ficha de servidor
- Se crea un aviso común: «Su usuario no está vinculado a un servidor».
- **Mis documentos:** muestra el aviso en vez de un error 422.
- **Solicitudes laborales, Actividades, Tickets y Préstamo:** se oculta «crear», que hoy falla después
  de llenar el formulario.
- **Vacaciones:** no se dispara la consulta que hoy da 404.
- **Viáticos, Equipos y Préstamos:** el vacío explica la causa.
- **Backend:** el préstamo sin servidor responde 422 en vez de 404.

#### PR 16. Limpieza de permisos
- **Se retira `puede-marcar-online`:** nadie lo usa y se confunde con `marcar-en-linea`. Va con una
  migración.
- **Permisos base que no usa ningún código:** `ver-expediente-propio` y `ver-asistencia-propia` pasan a
  guardar sus entradas del Portal.
- **Se documentan sin cambiar:** los permisos de los módulos futuros (capacitación, evaluación, clima,
  gestión documental) y los decorativos del Dispensario, que esperan al PR 17.

### Fase 4 — Opcional

#### PR 17. Dispensario por permisos
- Las 73 guardas `role:` pasan a `permission:`, con los permisos que ya existen (`crear-consulta`,
  `emitir-receta`, `despachar-medicamento`, `gestionar-fichas-sso-med`, `gestionar-inventario-med`…) y
  algunos nuevos (`registrar-triaje`, `ver-inventario-med`).
- El menú del Dispensario deja los `roles`.
- Hay que reescribir las pruebas de 73 archivos. Conviene partirlo en varios PR por módulo.

**Partición aprobada el 2026-10-28.** El inventario dio 129 endpoints, 77 `role:`, 14 combinaciones de
roles, 73 pruebas con el rol pelado y unas 25 comprobaciones en el frontend. Cada parte conserva
exactamente quién puede qué:

| Parte | Área | Permisos que ya existían | Permisos nuevos |
|---|---|---|---|
| 17a | Farmacia: inventario, recetas, despacho, adquisiciones | `emitir-receta`, `despachar-medicamento`, `gestionar-inventario-med` (a admin-dispensario) | `ver-inventario-med`, `ver-recetas`, `supervisar-dispensario` |
| 17b | Agenda, triaje, enfermería, pacientes, historias, disponibilidad | `ver-agenda-dispensario`, `ver-historia-clinica`, `crear-consulta`, `gestionar-agenda` | `registrar-triaje` |
| 17c | Consultas, exámenes, odontograma, certificados de reposo, CIE-10 | `crear-consulta` | `ver-examenes`, `gestionar-catalogo-examenes`, `registrar-odontograma` |
| 17d | Salud ocupacional, reportes y tableros | `gestionar-fichas-sso-med`, `solicitar-certificacion-medica`, `gestionar-onboarding` | `ver-fichas-sso-med`, `ver-certificaciones-medicas`, `ver-tablero-dispensario` |

- `supervisar-dispensario` (solo admin-dispensario) es «anula o ve lo que registró otro profesional».
- Lo que decide identidad sigue por rol: la especialidad, la cola, el perfil de «Mi jornada» y quién
  atiende para recibir turnos.
- Las pruebas no se reescriben: `rolesDelDispensario()` (tests/Support/RolesFixtures.php) crea los cinco
  roles con los permisos del seeder antes de cada prueba de Feature/Dispensario.

## 5. Orden y dependencias

| PR | Depende de | Suite completa |
|---|---|---|
| 1 | — | No |
| 2 | — | No |
| 3 | 2 | **Sí** |
| 4 | — | No |
| 5 | — | No |
| 6 | 5 (mismas pantallas) | No |
| 7 | — | **Sí** |
| 8 | — | No |
| 9 | 7 | **Sí** |
| 10 | 9 | No |
| 11 | 9 | No |
| 12 | 2 y 9 | No |
| 13 | 5 y 9 | No |
| 14 | 3 y 7 | No |
| 15 | — | No |
| 16 | 9 | No |
| 17 | 13 | Sí, por partes |

## 6. Fuera de este plan

- **Marcaciones de la unidad para director y jefe:** tienen `ver-asistencia-unidad`, pero la función
  no existe. Es una pantalla nueva.
- **Dashboard del SGTH:** está en construcción. Su contenido natural es `/reporteria/dashboard`, que ya
  existe en el backend.
- **Módulos con backend y sin pantalla:** Capacitación, Bienestar y clima, Reportería, Gestión
  documental, la base de conocimiento (con un controlador que devuelve vacío) y las tarifas de
  viáticos.
- **Diseños sin construir:** Evaluación del desempeño y Evaluación de atención al usuario.

## 7. Migraciones para producción

Los PR 2, 7, 8, 11, 12, 13, 14 y 16 llevan migración. La del PR 7 se detiene si encuentra usuarios
con los roles retirados.
