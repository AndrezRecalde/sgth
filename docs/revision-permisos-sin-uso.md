# Revisión de los permisos sin uso

**Versión 2, del 2026-10-28.** Retoma lo que el PR 16 de `docs/plan-roles-y-accesos.md` dejó «para una
revisión aparte»: 15 permisos que nadie comprueba, más los dos del Dispensario que quedaron sin uso
después del PR 17.

**Decisiones:** el usuario aprobó las doce recomendaciones (D1–D12, todas la opción a) el mismo
2026-10-28. Se aplican en los cuatro PR de la sección 6. `configurar-sistema` (sección 5.1) no entró en
las decisiones: se documenta en el enum y su retiro queda por decidir.

## 1. Resumen

| # | Permiso | Quién lo tiene (seeder) | Quién decide hoy, y cómo | Propuesta | ¿Cambia el acceso? |
|---|---|---|---|---|---|
| 1 | `ver-distributivo` | admin-uath | `ver-estructura` en Puestos y Plantilla | **Retirar** | No |
| 2 | `ver-expediente-todos` | admin-uath, asistente-uath, auditor | `hasRole` en `ServidorPolicy::verAny` y `ver` | **Darle uso**, y quitárselo al auditor | No |
| 3 | `ver-expediente-unidad` | admin-uath, asistente-uath, director, jefe-unidad, auditor | Nadie: el director y el jefe no tienen pantalla | **Retirar** | No |
| 4 | `gestionar-expediente` | admin-uath, asistente-uath | `role:admin-uath\|asistente-uath` en los historiales y en `ServidorPolicy::actualizar` | **Darle uso** | No |
| 5 | `cargar-documentos` | admin-uath | Subir: `role:admin-uath\|asistente-uath`. Borrar: `role:admin-uath` | **Darle uso** para subir, y dárselo al asistente | No |
| 6 | `gestionar-cargas-familiares` | admin-uath, asistente-uath | `role:admin-uath\|asistente-uath` en las cargas y en «Cargas por validar» | **Darle uso** | No |
| 7 | `gestionar-tarifas-viatico` | financiero | Nadie: las tarifas solo las carga un seeder | **Dejar para una pantalla futura** | No |
| 8 | `gestionar-base-conocimiento` | tecnico-dtic, jefe-tic, admin-ti | Nadie: el controlador devuelve una lista vacía | **Dejar para el módulo futuro** (fase 5.5 de TIC) | No |
| 9 | `ver-sumarios` | admin-uath | `role:admin-uath` en todo el prefijo `disciplinario` | **Darle uso** | No |
| 10 | `gestionar-sumarios` | admin-uath | Lo mismo | **Darle uso** | No |
| 11 | `registrar-sancion` | admin-uath | Lo mismo | **Darle uso** | No |
| 12 | `ver-actividades-unidad` | admin-uath, asistente-uath, director, jefe-unidad, auditor | Nadie: las actividades las ve solo su autor (decisión del 2026-10-12) | **Retirar** | No |
| 13 | `aprobar-actividades` | director, jefe-unidad | Nadie: no hay aprobación, y la columna `estado` se borró | **Retirar** | No |
| 14 | `exportar-reportes` | admin-uath | `role:admin-uath\|auditor\|director` en `reporteria`, que no tiene pantalla | **Dejar para el módulo futuro** (Reportería) | No |
| 15 | `ver-auditoria` | admin-ti, admin-uath, auditor | Nadie: no existe una pantalla de la bitácora | **Dejar para una pantalla futura** | No |
| 16 | `evaluar-personal-medico` | admin-dispensario | Nadie: no hay diseño ni pantalla | **Retirar** | No |
| 17 | `ver-historia-clinica-propia` | todos (base) | Nadie: `autoservicio/mi-historia-clinica` existe, pero no lo pide ni lo consume ninguna pantalla | **Darle uso** en esa ruta | No |

Ninguna propuesta recomendada cambia lo que hoy puede hacer cada rol. Las que sí lo cambiarían quedan
como alternativas en la sección 4.

## 2. Cómo se revisó

- **Grep** de cada permiso en `sgth-backend/app`, `routes`, `config` y `database`, y en
  `sgth-frontend/src`. Se buscó el valor (`'ver-sumarios'`, `"ver-sumarios"`, `permission:…|ver-sumarios`) y la
  constante (`Permiso::VER_SUMARIOS`). Para descartar comprobaciones armadas en tiempo de ejecución, se
  buscaron `can("…{$x}")`, `'permission:' .` y `` hasPermiso(`…`) ``, y no hay ninguna.
- **Quedan fuera** el enum, el seeder, las migraciones que replican el seeder,
  `sgth-frontend/src/test/permisos-por-rol.json` (el fixture de las pruebas del menú) y los comentarios.
  `ver-expediente-todos` solo aparece en un comentario de `ServidorPolicy.php:25`.
- **Roles:** los del `RolPermisoSeeder`. El seeder no se vuelve a correr en producción: allá los roles
  tienen lo que les dieron las migraciones, más lo que TI haya dado a mano desde Usuarios. **Antes de
  retirar un permiso conviene mirar en producción quién lo tiene como permiso directo**
  (`model_has_permissions`).
- **Barrido completo.** Se repitió el grep con los 129 permisos del enum. Salieron tres más sin uso
  que no estaban en ninguna de las listas del PR 16 (sección 5.1).

## 3. Permiso por permiso

### 3.1 Estructura

#### `ver-distributivo`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. Solo lo nombran el enum, el seeder y `permisos-por-rol.json`. |
| **Roles** | admin-uath |
| **Qué decide hoy** | El distributivo, es decir quién ocupa cada puesto y con qué RMU, ya se ve en **Puestos y cargos**: `PuestoResource` manda `rmu` y `ocupantes` con nombre y cédula (`PuestoResource.php:29` y `:79`), y `PuestoPolicy::viewAny` pide `ver-estructura`. Ese permiso lo tienen admin-uath, asistente-uath, director, jefe-unidad y auditor. **Plantilla** muestra plazas y ocupación agregadas, sin nombres. El reporte «Distributivo de sueldos» de Reportería va por `role:admin-uath\|auditor\|director` y no tiene pantalla. |
| **Propuesta** | **Retirarlo.** El distributivo es información pública: la LOTAIP lo publica cada mes, y el SGTH ya lo genera para eso (`GenerarReportesLotaipCommand`). Otro permiso para lo mismo que muestra `ver-estructura` no protege nada. |
| **Alternativa** | Darle uso para que solo admin-uath vea RMU y ocupantes en Puestos. Eso **le quita esa vista** al asistente, al director, al jefe y al auditor, y obliga a partir `PuestoResource`. |
| **Qué tocaría** | Enum, seeder, una migración que lo borre, y `permisos-por-rol.json`. No lo nombra ninguna prueba. |

### 3.2 Expediente

Hoy todo el Expediente lo decide el **rol** `admin-uath|asistente-uath`, en las rutas y en
`ServidorPolicy`. Los cuatro permisos del Expediente tienen justo esos dos roles (`cargar-documentos`,
solo admin-uath), así que pueden reemplazar los `role:` sin que nadie gane ni pierda nada.

**Costo común:** 81 de los 88 archivos de `tests/Feature/Expediente` que nombran estos roles los crean
sin permisos (`Role::firstOrCreate(['name' => 'admin-uath'])`). Con la ruta por permiso, fallarían. La
salida es la del PR 17: una función como `rolesDelDispensario()` (`tests/Support/RolesFixtures.php`)
que cree admin-uath y asistente-uath con sus permisos de Expediente antes de cada prueba de
`Feature/Expediente` y `Feature/Disciplinario`.

#### `ver-expediente-todos`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. Solo aparece en un comentario de `ServidorPolicy.php:25`, que explica por qué **no** se usa. |
| **Roles** | admin-uath, asistente-uath, auditor |
| **Qué decide hoy** | `ServidorPolicy::verAny` (`:29`) y `ver` (`:51`) con `hasRole('admin-uath') \|\| hasRole('asistente-uath')`. De `verAny` dependen el listado (`ServidorController::index`) y las dos exportaciones (`servidores-export/excel` y `/pdf`, `api.php:451`). De `ver` dependen la ficha, los documentos y su descarga. En el menú, «Expediente digital» lleva `roles: ['admin-uath', 'asistente-uath']` (`nav.ts:88`). |
| **Propuesta** | **Darle uso y quitárselo al auditor.** `verAny`, `ver` (la rama de TH) y el menú pasan a `ver-expediente-todos`, y una migración se lo quita al rol auditor. Con eso queda en los mismos dos roles que hoy entran. |
| **Alternativa** | Darle uso y dejárselo al auditor: el auditor **entraría** al Expediente y vería la ficha, los documentos y las exportaciones. Las cuentas, las cargas y la salud siguen con su propio `role:`. El comentario de la policy descartó esto a propósito. |
| **Qué tocaría** | `ServidorPolicy`, `nav.ts`, una migración, el seeder, `permisos-por-rol.json` y `nav.test.ts`. `RolesRetiradosTest` y `TecnicoSsoTest` lo nombran. |

#### `ver-expediente-unidad`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. |
| **Roles** | admin-uath, asistente-uath, director, jefe-unidad, auditor |
| **Qué decide hoy** | Nada. El director y el jefe de unidad no tienen ninguna pantalla con los expedientes de su unidad: `ServidorPolicy` se los niega y el menú no se los ofrece. Para elegir al jefe inmediato, el Portal usa `autoservicio/companeros-de-unidad`, que no lo pide. Para TH y el auditor, sobra al lado de `-todos`. |
| **Propuesta** | **Retirarlo.** El Expediente trae cuentas bancarias, salud y cargas familiares, y una jefatura no necesita todo eso. Si algún día la jefatura tiene una pantalla «Mi equipo», debería llevar un permiso más estrecho, hecho para lo que esa pantalla muestre. |
| **Alternativa** | Dejarlo para esa pantalla futura de la jefatura, como `ver-asistencia-unidad` (sección 6 del plan de roles). |
| **Qué tocaría** | Enum, seeder, migración y `permisos-por-rol.json`. Lo nombra `TecnicoSsoTest`. La migración `2026_11_27` lo menciona, pero es historia y no se toca. |

#### `gestionar-expediente`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. |
| **Roles** | admin-uath, asistente-uath |
| **Qué decide hoy** | `role:admin-uath\|asistente-uath` en el grupo «Historiales gestionados por la UATH» (`api.php:706`): vínculos, plazo, discapacidades y enfermedades del servidor, historial académico y declaraciones juramentadas. También en las cuentas bancarias (`:776`), el certificado laboral y «Títulos por validar». Y la rama de TH de `ServidorPolicy::actualizar` (`:90`), con `hasRole`. |
| **Propuesta** | **Darle uso** en todos esos lugares, en vez del rol. Son los mismos dos roles. La ficha completa sigue siendo de `registrar-ficha-servidor`, por `UpdateServidorRequest`. |
| **Qué tocaría** | `routes/api.php`, `ServidorPolicy`, «Títulos por validar» en `nav.ts:96` y el fixture de pruebas. `AltaFichaYPermisosTest` y `TecnicoSsoTest` lo nombran. |

#### `cargar-documentos`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. |
| **Roles** | admin-uath (solo) |
| **Qué decide hoy** | Subir: `role:admin-uath\|asistente-uath` (`api.php:485`). El asistente entró el 2026-10-03 por decisión de TH, «por el momento, pendiente de reforzar». Borrar: `role:admin-uath` (`:489`), y en pantalla `hasRole('admin-uath')` (`DocumentosTab.tsx:27`). El botón «Subir documento» no tiene guarda: lo ve todo el que abre el Expediente. |
| **Propuesta** | **Darle uso para subir y dárselo a asistente-uath** con una migración. Hoy no cambia nada. Lo que se gana es que, si TH decide «reforzar» y quitarle la subida al asistente, TI lo hace desde Usuarios, sin código. Borrar sigue con `role:admin-uath`. El botón «Subir documento» pasaría a pedir el permiso. |
| **Alternativa** | Retirarlo y que subir vaya con `gestionar-expediente`. Es más simple, pero la subida del asistente deja de poder quitarse por separado. |
| **Qué tocaría** | `api.php:485`, `DocumentosTab.tsx`, seeder, migración y `permisos-por-rol.json`. No lo nombra ninguna prueba. |

#### `gestionar-cargas-familiares`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. |
| **Roles** | admin-uath, asistente-uath |
| **Qué decide hoy** | `role:admin-uath\|asistente-uath` en: las cinco rutas de cargas del grupo de historiales (`api.php:757`), las discapacidades y enfermedades de la carga (`:420`) y «Cargas por validar» (`:686`), con su entrada del menú (`nav.ts:105`). Lo que pide el servidor desde «Mi perfil» va por `acceso-autoservicio` y no cambia. |
| **Propuesta** | **Darle uso** en esas tres partes y en el menú. Son los mismos roles. Las rutas de cargas salen del grupo de `gestionar-expediente` y llevan su propio permiso. |
| **Qué tocaría** | `routes/api.php`, `nav.ts` y el fixture. No lo nombra ninguna prueba. |

### 3.3 Viáticos

#### `gestionar-tarifas-viatico`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. Ninguna ruta toca `tarifas_viatico`. |
| **Roles** | financiero |
| **Qué decide hoy** | Nadie. Las seis tarifas (servidor y autoridad, dentro y fuera de la provincia, y exterior) las carga `TarifaViaticoSeeder` con `insertOrIgnore`, que no pisa un valor ya ajustado. Si el MDT cambia una tarifa, hoy TIC tiene que editarla a mano en la base. El cálculo (`CalculoViaticoService::tarifaDiaria`) la lee de la tabla. |
| **Propuesta** | **Dejarlo para una pantalla futura** y preguntarle a Financiero si la necesita. Sería pequeña: la tabla de seis tarifas en la Bandeja de viáticos, con `permission:gestionar-tarifas-viatico`. |
| **Alternativa** | Construirla ya. |
| **Qué tocaría** | Nada, por ahora. `ViaticoAccesoTest` compara la migración del rol financiero con el seeder. |

### 3.4 Mesa de ayuda

#### `gestionar-base-conocimiento`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. |
| **Roles** | tecnico-dtic, jefe-tic y admin-ti, por `PERMISOS_TECNICO_TIC` |
| **Qué decide hoy** | Nadie. `tic/base-conocimiento` (`api.php:1519`) solo tiene `index` y `show`, sin guarda, y `BaseConocimientoController` devuelve `[]` y `['id' => $id]`. La tabla `base_conocimiento` existe y está vacía. |
| **Propuesta** | **Dejarlo para el módulo futuro.** Es la fase 5.5 de `docs/diseno-subsistema-tic.md`, «a mediados de 2027, con tickets reales». Conviene moverlo a la sección de módulos futuros del enum. |
| **Opcional** | Retirar mientras tanto las dos rutas falsas, que no usa ninguna pantalla. |

### 3.5 Régimen disciplinario

Los tres permisos los tiene **solo admin-uath**, y todo el prefijo `disciplinario` pide
`role:admin-uath` (`api.php:1695`). Los requests devuelven `authorize(): true`, y el menú lleva
`roles: ['admin-uath']` (`nav.ts:209`). Darles uso no cambia nada. Los 6 archivos de
`tests/Feature/Disciplinario` crean el rol sin permisos y necesitan el fixture de la sección 3.2.

| Permiso | Rutas que pasaría a guardar | Propuesta |
|---|---|---|
| `ver-sumarios` | `GET sumarios`, `GET reglamento-interno/faltas`, `GET vistos-buenos`, `GET vistos-buenos/{id}/documento` y la entrada del menú | **Darle uso** |
| `gestionar-sumarios` | `POST sumarios`, `PUT sumarios/{id}/avanzar`, `POST` y `PUT` de las faltas del reglamento, `POST vistos-buenos`, `PUT …/transicionar` y `POST …/documento` | **Darle uso** |
| `registrar-sancion` | `POST sumarios/{id}/resolver`: impone la sanción, genera su acción de personal o, si hay destitución, la cesación | **Darle uso** |

**Alternativa:** que el asistente o el auditor lean los sumarios (`ver-sumarios`). Hoy no los ven. Es
información de sanciones, así que no se recomienda sin pedido expreso de TH.

### 3.6 Actividades

| | `ver-actividades-unidad` | `aprobar-actividades` |
|---|---|---|
| **Sin uso, comprobado** | Sí | Sí |
| **Roles** | admin-uath, asistente-uath, director, jefe-unidad, auditor | director, jefe-unidad |
| **Qué decide hoy** | «Mis actividades» del Portal (`autoservicio/mis-actividades`, `api.php:1059`) con `registrar-actividades`: cada servidor ve las suyas y nadie más. El usuario lo decidió el 2026-10-12, en el PR #431: sin revisión del jefe. Ese PR retiró el CRUD viejo y la columna `estado`. | Lo mismo: no hay nada que aprobar. |
| **Propuesta** | **Retirarlo** | **Retirarlo** |

Si más adelante se agrega la revisión del jefe, se crean de nuevo con lo que esa función necesite. No
los nombra ninguna prueba.

### 3.7 Reportería

#### `exportar-reportes`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. |
| **Roles** | admin-uath (solo) |
| **Qué decide hoy** | Las seis rutas de `reporteria` (`api.php:2564`) piden `role:admin-uath\|auditor\|director`: dashboard, configuraciones, ad hoc, y `background`, que genera el archivo en Excel o PDF. **No hay pantalla**, y la sección 6 del plan de roles cuenta Reportería entre los módulos con backend y sin pantalla. |
| **Propuesta** | **Dejarlo para el módulo futuro.** Cuando Reportería tenga pantalla, las rutas pasan del rol a los tres permisos del módulo: `ver-dashboard-ejecutivo` (dashboard), `generar-reportes` (configuraciones y ad hoc) y `exportar-reportes` (`background`). Ojo: `exportar-reportes` solo lo tiene admin-uath, así que el director y el auditor **perderían** la exportación que hoy les da el rol. Eso hay que decidirlo cuando exista la pantalla. |
| **Alternativa** | Retirarlo y dejar que exportar vaya con `generar-reportes`. |

### 3.8 Administración

#### `ver-auditoria`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. |
| **Roles** | admin-ti, admin-uath, auditor |
| **Qué decide hoy** | Nadie. Más de veinte modelos, observers y servicios escriben en `activity_log` (spatie/laravel-activitylog), pero no existe una pantalla para leerlo entero. Cada módulo muestra su propio rastro con su propia guarda: los cambios de los vínculos, en la pestaña Laboral del Expediente, por ejemplo. Pulse y la documentación de la API van por el rol admin-ti, y Telescope por correo. |
| **Propuesta** | **Dejarlo para una pantalla futura de la bitácora.** Cuando se construya, conviene **quitárselo a admin-ti**: la bitácora trae cambios de Talento Humano y de salud, y la decisión 1 del plan de roles dejó a admin-ti fuera de esos datos. |
| **Alternativa** | Retirarlo hasta que haya pantalla. |

### 3.9 Dispensario

#### `evaluar-personal-medico`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. Después del PR 17 no lo pide ninguna ruta, policy ni pantalla. |
| **Roles** | admin-dispensario |
| **Qué decide hoy** | Nadie. No hay diseño ni pantalla para evaluar al personal del Dispensario. Tampoco lo menciona ningún documento de `docs/`. |
| **Propuesta** | **Retirarlo.** Si el personal médico se evalúa, lo hará el módulo general de Evaluación del desempeño (`realizar-evaluacion`, `gestionar-evaluaciones`), que espera su diseño. Un permiso aparte del Dispensario no tendría pantalla propia. |
| **Alternativa** | Dejarlo en la sección de módulos futuros del enum, junto con los de Evaluación. |
| **Qué tocaría** | Enum, `PERMISOS_ADMIN_DISPENSARIO`, migración y `permisos-por-rol.json`. Lo nombra `AuxiliarFarmaciaTest`. |

#### `ver-historia-clinica-propia`

| | |
|---|---|
| **Sin uso, comprobado** | Sí. |
| **Roles** | Todos, porque está en `PERMISOS_BASE`. |
| **Qué decide hoy** | `GET autoservicio/mi-historia-clinica` (`api.php:1128`) devuelve la fecha, el médico, la especialidad y el diagnóstico CIE-10 de cada consulta del servidor (`AutoservicioService::obtenerMiHistoriaClinicaBasica`). **No pide permiso y no la consume ninguna pantalla.** `MiHistoriaClinicaTest` la cubre. `HistoriaClinicaPolicy::view` también deja pasar al titular, pero ningún `authorize` la usa. |
| **Propuesta** | **Darle uso en esa ruta** (`permission:ver-historia-clinica-propia`). Como lo tienen todos, no cambia nada, y la ruta queda en regla con el principio 1 del plan de roles. Si el servidor ve sus diagnósticos en el Portal es otra decisión: está en D11. |
| **Alternativa** | Retirar el permiso **y la ruta**, igual que se retiró «Mis exámenes» el 2026-10-25: el servidor no ve sus órdenes en el Portal. |

## 4. Decisiones para el usuario

La opción recomendada va primero.

| | Decisión | Opciones |
|---|---|---|
| **D1** | `ver-distributivo` | **a) Retirarlo** · b) Usarlo para que solo admin-uath vea RMU y ocupantes en Puestos (se lo quita a 4 roles) |
| **D2** | `ver-expediente-todos` y el auditor | **a) Darle uso y quitárselo al auditor**: nadie cambia · b) Darle uso y que el auditor entre al Expediente en lectura |
| **D3** | `ver-expediente-unidad` | **a) Retirarlo** · b) Dejarlo para una pantalla «Mi equipo» de la jefatura |
| **D4** | `gestionar-expediente` y `gestionar-cargas-familiares` | **a) Darles uso en vez de los `role:`**: nadie cambia · b) Dejarlos como están |
| **D5** | `cargar-documentos` | **a) Usarlo para subir y dárselo al asistente**, para poder quitarle la subida desde Usuarios · b) Retirarlo y subir con `gestionar-expediente` |
| **D6** | `gestionar-tarifas-viatico` | **a) Preguntarle a Financiero y dejarlo para una pantalla futura** · b) Construir ya la pantalla de tarifas |
| **D7** | `gestionar-base-conocimiento` | **a) Módulo futuro (fase 5.5)**, y se mueve a esa sección del enum · b) Lo mismo, y además se retiran las dos rutas falsas |
| **D8** | Los tres de Disciplinario | **a) Darles uso, solo admin-uath como hoy** · b) Darles uso y dar `ver-sumarios` al asistente o al auditor |
| **D9** | Los dos de Actividades | **a) Retirarlos** · b) Dejarlos para una revisión del jefe en el futuro |
| **D10** | `exportar-reportes` y `ver-auditoria` | **a) Módulo o pantalla futura**: se documentan en el enum · b) Retirarlos hasta entonces |
| **D11** | `ver-historia-clinica-propia` | **a) Darle uso en `mi-historia-clinica`**, y la pantalla del Portal se decide aparte · b) Retirar el permiso y la ruta |
| **D12** | `evaluar-personal-medico` | **a) Retirarlo** · b) Dejarlo con los módulos futuros de Evaluación |

## 5. Lo que salió de paso

### 5.1 Tres permisos más sin uso

El barrido de los 129 permisos encontró tres que nadie comprueba y que el PR 16 no puso en ninguna
lista. Su docblock no dice nada de ellos.

| Permiso | Roles | Qué decide hoy | Sugerencia |
|---|---|---|---|
| `gestionar-bienestar` | admin-uath | `role:admin-uath\|asistente-uath` en `bienestar/planes` y `role:admin-uath` en las encuestas (`api.php:2447`). No hay pantalla. | Módulo futuro, con Bienestar y clima |
| `generar-reportes` | admin-uath, asistente-uath, director, auditor | El rol, en `reporteria` (sección 3.7). El grep lo confunde con el comando `lotaip:generar-reportes`. | Módulo futuro, con Reportería |
| `configurar-sistema` | admin-ti | Nadie. Solo lo mencionan un comentario de `UsuarioPolicy` y `UsuarioFeatureTest`. La configuración de TIC y la de Comunicación van con sus propios permisos. | Retirarlo, o documentarlo |

`ver-dashboard-ejecutivo` sí se usa, aunque en un solo lugar: las encuestas de satisfacción de la mesa
de ayuda (`api.php:1524`).

### 5.2 Dos rutas sin la guarda que declara el menú

- **`GET estructura/plantilla`** (`api.php:314`) no pide nada: la abre cualquier usuario con sesión.
  El menú la declara con `ver-estructura` (`nav.ts:65`). Devuelve solo números (plazas, ocupación,
  personal por modalidad), sin nombres, así que el riesgo es bajo. Pero rompe el principio 1 del plan de
  roles. El arreglo es una línea: `permission:ver-estructura`.
- **`GET autoservicio/mi-historia-clinica`**: ver D11.

## 6. Cómo se aplica

Cuatro PR apilados, cada uno sobre la rama del anterior y con base `main`: se fusionan en orden. Todos
tocan `RolPermisoSeeder`, así que cada uno corrió las pruebas de paridad (los archivos de
`tests/Feature` que hacen `require database_path('migrations` o usan `ReflectionClassConstant`).

1. **#603 — Expediente por permisos (D2, D4, D5).** `ServidorPolicy`, los `role:` del Expediente y el
   menú pasan a permisos. La migración `2026_12_08` le quita `ver-expediente-todos` al auditor y le da
   `cargar-documentos` al asistente. Trae `rolesDeTalentoHumano()`, el fixture de la sección 3.2.
2. **#604 — Disciplinario por permisos (D8).** El prefijo `disciplinario`, el menú y los botones de la
   pantalla. Migración `2026_12_09`.
3. **#605 — Retiros (D1, D3, D9, D12).** Enum, seeder, la migración `2026_12_10` que borra los cinco y
   `permisos-por-rol.json`. `configurar-sistema` no se retira: no entró en las decisiones.
4. **Documentar en el enum y las dos guardas (D6, D7, D10, D11 y 5.1).** Cada permiso que espera un
   módulo o una pantalla lo dice junto a su caso, y `estructura/plantilla` y `mi-historia-clinica`
   piden su permiso. Sin migración.

En producción: `php artisan migrate` hasta `2026_12_10`. Antes, conviene mirar si alguien tiene como
permiso adicional uno de los cinco que se retiran: la migración se lo quita sin avisar, aunque no le
abría nada.
