# Diseño del subsistema de TIC: Mesa de Ayuda, Inventario y Préstamo de Equipos Informáticos

**SGTH — GAD Provincial de Esmeraldas** · Versión 2, 13 de octubre de 2026 · Base: `main` en `3ba9c2e1`

Estado: **diseño decidido**. Todas las decisiones de la sección 9 están tomadas: D1, D2, D12 y D13 con respuestas propias de TIC y las demás aceptando la propuesta (13-10-2026).

Marcas que usa el documento:

- **[Hoy]**: lo que existe en `main`.
- **[Excel]**: lo que sale del archivo *Inventario P2026 Actual1.xlsm*, pestaña «Equipos».
- **[Propuesta]**: lo que se plantea construir.
- **[Dn]**: decisión de la Unidad de TIC (sección 9).

---

## 1. Resumen

1. **El subsistema no parte de cero.** El backend ya tiene un **Módulo 12 (Inventario TI)** y un **Módulo 13 (Helpdesk)** hechos en mayo de 2026, en buena parte generados por scripts. Tienen tablas, modelos, servicios, rutas y algunas pruebas, pero **las tablas están vacías, no hay ni una pantalla** y una parte importante del código es simulada o está rota (sección 3). El trabajo consiste en **rediseñar y terminar** eso, no en construir al lado.
2. **Se propone un cuarto subsistema, «Tecnologías» (`/tic`)**, junto a Talento Humano, Dispensario y Portal. Hoy el rol `tecnico-dtic` entra al Dispensario por error.
3. **El servidor público usa la Mesa de Ayuda desde el Portal**, con «Mis tickets» (el enlace ya está en el menú y lleva a una página que no existe). También ve sus equipos en «Mis equipos».
4. **El inventario se carga desde el Excel** con un comando que primero simula y entrega un informe de coincidencias. Lo que no se pueda emparejar con seguridad (funcionario, área, piso) queda marcado para revisar a mano.
5. **Los dos módulos se enlazan.** Un ticket de hardware se abre sobre un equipo concreto y, al resolverse, deja su mantenimiento en la ficha del equipo. Eso es lo que hoy hace a mano la columna «SOPORTE» del Excel.
6. **Préstamo de equipos (agregado el 13-10-2026).** TIC presta proyectores, parlantes, auriculares y otros equipos por horas o días. Queda registrada la fecha y la hora de entrega y de devolución, y el servidor ve desde el Portal si cada equipo está libre u ocupado (sección 4.6).
7. **Plan:** 6 fases y 19 PRs, más 5 opcionales (sección 8). Primero los cimientos y el inventario; después los préstamos; luego la mesa de ayuda, que arranca en 2027; al final el tablero y los reportes.

---

## 2. Lo que dice el Excel

### 2.1 La pestaña «Equipos»

Es la tabla `Computadoras`, con **376 equipos** y 33 columnas, de las cuales 13 están ocultas. Las columnas se agrupan en bloques, uno por cada encabezado combinado de la fila 1.

| Bloque | Columnas | Lo que es | Cómo queda en el sistema |
|---|---|---|---|
| Ubicación | `PISO` | 1ER…6TO y PB (edificio matriz); SM (San Mateo), DM (Dispensario), UNAMY (UNAMYDESC). Vacío en 25 filas. Una observación dice «Equipo en Quinindé». | Catálogo **ubicaciones**: sede + piso |
| Código GADPE | `ANTERIOR` | Código contable del bien: `007-01-010-NNNNN` (computadores, 132), `007-01-028-NNNNN` (portátiles, 28), `007-01-005` (3). Hay 179 llenos y 15 con basura («MEMO 012», «NOVEDAD», «KARELIZ UTILIZA»). | `codigo_anterior` |
| | `TERREMOTO` | Columna vacía y oculta. | Se descarta |
| | `ACTUAL` | **Código institucional vigente**, único y presente en las 376 filas. Tres valores no son números: «OJO», «005578» y «revisado con el Inventario». | `codigo_institucional`, la llave del equipo |
| Funcionario | `ASIGNADO` | Nombre de quien lo usa, en texto libre y con mayúsculas mezcladas. Vacío en 16 filas. | Asignación vigente a un **servidor** |
| Departamento | `AREA`, `PROCESO` | 24 áreas con siglas propias (TTHH, DASIP, FODEPRO, CRD, GACIT…) y 31 procesos. | Unidad administrativa del SGTH, con una tabla de equivalencias |
| Tipo de bien | `TIPO`, `SUBTIPO` | COMPUTADOR (309) y PORTÁTIL (66). Subtipos: TODO EN UNO (190), COMPLETO (83), CPU (34), PORTÁTIL (66), MAC (2). | Tipo y subtipo de bien |
| Detalle | `Marca`, `Modelo`, `Serie`, `Fecha de Compra` | 18 marcas (DELL, HP, HURRICANE, LENOVO, ARI…) y 53 modelos (26 dicen «S/N»). La serie es única salvo dos «S/N». Fechas de compra de 2007 a 2026. | Ficha del equipo; la serie admite vacío |
| Mantenimiento preventivo | `Mantenimiento SOFTWARE 2025`, `TÉCNICO MANTENIMIENTO 2025` | Número de soporte y técnico (Loor, Ayoví, Apráez, Vasco, Delgado). 156 equipos. | Mantenimientos del equipo |
| | `NOMBRE EQUIPO`, `USUARIO` | Nombre en la red (`LEG-EZATIZABAL`) y cuenta del dominio (`ezatizabal`). 108 equipos. | Datos de red del equipo |
| | `MANTENIMIENTO / VIGENCIA TECNOLÓGICA` | Texto del contrato con el proveedor: proceso (`SIE-GADPE-018-2021`), cronograma («julio 2024, 2025, 2026») y administrador del contrato (Carlos Batioja, José Luis Mina, Jean Loor). En otras 118 filas trae solo un número de soporte. | **Contrato de vigencia tecnológica** enlazado al equipo |
| | `CUSTODIA ADMINISTRATIVO` | Un segundo nombre en 20 equipos, distinto del de quien lo usa. | [D3] |
| Cambio a SSD | `TIPO DE DISCO DURO`, `SOPORTE` | SSD o HDD, con su soporte. 11 equipos. | Repotenciación (un tipo de mantenimiento) y el disco en las características |
| Traspaso de custodio | `SOPORTE.`, `Memorando Nº … GADPE-HAP-GTIC-AAAA`, `USUARIO ANTERIOR` | 58 traspasos con su soporte, su memorando y la persona anterior. | Historial de asignaciones |
| Dado de baja | `SOPORTES`, `Memorando…3` | Bajas **parciales**: «MONITOR DADO DE BAJA», «TECLADO… MEMO 547». | Baja de un componente |
| Terremoto abril/2025 | `SOPORTE TERREMOTO`, `TÉCNICO` | 2 equipos. La pestaña TERREMOTO trae 55. | Evento o incidente masivo [D9] |
| Novedades | `OBSERVACIÓN`, `Novedades Dominio`, `ESTADO EQUIPO` | Texto libre. Estado: BUENO (55) y REGULAR (19), más 5 frases sueltas. | Condición física y observaciones |

### 2.2 Reglas de negocio que se desprenden

1. **Un equipo se identifica por su código institucional** (`ACTUAL`). El código anterior y la serie son datos de búsqueda, no la llave.
2. **El equipo está asignado a una persona y está físicamente en un lugar.** La unidad sale de la persona, salvo en equipos sin asignar (en bodega de TIC, en un área común o compartidos).
3. **Todo lo que le pasa al equipo deja un número de «SOPORTE».** Esos enteros (unos 32 000 a 48 800) **no son fechas**: son números de atención de un sistema de soporte. 237 de los 376 equipos tienen al menos uno. Salen de un sistema antiguo que no se puede exportar. La Mesa de Ayuda nueva arranca en 2027 con su propia numeración, y desde entonces cada evento del equipo nace de un ticket o queda enlazado a uno. Los números viejos se conservan solo como referencia en el historial del equipo (D1, resuelta en la sección 7).
4. **El traspaso de custodio lleva un memorando de TIC** (`Memorando Nº NNN GADPE-HAP-GTIC-AAAA`) y conserva al usuario anterior.
5. **Un equipo «COMPLETO» son varias piezas** (CPU, monitor, teclado, mouse), y cada pieza puede tener su propio código («INCLUYE MONITOR: 1736 - 007-01-010-03767»). Se dan de baja por separado: en la pestaña «Dado de baja» aparece «solo queda el monitor». Se propone que **los componentes con código sean equipos propios, enlazados al principal**.
6. **La vigencia tecnológica es un contrato con su cronograma.** Cubre a todos los equipos de un mismo proceso de compra, tiene un administrador y fija fechas de mantenimiento.
7. **El mantenimiento preventivo se planifica por campaña.** La pestaña «Planificador de proyectos» lo hace por piso, con un técnico, número de equipos, fechas y porcentaje de avance.
8. **Calidad de datos:** hay nombres en texto libre, siglas de área propias, estados escritos como frases, memorandos escritos en la columna de códigos y 3 códigos que no lo son. **La carga no puede ser ciega** (sección 7).

### 2.3 Las otras pestañas

No son el foco pedido, pero el modelo las debe admitir sin rediseño:

- **IMPRESORAS (86), MONITORES (24) y SCANER (28)** usan el mismo esquema: código anterior y nuevo, clase, marca, modelo, serie, custodio, departamento y estado. Impresoras agrega IP o conexión y el consumible.
- **MANTENIMIENTO** es la lista de impresoras con contrato de mantenimiento.
- **tONERSSS, Hoja1, Hoja5, Hoja6, Hoja7, TBC y TBD** son el control de **consumibles** (tóner y tinta): adquisiciones, entregas y saldo por impresora. Es un módulo aparte, que se propone para la fase 5.
- **DADO DE BAJA (144)** es el registro de bajas, con soporte, memorando y lo que quedó del equipo.
- **Dashboar** es una tabla dinámica de equipos por área. Pasa a ser el tablero.

---

## 3. Auditoría del código actual

### 3.1 Lo que existe

| Capa | Inventario TI (Módulo 12) | Helpdesk (Módulo 13) |
|---|---|---|
| Tablas | `bienes_informaticos`, `asignaciones_bien`, `mantenimientos_bien`, `tipos_bien`, `marcas`, `origenes_bien`. **Las seis vacías.** | `tickets`, `comentarios_ticket`, `categorias_ticket`, `slas`, `areas_dtic`, `tecnicos_dtic`, `encuestas_satisfaccion`, `base_conocimiento`. **Todas vacías.** |
| Modelos | `app/Models/InventarioTi/*`, varios minificados en una línea | `app/Models/Helpdesk/*`, la mayoría minificados en una línea |
| Servicio | `InventarioTiService` (357 líneas) con su interfaz | `HelpdeskService` y `SlaService` con sus interfaces; `EnviarAlertaSlaJob` cada 15 minutos |
| Rutas | `routes/api.php:1063-1105`, prefijo `inventario`, protegidas con `role:` | `routes/api.php:1107-1174`, prefijo `helpdesk`, con `role:` y partes sin protección |
| Pruebas | `tests/Feature/InventarioTi/` (3 archivos) | `tests/Feature/Helpdesk/` (2 archivos, llaman al servicio y no a la API) |
| Frontend | Nada | Solo la entrada «Mis Tickets» del Portal (`nav.ts:534`), que apunta a una página inexistente |

### 3.2 Lo que sirve

- **Asignar, devolver y el acta de entrega-recepción en PDF** (`InventarioTiService.php:198-310`, `resources/views/pdf/inventario/acta-entrega.blade.php`). Se arregló en septiembre (#28, #34, #35) y tiene pruebas.
- **La desvinculación ya consume el inventario**: el paso «devolución de bienes» lee `AsignacionBien` (`DesvinculacionService.php:335-343, 544-561`). **No se puede romper.**
- La estructura de tablas sirve como base: equipo, asignación (custodia con fecha de entrega y devolución) y mantenimiento con `ticket_id`.
- Roles `tecnico-dtic` y `admin-ti` (`app/Enums/Rol.php:33-35`). Permisos ya declarados en `app/Enums/Permiso.php:137-150`, aunque **nadie los usa**. El agrupador «TI y Helpdesk» de la pantalla de permisos ya existe (`PermisoController.php:162-168`).
- La estructura orgánica **ya tiene la Unidad de TIC y sus subunidades**: Soporte Técnico, Infraestructura y Redes, Desarrollo de Software y Seguridad de la Información. También existe **Activos Fijos y Bodega** y **Guardalmacén**, a quienes va la baja.

### 3.3 Lo que está mal

**Helpdesk:**

- `TicketController::index` devuelve **todos los tickets a cualquier usuario** y precarga una relación que no existe (`bien_informatico`).
- `TicketController::store` no llena `solicitante_id` ni `tipo_ticket`, que son obligatorios, así que **crear un ticket da 500**. Además valida un campo `prioridad` que no tiene columna.
- `TicketController::asignar` escribe en una columna que no existe (`tecnico_dtic_id`).
- `cambiarEstado` acepta cualquier texto: no hay máquina de estados. `SlaService` escribe `escalado` y `ReporteriaService` filtra por `cancelado`; **ninguno de los dos está en el enum**.
- Los comentarios **no comprueban que el ticket sea del usuario**.
- `escalarTicket`, las encuestas y la carga de trabajo son **simulados**.
- El folio es `TIC-` + `uniqid()`, sin orden ni año.
- `TicketCerradoMail` apunta a una vista `view.name` que no existe.
- Las rutas de tickets y comentarios **no tienen middleware**.
- `areas_dtic` duplica la estructura orgánica, que ya tiene las subunidades de TIC.

**Inventario:**

- `MantenimientoBienController` y el `index`/`show` de asignaciones son stubs que devuelven listas vacías.
- `registrarAuditoriaFisica` es simulado, y `escanear` pasa un código a un método que espera un id.
- `procesarBaja` solo cambia el estado: **no deja registro de la baja** ni produce un acta.
- `codigo_qr` es un texto (`código-QR`): no hay imagen QR.
- Al modelo le faltan respecto del Excel: código anterior, ubicación, nombre en red, cuenta de dominio, componentes, vigencia tecnológica, subtipo y unidad. Además, `numero_serie` es obligatoria y única, cuando el Excel trae «S/N», y `origen_bien_id` es obligatorio, cuando el Excel no lo trae.

**Comunes:**

- **No hay policies** para ninguno de los dos módulos, y todas las rutas van por rol y no por permiso. Las reglas del proyecto piden lo contrario.
- `tecnico-dtic` está en `ROLES_SALUD` (`nav.ts:586`), así que un técnico de TIC entra al Dispensario.
- Quedan los scripts generadores en la raíz del backend: `generate_m12.php`, `generate_m13.php`, `refactor_m12.php` y `refactor_categorias.php`.
- `AdminTiSeeder.php` trae una contraseña inicial en claro para una cuenta institucional real. No es parte de este plan, pero se debe corregir.

### 3.4 Lo que se reutiliza del resto del sistema

| Necesidad | Pieza existente |
|---|---|
| Máquina de estados con historial | `ViaticoEstadoService` (`TRANSICIONES`) + `ViaticoHistorialEstado` + `ViaticoHistorialCard` (línea de tiempo) |
| Folio por año sin huecos | `PermisoService::siguienteFolio()` (`pg_advisory_xact_lock`, formato `PER-AAAA-00001`) |
| Días hábiles para el SLA | `app/Helpers/DiasHabilesHelper.php` (lee el calendario de feriados) |
| Anulación | Columnas `anulado_en`/`anulado_por`/`motivo_anulacion` + `MotivoModal` |
| PDF con membrete | dompdf + `public/images/membrete-gadpe.jpg`, como el memorando de «Mis actividades» y el acta de entrega |
| QR | `simplesoftwareio/simple-qrcode` (`CertificadoLaboralService.php:163`) y la página pública `/verificar` |
| Adjuntos | Patrón de `VistoBuenoService` (disco `local`, descarga autorizada) + `ZonaArchivo.tsx` en el frontend |
| Correo | Mailables + `AvisoNotificacionService` (enviar, registrar y no romper si falla) |
| Exportar a Excel | `maatwebsite/excel`, `ReporteGenericoExport` |
| Jefe de una unidad | `FirmanteOrganigramaService::jefeDeUnidadEn()` |
| Bitácora | `spatie/laravel-activitylog` (`LogsActivity` o el trait `RegistraActividadSso`) |
| Frontend | `PageShell`, `PageHeader`, `SgthTable`, `TableActions`, `StatCard`, `StatusBadge`, `DetailList`, `FormModal`, `SgthDrawer`, `BuscadorServidor`, `useUnidades`, ECharts con `useEChartsColors`, `lib/archivo.ts` |

---

## 4. Arquitectura propuesta

### 4.1 Backend

Se conservan los espacios de nombres y prefijos existentes, para no romper la desvinculación ni los tipos generados:

```
app/
  Http/Controllers/InventarioTi/   (prefijo /api/v1/inventario)
  Http/Controllers/Helpdesk/       (prefijo /api/v1/helpdesk)
  Http/Controllers/InventarioTi/   PrestamoEquipoController, DisponibilidadPrestamoController
  Http/Controllers/Autoservicio/   MisTicketsController, MisEquiposController, MisPrestamosController  (/api/v1/autoservicio)
  Services/InventarioTi/           BienService, CustodiaService, MantenimientoService, BajaService, ImportacionInventarioService,
                                   PrestamoService, PrestamoEstadoService, DisponibilidadPrestamoService
  Services/Helpdesk/               TicketService, TicketEstadoService, SlaService, AsignacionTicketService
  Contracts/…                      una interfaz por servicio, enlazada en AppServiceProvider
  Policies/InventarioTi/, Policies/Helpdesk/
  Http/Requests/…, Http/Resources/…   un Store y un Update por recurso
  Enums/  EstadoBien, CondicionFisica, TipoMantenimiento, EstadoTicket (corregido), PrioridadTicket, CanalTicket
  Console/Commands/CargarInventarioExcelCommand.php   (inventario:cargar-excel --simular)
```

Las reglas son las de siempre: controlador delgado, `ApiResponse`, `ReglaNegocioException`, enums comparados por `->value`, `America/Guayaquil`.

**Se reescriben** los controladores y servicios de los dos módulos. Se mantienen las firmas que usa `DesvinculacionService` (asignaciones vigentes y `devolverBien`). **Las tablas se ajustan con migraciones nuevas**: están vacías, así que se pueden cambiar sin migrar datos, pero las migraciones originales ya pueden haber corrido en producción y no se reescriben.

### 4.2 Modelo de datos

```
ubicaciones ─┐                    contratos_vigencia (proceso, proveedor, administrador, desde/hasta)
             │                         │ 1─n  cronograma_vigencia (fechas de mantenimiento)
tipos_bien ─ subtipos_bien             │
marcas ──────┤                         │
             ▼                         ▼
        bienes_informaticos  ◄── bien_padre_id (componentes: monitor, teclado…)
             │ 1─n asignaciones_bien    (servidor, desde, hasta, motivo, memorando, acta)
             │ 1─n mantenimientos_bien  (tipo, ticket_id, técnico, fecha, detalle, contrato_vigencia_id)
             │ 1─n bajas_bien           (informe técnico, aprobación o rechazo, memorando, entrega a Activos Fijos)
             │ 1─n verificaciones_bien  (campaña de constatación física, resultado)
             ▼
          tickets ──► servidores (solicitante), users (técnico), unidades (área de TIC)
             │ 1─n comentarios_ticket (+ adjuntos, es_interno)
             │ 1─n historial_estados_ticket
             │ 1─1 encuestas_satisfaccion
```

**Cambios en `bienes_informaticos`:**

- Se agregan: `codigo_anterior`, `subtipo_bien_id`, `ubicacion_id`, `unidad_administrativa_id` (solo para equipos sin persona asignada), `fecha_compra`, `nombre_red`, `usuario_dominio`, `direccion_ip`, `bien_padre_id`, `contrato_vigencia_id` y `observaciones`.
- `numero_serie` pasa a ser opcional, con índice único parcial (solo sobre las series no vacías).
- `origen_bien_id` pasa a ser opcional. El origen (compra pública, donación nacional o internacional, comodato) sigue sirviendo para lo que entra donado o comprado, pero el Excel no lo trae para lo histórico.
- **`codigo_institucional` sigue siendo obligatorio y único [D2, D12, resueltas el 13-10-2026].** Todo bien, sea comprado o donado por cualquier entidad o persona, entra por Activos Fijos, que le asigna el código. TIC registra el equipo cuando ya tiene código, así que no hay estado «pendiente de código» ni códigos internos de TIC. `codigo_anterior` es complementario: en el Excel, ningún equipo, impresora, monitor ni baja tiene solo el código anterior.
- **`prestable`** (booleano) marca los equipos que forman el grupo de préstamo (sección 4.6).

**Estados del equipo.** Se separan dos cosas que el Excel mezcla:

- **Estado del equipo:** `en_uso`, `disponible` (en bodega de TIC), `en_mantenimiento`, `en_garantia` (con el proveedor), `dado_de_baja`, `extraviado`.
- **Condición física:** `bueno`, `regular`, `malo`.

**Hecho en la fase 1.1** (migración `2026_10_18_000000_modelo_de_datos_del_inventario_ti`):

- **Estados.** `en_uso` y `disponible` no se eligen: salen de la custodia. Entregar un equipo lo pone en uso y devolverlo lo deja disponible. Al editar, pedir cualquiera de los dos significa «vuelve al servicio». `dado_de_baja` solo lo pone la baja, y un equipo de baja ya no cambia de estado. Los estados viejos se convierten así:
  - `activo` pasa a `en_uso` si el equipo tiene custodio y a `disponible` si no;
  - `robado` y `perdido` pasan a `extraviado`.
- **Custodia.** Un equipo tiene un solo custodio a la vez. No se entrega si está dado de baja, extraviado o en el grupo de préstamo, y un equipo con custodio no se marca como prestable.
- **Componentes.** Un componente cuelga de un equipo principal, y solo un nivel. Un equipo con componentes no se borra.
- **Únicos sin distinguir mayúsculas.** El código institucional, la serie y los nombres de catálogo no cuentan las fichas borradas: una ficha registrada por error se puede volver a registrar bien. Se comparan sin mayúsculas, porque «DELL» y «Dell» son la misma marca.
- **Catálogos de partida.** `InventarioTiCatalogoSeeder` siembra 10 tipos con sus subtipos del Excel y 11 ubicaciones: «Edificio principal» de la PB al piso 6, San Mateo, Dispensario Médico, UNAMYDESC y Quinindé. Las marcas las crea la carga del Excel (1.4).
- **Pendiente de TIC: los años de vida útil de cada tipo.** Quedan en 0 hasta que TIC los fije desde Configuración. Sin ese dato no se calcula el fin de la vida útil.

**Mesa de ayuda:**

- `tickets` gana `prioridad`, `canal` (portal o registrado por TIC), `unidad_tic_id` (subunidad de TIC que atiende, en lugar de `areas_dtic`), `nivel`, `solucion`, `pausado_desde` y `reabierto_veces`.
- `areas_dtic` se retira y `tecnicos_dtic` pasa a apuntar a la unidad. [D6]

### 4.3 Estados del ticket

```
            asignar                 atender
abierto ───────────► asignado ───────────► en_proceso ──────► resuelto ──► cerrado
   │                     ▲  │                 │  ▲                │  ▲   (el usuario confirma,
   │                     │  └── escalar ──────┘  │                │  │    o cierre automático
   │                     │      (nivel 2 o 3)     │                │  │    a los N días hábiles)
   │                     │              pendiente_usuario ◄────────┘  │
   │                     │              (pausa el SLA)                │
   │                     └──────────── reabrir ◄──────────────────────┘ (dentro del plazo)
   └──► anulado (duplicado o mal registrado, con motivo)
```

- Las transiciones viven en `TicketEstadoService::TRANSICIONES`, como en Viáticos, y cada una deja historial: quién, cuándo y de qué estado a cuál.
- **El SLA se cuenta en horas hábiles** y se pausa en `pendiente_usuario`. El SLA depende de la categoría y la prioridad.
- **El folio es `TIC-AAAA-00001`**, con bloqueo consultivo de Postgres, como en Permisos.
- **Al resolver un ticket de hardware con equipo vinculado**, se registra el mantenimiento correctivo en la ficha. El equipo vuelve a `en_uso`, o pasa a `en_garantia` o a baja.

### 4.4 Roles y permisos

Las rutas van **por permiso**, y el alcance de cada ticket lo decide `TicketPolicy`. El middleware `permission:` de Spatie usa `canAny`, que pasa por `Gate::before`, así que `admin-ti` entraría de todos modos; aun así recibe los permisos de forma explícita, para que la pantalla de Usuarios muestre lo que puede hacer. El que no pasa por `Gate::before` es el middleware `role:`.

Hecho en el PR 0.2: el rol `jefe-tic` [D5], el permiso `registrar-mantenimiento` y las rutas por permiso. Se conservan los nombres que ya existían en el enum: `gestionar-tickets` es «atender tickets», y la configuración de la mesa de ayuda se reparte entre `gestionar-tecnicos` y `configurar-sla`. Los permisos de las filas marcadas «(fase N)» se crean con su PR, no antes.

| Permiso | Servidor (todos) | Técnico TIC | Jefe de TIC | Auditor |
|---|---|---|---|---|
| `crear-ticket`, `ver-ticket-propio` | ✓ | ✓ | ✓ | ✓ |
| «Mis equipos» del Portal: con `acceso-autoservicio`, que ya tienen todos (1.3; un permiso base nuevo habría obligado a tocar las migraciones de roles sin agregar control) | ✓ | ✓ | ✓ | ✓ |
| `gestionar-tickets`: atender la cola propia y los tickets sin asignar | | ✓ | ✓ | |
| `ver-tickets-todos`, `asignar-ticket` | | | ✓ | |
| `gestionar-tecnicos`, `configurar-sla` | | | ✓ | |
| `gestionar-base-conocimiento` | | ✓ | ✓ | |
| `ver-inventario-ti` | | ✓ | ✓ | ✓ (solo lectura) |
| `gestionar-inventario-ti`: registrar, editar, custodia | | ✓ | ✓ | |
| `registrar-mantenimiento` | | ✓ | ✓ | |
| Proponer una baja: con `gestionar-inventario-ti` (1.6) | | ✓ | ✓ | |
| `dar-baja-bien`: aprobar o rechazar la baja, su memorando y la entrega a Activos Fijos | | | ✓ | |
| Pedir un préstamo y ver la disponibilidad en el Portal: con `acceso-autoservicio`, que ya tienen todos (2.1; igual que «Mis equipos», sin permiso base nuevo) | ✓ | ✓ | ✓ | ✓ |
| Consultar la bandeja y la agenda de préstamos: con `ver-inventario-ti` o `gestionar-prestamos` | | ✓ | ✓ | ✓ (solo lectura) |
| `gestionar-prestamos` (fase 2.1): aprobar, rechazar, cancelar, entregar, recibir, préstamo directo y las reglas | | ✓ | ✓ | |

`admin-ti` tiene todo lo del jefe de TIC. Activos Fijos no tiene rol propio; si necesita consultar el inventario, se le da `ver-inventario-ti` desde Usuarios.

Cada permiso nuevo va en el enum, en `RolPermisoSeeder` y en una **migración** con `Permission::firstOrCreate`, como `2026_10_17_000000_crear_rol_jefe_tic_y_permisos_tic.php`.

### 4.5 Frontend

- **Nuevo subsistema `tic`** en `src/app/(tic)/tic/`, con un color propio.
- Archivos que toca:
  - el tipo `Subsistema` (`routes.ts:140` y las uniones de `nav.ts`);
  - `subsistemas.ts`;
  - `NAV_TIC` y `ROLES_TIC`;
  - `tokens.css`;
  - `design.tokens.ts`;
  - `mantine.theme.ts`;
  - la lista de colores permitidos en `eslint.config.mjs`;
  - el glob de la regla `pagina-con-pageshell`.
- `tecnico-dtic` sale de `ROLES_SALUD`.
- El código va en `features/inventario-ti/` y `features/helpdesk/`, siguiendo el patrón componente → hook → servicio, con esquemas Zod `zod/v4`. Las páginas son delgadas y renderizan una `…View`.
- **Portal:** se crean `portal/mis-tickets` (la entrada del menú ya existe) y `portal/mis-equipos`, que es nueva.

### 4.6 Préstamo de equipos

TIC tiene un grupo de equipos que no están asignados a nadie (proyectores, parlantes, auriculares, portátiles de préstamo, adaptadores) y que presta por horas o días a quien lo pide. El préstamo **no es una custodia**: la custodia es indefinida y lleva acta, mientras que el préstamo tiene una hora de entrega y una de devolución previstas, y otras dos reales.

**Qué se presta.** Los equipos del inventario marcados como **prestables** (`bienes_informaticos.prestable`). Mientras están en el grupo de préstamo, su custodio es la Unidad de TIC y su estado es `disponible`. Así, el préstamo aprovecha la ficha, el historial, el mantenimiento y la baja sin duplicar nada.

- **Todo lo que se presta tiene código institucional**, incluidos los auriculares, adaptadores y punteros, porque todo entra por Activos Fijos [D12]. Marcar o desmarcar un equipo como prestable lo hace TIC desde la ficha del equipo.
- **El servidor elige los equipos concretos que necesita** entre los disponibles, uno o varios en la misma solicitud. Por ejemplo, el proyector 002104 y el parlante 005130 de 09:00 a 12:00 [D13]. TIC entrega exactamente esos.

**Modelo:**

```
prestamos_equipo         folio PRE-AAAA-00001, servidor (responsable), unidad, motivo, lugar de uso,
                         inicio_previsto, fin_previsto, estado,
                         aprobado_por/en, motivo_rechazo,
                         entregado_por, entregado_en, recibido_por, devuelto_en, observaciones
  1─n prestamo_items     bien, periodo (rango de fecha y hora), accesorios entregados,
                         condición a la entrega, condición a la devolución, novedad
```

- **Un equipo no se puede comprometer dos veces en el mismo horario.** No basta con revisarlo en el servicio: lo garantiza la base de datos con una restricción de exclusión de Postgres sobre el ítem (`EXCLUDE USING gist (bien_id WITH =, periodo WITH &&)`, solo mientras el préstamo está activo). Dos personas que piden el mismo proyector al mismo tiempo no pueden quedar las dos aprobadas.
- **Al entregar y al devolver se registran la fecha y la hora reales**, quién entrega o recibe por TIC y en qué condición sale y vuelve cada equipo. Si vuelve con daño, desde la devolución se registra el mantenimiento (o se abre el ticket, cuando exista la Mesa de Ayuda).

**Estados:**

```
solicitado ──aprobar──► reservado ──entregar──► en_curso ──recibir──► devuelto
    │                      │                       │
    ├──rechazar──► rechazado   ├──cancelar (el servidor o TIC)──► cancelado
    └──cancelar───► cancelado  └──no retirado a tiempo──► no_retirado (automático)
```

- **«Vencido» no es un estado**: es un préstamo `en_curso` cuya hora prevista de devolución ya pasó. Se marca en la bandeja y en el Portal, y dispara los avisos.
- **El préstamo directo** cubre al servidor que llega al mostrador: TIC lo registra y lo entrega en el mismo paso, sin pasar por `solicitado`.

**Disponibilidad en el Portal.** Para cada equipo prestable, el servidor ve:

- el estado **ahora**: disponible, prestado hasta las 15:00, reservado desde las 10:00, o en mantenimiento;
- una **agenda** por día y por semana con los bloques ocupados, para elegir un horario libre al pedir.

El servidor no ve quién tiene el equipo; TIC sí. [D15]

**Hecho en la fase 2.1** (migración `2026_10_23_000000_prestamo_de_equipos`):

- **Tablas:** `prestamos_equipo` (folio `PRE-AAAA-00001`), `prestamo_items` (el horario de cada equipo, sus accesorios y su condición a la entrega y a la devolución), `historial_estados_prestamo` y `reglas_prestamo` (una fila).
- **Sin solapes:** `EXCLUDE USING gist (bien_informatico_id WITH =, periodo WITH &&) WHERE (bloquea)`. `periodo` es un `tsrange` semiabierto generado desde `inicio` y `fin`, así que un préstamo que termina a las 12:00 no choca con uno que empieza a las 12:00. `bloquea` es verdadero mientras el préstamo está reservado o en curso. La migración crea la extensión `btree_gist`, que desde PostgreSQL 13 es de confianza y no pide superusuario.
- **La solicitud no ocupa el horario:** dos servidores pueden pedir el mismo proyector a la misma hora. Al aprobar una, la otra ya no se aprueba, y desde el Portal ese horario ya no se puede pedir. El mismo servidor no pide dos veces el mismo equipo para el mismo horario.
- **Reglas (D16), configurables:** plazo máximo en días hábiles (el día de la entrega cuenta como el primero; se descuentan fines de semana y feriados institucionales), anticipación mínima, horario de atención (por defecto de 08:00 a 17:00), préstamo en fin de semana y feriados, bloqueo con un préstamo vencido y margen para retirar. Los errores de horario salen en su campo.
- **Lo que se presta:** solo lo prestable que esté en TIC. No sale lo que está en mantenimiento, en garantía, extraviado o dado de baja, ni lo que sigue fuera en un préstamo vencido, aunque su horario previsto ya haya pasado.
- **Entregar y recibir** piden la fecha y hora reales (no futuras ni anteriores al paso previo) y la condición de cada equipo del préstamo. La condición con que vuelve pasa a la ficha del equipo.
- **Guardas en el inventario:** un equipo reservado o prestado no sale del grupo de préstamo, no pasa a mantenimiento ni a garantía y no se da de baja hasta que se reciba o cancele ese préstamo.
- **Disponibilidad:** consulta de hasta 31 días. El Portal ve el estado ahora y los bloques ocupados; TIC ve además el folio y quién tiene el equipo.
- El paso automático a `no_retirado`, los avisos por correo y el bloqueo en la desvinculación van en la 2.4.

**Hecho en la fase 2.3:** la bandeja `/tic/prestamos` con las pestañas Por aprobar, Reservados (todas las reservas, de la más próxima a la más lejana; la cabecera avisa cuántas se retiran hoy), En curso, Vencidos e Historial, cada una con su contador. Aprobar se confirma; rechazar y cancelar piden motivo; entregar y recibir piden la hora real y la condición de cada equipo, con sus accesorios a la salida y la novedad a la vuelta. El préstamo directo elige al servidor y los equipos que están en TIC ahora. El detalle `/tic/prestamos/[id]` dice quién pidió y quién hizo cada paso, con los botones del paso que toca. La agenda `/tic/prestamos/agenda` es la del Portal con el folio y el nombre en cada bloque. Las reglas se editan en Configuración › Préstamos (`gestionar-prestamos`); el auditor las ve como lista. El acta en PDF (D17) se imprime desde la entrega, para los préstamos que la necesiten.

**Hecho en la fase 2.4:**

- **Correos al servidor** (`PrestamoEquipoMail`, a la dirección institucional o, si no tiene, la personal): cuando TIC aprueba, rechaza o cancela; en la entrega y en la devolución (D17), con las novedades; una hora antes de la devolución prevista; cuando vence, y cuando la reserva no se retiró. Si cancela el propio servidor, no se le escribe. Un correo que no sale no deshace el paso.
- **A TIC**, a quienes tienen `gestionar-prestamos`: el préstamo vencido, una vez.
- **`sgth:prestamos:seguimiento`**, cada quince minutos: la reserva cuyo inicio pasó hace más que el margen de la regla, sin entregarse, pasa a `no_retirado` y libera el horario; salen el recordatorio y el aviso de vencido, cada uno una sola vez (`recordatorio_enviado_en`, `aviso_vencido_en`).
- **Desvinculación:** el paso «Devolución de los bienes» queda pendiente mientras el servidor tenga un préstamo en curso, y los lista; la devolución la registra TIC en Préstamos.
- **Reporte de uso** (`/tic/prestamos/reporte`, y en Excel): por equipo, los préstamos entregados en el rango, las horas fuera, las devoluciones tarde y las reservas no retiradas.

---

## 5. Pantallas y navegación

### 5.1 Subsistema «Tecnologías» (`/tic`)

```
Tecnologías
├─ Inicio                          tablero: tickets por estado, vencidos de SLA, mi cola,
│                                  equipos por estado y unidad, vigencias por vencer
├─ Mesa de ayuda
│  ├─ Bandeja                      pestañas: Sin asignar · Mis tickets · Todos (jefe) · Vencidos
│  ├─ Registrar ticket             a nombre de un servidor (llamada o visita)
│  └─ /tic/tickets/[folio]         conversación, notas internas, estado, asignación,
│                                  escalamiento, equipo vinculado, línea de tiempo
├─ Inventario
│  ├─ Equipos                      filtros: tipo, unidad, ubicación, estado, custodio, vigencia
│  ├─ Registrar equipo
│  ├─ /tic/equipos/[codigo]        pestañas: Datos · Custodia · Mantenimientos · Tickets ·
│  │                               Componentes · Documentos
│  ├─ Mantenimientos               correctivos y preventivos; plan por campaña (fase 5)
│  ├─ Bajas                        informes técnicos y su estado
│  └─ Constatación física          campaña por piso o unidad, escaneo del QR (fase 5)
├─ Préstamos
│  ├─ Bandeja                      pestañas: Por aprobar · Por entregar hoy · En curso · Vencidos · Historial
│  ├─ Agenda                       ocupación de cada equipo prestable por día y semana
│  ├─ Préstamo directo             entrega en el mostrador, sin solicitud previa
│  └─ /tic/prestamos/[folio]       detalle: equipos, horas previstas y reales, condición, novedades
├─ Reportes                        inventario por unidad o ubicación (Excel), obsolescencia,
│                                  tickets por categoría, técnico y SLA, satisfacción
└─ Configuración                   tipos y subtipos, marcas, ubicaciones, contratos de vigencia,
                                   categorías de ticket, SLA, técnicos por unidad,
                                   reglas de préstamo (plazo máximo, horario, anticipación)
```

### 5.2 Portal del servidor

```
Portal › Soporte
├─ Mis tickets          crear (categoría, asunto, descripción, equipo opcional, captura),
│                       seguir, responder, confirmar la solución y calificar
├─ Mis equipos          los equipos a mi cargo, con «Reportar un problema»
│                       (abre un ticket con el equipo ya elegido)
└─ Préstamo de equipos
   ├─ Disponibilidad    cada equipo prestable con su estado ahora y su agenda;
   │                    «Solicitar» desde un horario libre
   └─ Mis préstamos     solicitudes y préstamos: estado, horas previstas y reales,
                        cancelar mientras no se haya entregado
```

**Hecho en la fase 2.2:** `/portal/prestamos` («Préstamo de equipos») muestra cada equipo prestable con su estado ahora y su horario, por día (la barra del horario de atención, con las horas) o por semana (una barra chica por día; cinco, o siete si TIC presta en fin de semana). Se marcan varios equipos y se piden juntos, o se hace clic en un horario libre de la barra: el formulario se abre con ese equipo, esa hora (redondeada a la media hora) y una hora de duración. Lo ocupado sale pintado, en rojo si el préstamo en curso está vencido, y al pasar el cursor dice de qué hora a qué hora. Si el horario choca, el aviso cae en el campo «Equipos». `/portal/mis-prestamos` lista las solicitudes y los préstamos con su estado, el horario previsto y el real, el detalle con la línea de tiempo, y cancelar mientras no se haya entregado. Las dos páginas van en el grupo «Soporte» del Portal.

### 5.3 Flujos principales

**Ticket**

1. El servidor lo abre desde el Portal y queda `abierto`.
2. La mesa lo asigna a una subunidad o técnico y queda `asignado`, con correo al técnico.
3. El técnico lo toma y queda `en_proceso`.
4. Si le falta información, lo pone en `pendiente_usuario` y el SLA se pausa.
5. Lo resuelve con la solución escrita. Si es de hardware, queda el mantenimiento en el equipo.
6. El servidor recibe un correo, confirma y califica. Si no responde en N días hábiles, el ticket se cierra solo.

**Alta y custodia**

1. TIC registra el equipo: códigos, serie, compra y vigencia.
2. Lo asigna a un servidor y queda el acta de entrega-recepción en PDF.
3. Para un **traspaso**, se devuelve el equipo y se hace una nueva asignación, con el número de memorando GTIC y una nueva acta. El historial conserva al usuario anterior.
4. Si el servidor sale de la institución, la desvinculación exige la devolución. Ese paso ya existe.

**Baja**

1. El técnico propone la baja desde la ficha (desde un ticket, en la fase 3) con su **informe técnico**: motivo, diagnóstico de al menos 20 caracteres y recomendación. El informe (`IB-000001`) sale en PDF para la firma. Un equipo tiene como mucho una baja propuesta o aprobada (índice parcial).
2. El jefe de TIC (`dar-baja-bien`) la aprueba con la fecha y el número del memorando de Quipux, o la rechaza con su motivo. Una baja rechazada queda en el historial y se puede volver a proponer.
3. Al aprobarla, si alguien tiene el equipo en custodia se registra la devolución; el equipo queda `dado_de_baja` y sale del grupo de préstamo. Después se adjunta el PDF del memorando y se anota la entrega a Activos Fijos y Bodega.
4. El jefe marca qué componentes salen con el equipo: cada uno lleva su propia baja, enlazada a la del principal. Los demás se desenlazan y siguen en servicio.
5. Una ficha con una baja, aunque rechazada, ya no se puede borrar como «registrada por error».

**Etiqueta QR (1.6).** El QR lleva la dirección de la ficha del equipo en el SGTH, y el código va escrito al lado para quien no tenga lector. Sale una etiqueta de 70 × 40 mm desde la ficha, o las del filtro del listado en hojas A4 de 3 × 8, hasta 240 de una vez.

**Préstamo**

1. El servidor mira la disponibilidad en el Portal, elige uno o varios equipos y un horario libre, y escribe el motivo y el lugar. El préstamo queda `solicitado`.
2. TIC aprueba (queda `reservado` y el horario se bloquea) o rechaza con motivo. El servidor recibe un correo en los dos casos.
3. Cuando el servidor retira el equipo, TIC registra la entrega con **fecha y hora reales**, los accesorios y la condición del equipo. El préstamo queda `en_curso`.
4. Antes de la hora prevista de devolución, el servidor recibe un recordatorio. Si la hora pasa, el préstamo aparece como **vencido** y se avisa al servidor y a TIC.
5. Cuando el servidor devuelve el equipo, TIC registra la **fecha y hora reales**, quién recibe y la condición en que vuelve. El préstamo queda `devuelto` y el equipo vuelve a estar disponible. Si vuelve dañado, se registra la novedad y el mantenimiento.
6. Si el servidor no retira el equipo dentro del margen configurado, la reserva pasa sola a `no_retirado` y libera el horario.

---

## 6. Integraciones

- **Expediente o desvinculación:** sigue leyendo las asignaciones vigentes. Se agrega, opcionalmente, la pestaña «Equipos a su cargo» en la ficha del servidor. **Un préstamo en curso también bloquea el paso de devolución de bienes**, igual que una custodia.
- **Estructura:** las áreas del Excel se mapean a unidades administrativas, y las subunidades de TIC hacen de áreas de atención.
- **Correo:** al asignar, al pedir información, al resolver y en las alertas de SLA; en préstamos, al aprobar o rechazar, el recordatorio de devolución y el aviso de vencido. Todos con el patrón de `AvisoNotificacionService`.
- **Reportería:** se corrige el KPI de tickets del tablero general (`ReporteriaService.php:107-122`).

---

## 7. Carga inicial desde el Excel

**Hecho en la fase 1.4.** El comando es `php artisan inventario:cargar-excel "archivo.xlsm" --simular`. Se corre **en producción**, donde están los servidores reales (en local hay 13 servidores de prueba). Funciona así:

- **Con `--simular`** hace la carga entera, la deshace y deja el informe en `storage/app/private/inventario/cargas/`.
- **Sin `--simular`** pide `--responsable`, que queda en la bitácora.
- **Se puede repetir:** los códigos que ya están en el inventario se omiten.
- **No hace falta un permiso** `importar-inventario-ti`: es un comando de consola y no una pantalla.

1. **Lee la pestaña «Equipos».**
   - Las columnas se leen por posición, porque hay dos «SOPORTE» y dos «Memorando Nº 0xx». Antes de leer comprueba que PISO, ACTUAL, ASIGNADO, Serie y Fecha de Compra sigan en su sitio; si alguien movió una, se detiene.
   - La llave es `ACTUAL` **completado a seis dígitos** (1774 → `001774`), como escribe Activos Fijos; Excel guardó los códigos como números y perdió los ceros.
   - Las filas con un código que no es un número («OJO», «revisado con el Inventario») se informan y no se cargan.
2. **Empareja el funcionario con un servidor.** Coinciden si todas las palabras del Excel, sin tildes ni mayúsculas, están en su nombre completo e incluyen su primer apellido.
   - Si encajan varios, no se elige ninguno: el Excel suele omitir el segundo nombre.
   - Un servidor inactivo tampoco se asigna.
   - Todo eso queda **sin custodio y en el informe**.
   - Si hay «CUSTODIA ADMINISTRATIVO», ese es el custodio y el «ASIGNADO» es quien usa el equipo (D3).
3. **Mapea el área a una unidad** con la tabla de `config/inventario.php` (`carga.areas`), que TIC revisa. Solo se usa para los equipos sin custodio. «SAN MATEO» no tiene equivalencia: es una sede, y sale en el informe.
4. **Mapea el piso a una ubicación:** 1ER…6TO y PB son pisos del «Edificio principal»; SM, DM y UNAMY son otras sedes.
5. **Crea los catálogos** que falten, sin distinguir mayúsculas: tipos, subtipos y marcas («DELL » y «DELL» son la misma).
6. **Convierte las columnas de eventos en historial, sin inventar fechas.** El Excel no dice cuándo:
   - el mantenimiento de software de 2025 y los números de la columna de vigencia → mantenimiento preventivo **sin fecha**, con su técnico y su número de soporte;
   - el cambio a SSD → repotenciación sin fecha;
   - el traspaso → la custodia actual lleva su memorando y su soporte, y anota quién la tuvo antes. No se crea una custodia anterior: no hay fechas para ella;
   - la custodia queda con la fecha de la carga y la nota «la fecha real de entrega no consta»;
   - las bajas parciales, el terremoto, las novedades del dominio y los textos sueltos → observaciones del equipo.

**Los números del sistema antiguo [D1, resuelta el 12-10-2026].** El sistema antiguo no se puede exportar, y la Mesa de Ayuda nueva empieza en 2027 con su propia numeración (`TIC-2027-00001`). Por eso:

- **Los números viejos no se convierten en tickets** ni se mezclan con la numeración nueva. No hay datos de ellos más allá del número.
- **Viven en el evento del equipo que respaldan**, en una columna de texto `soporte_anterior` en `mantenimientos_bien`, `asignaciones_bien` y `bajas_bien`. Esas mismas tablas tienen `ticket_id`, que apunta a la Mesa de Ayuda nueva. Un evento tiene uno de los dos, o ninguno (por ejemplo, un preventivo de campaña).
- **En la ficha del equipo** se muestran en la línea de tiempo como «Soporte N.º 45062 (sistema anterior)», sin enlace, y el buscador de equipos también busca por ese número. Sirven para cruzar con los memorandos y con lo que pida Control de Bienes o Contraloría.
- **El período de transición:** el inventario estará en uso antes que la Mesa de Ayuda, así que hasta el arranque de 2027 los técnicos siguen atendiendo con el sistema antiguo. Mientras tanto, al registrar un mantenimiento, traspaso o baja a mano se puede escribir el número viejo. **Cuando la Mesa de Ayuda arranca, ese campo deja de pedirse** y queda de solo lectura (con una fecha de corte configurable); desde entonces lo que va es el ticket.
**La vigencia tecnológica pasa a contratos** a partir del texto: el proceso (`SIE-GADPE-018-2021`), el administrador y las ventanas de mantenimiento («1.- JULIO - 2024…», «Entre el 15 y 30 de octubre del 2022 / 2023 / 2024»). Con el Excel actual salen 6 contratos; los que no traen proceso se llaman «Por revisar N: …» y TIC los corrige desde Configuración.

**El informe** es un Excel con cuatro pestañas: el resumen, cada equipo con su resultado y sus avisos, los nombres que no se emparejaron y por qué, y los contratos creados. Con el informe revisado, se corre sin `--simular`.

---

## 8. Plan de trabajo

Cada punto es un PR con sus pruebas Pest y, si hay pantalla, revisión en el navegador. El orden propuesto deja algo usable al cierre de cada fase.

### Fase 0: Cimientos

| PR | Contenido |
|---|---|
| 0.1 | Subsistema `tic` en el frontend (shell, color, navegación, inicio vacío) y `tecnico-dtic` fuera del Dispensario |
| 0.2 | Permisos y policies de los dos módulos (enum, seeder, migración), rutas por permiso y retiro de los scripts generadores |

### Fase 1: Inventario

| PR | Contenido |
|---|---|
| 1.1 | Modelo de datos: migraciones de ajuste, catálogos (ubicaciones, subtipos), contratos de vigencia, enums de estado, servicios y recursos reescritos |
| 1.2 | Pantallas de equipos: listado con filtros, ficha con pestañas, registrar y editar, catálogos en Configuración |
| 1.3 | Custodia: asignar, traspasar y devolver, con acta en PDF y memorando; historial; «Mis equipos» en el Portal |
| 1.4 | Importador del Excel con simulación e informe |
| 1.5 | Mantenimientos: correctivo, preventivo y repotenciación, ligados al contrato de vigencia y al ticket |
| 1.6 | Bajas: informe técnico en PDF, aprobación, componentes que quedan; etiqueta QR imprimible por equipo |

### Fase 2: Préstamo de equipos

Depende solo de la fase 1 (1.1 y 1.2). No necesita la Mesa de Ayuda, así que puede estar en uso antes de 2027.

| PR | Contenido |
|---|---|
| 2.1 | Backend: equipos prestables, préstamos e ítems, estados con historial, folio `PRE-AAAA-00001`, restricción de no solapamiento, reglas (plazo, horario, anticipación), permisos y policies |
| 2.2 | Portal: «Disponibilidad» (estado ahora y agenda por día y semana), solicitar, «Mis préstamos» y cancelar |
| 2.3 | `/tic/prestamos`: bandeja, aprobar o rechazar, entregar y recibir con fecha, hora y condición, préstamo directo, agenda de ocupación |
| 2.4 | Avisos (aprobado, rechazado, recordatorio, vencido), paso automático a `no_retirado`, bloqueo en la desvinculación y reporte de uso por equipo |

### Fase 3: Mesa de ayuda

| PR | Contenido |
|---|---|
| 3.1 | Backend de tickets reescrito: estados con historial, folio, prioridad, alcance por policy, comentarios con adjuntos |
| 3.2 | SLA en horas hábiles con pausa, alertas reales, escalamiento por nivel y cierre automático |
| 3.3 | Portal «Mis tickets»: crear, seguir, responder, confirmar y calificar |
| 3.4 | Bandeja y detalle del ticket en `/tic`: asignar, atender, notas internas, equipo vinculado → mantenimiento |
| 3.5 | Correos (asignado, pendiente de usted, resuelto, SLA) y configuración de categorías, SLA y técnicos |

### Fase 4: Tablero y reportes

| PR | Contenido |
|---|---|
| 4.1 | Tablero de Inicio (tickets, SLA, equipos por estado, unidad y antigüedad, vigencias por vencer, préstamos en curso y vencidos) |
| 4.2 | Reportes en Excel y PDF: inventario por unidad o ubicación, obsolescencia, tickets por técnico y categoría, satisfacción |

### Fase 5: Ampliaciones (opcional, por decidir)

| PR | Contenido |
|---|---|
| 5.1 | Plan de mantenimiento preventivo por campaña (piso, técnico, avance), como el «Planificador» |
| 5.2 | Constatación física por QR desde el celular |
| 5.3 | Carga de impresoras, monitores y escáneres |
| 5.4 | Consumibles (tóner y tinta): ingresos, entregas y saldo por impresora |
| 5.5 | Base de conocimiento |

---

## 9. Decisiones de la Unidad de TIC

| # | Pregunta | PR que afecta | Decisión |
|---|---|---|---|
| D1 | ~~¿De qué sistema salen los números de «SOPORTE»? ¿Se pueden exportar sus tickets?~~ | — | **Resuelta (12-10-2026):** el sistema antiguo no se puede exportar; la Mesa de Ayuda nueva arranca en 2027. Los números viejos quedan como `soporte_anterior` en el evento del equipo (sección 7) |
| D2 | ~~¿Quién es el dueño del código institucional? ¿TIC puede registrar un equipo antes de que tenga código?~~ | — | **Resuelta (13-10-2026):** todo bien, comprado o donado, entra por Activos Fijos, que le asigna el código. TIC registra solo equipos con código; el código es obligatorio (sección 4.2) |
| D3 | ~~«Custodia administrativo» frente a «asignado»: ¿son dos papeles (el responsable del bien ante Bienes y quien lo usa)?~~ | 1.1 y 1.3 | **Aceptada (13-10-2026):** Un solo custodio, que es quien firma el acta, y un campo opcional «usuario» cuando no coincide |
| D4 | ~~¿El memorando GTIC se genera en el sistema (número y PDF) o se hace en Quipux y solo se anota su número y su PDF?~~ | 1.3 y 1.6 | **Aceptada (13-10-2026):** Anotar el número y adjuntar el PDF; el acta sí la genera el sistema |
| D5 | ~~¿El «Jefe de TIC» es el rol `admin-ti` (que hoy es el superadministrador del sistema) o un rol nuevo `jefe-tic`?~~ | 0.2 | **Aceptada (13-10-2026):** Rol nuevo `jefe-tic`; `admin-ti` sigue siendo el administrador técnico |
| D6 | ~~¿La mesa de ayuda se divide por las subunidades de TIC (Soporte Técnico, Infraestructura y Redes, Desarrollo de Software, Seguridad de la Información)? ¿Hay un nivel 3 de proveedor por la vigencia tecnológica?~~ | 3.1 | **Aceptada (13-10-2026):** Sí, por subunidad, con el proveedor como nivel 3 ligado al contrato |
| D7 | ~~Categorías de ticket y tiempos de SLA por prioridad~~ | 3.2 | **Aceptada (13-10-2026):** TIC entrega la lista; mientras tanto se siembra una base (hardware, software, red, correo, impresoras, accesos, sistemas institucionales) |
| D8 | ~~¿Los equipos en sedes fuera del edificio (San Mateo, Quinindé, UNAMYDESC) se manejan igual?~~ | 1.1 | **Aceptada (13-10-2026):** Sí, como ubicaciones con su sede |
| D9 | ~~El terremoto de abril de 2025: ¿se registra como un evento masivo con los equipos afectados o basta una anotación por equipo?~~ | 1.4 | **Aceptada (13-10-2026):** Anotación por equipo, con su número de soporte |
| D10 | ~~Orden: ¿inventario, préstamos y después mesa de ayuda (como se propone), o mesa de ayuda antes?~~ | todo | **Aceptada (13-10-2026):** Inventario primero: tiene los datos listos, y el ticket de hardware y el préstamo necesitan el equipo. Préstamos después, porque no depende de la mesa de ayuda y la mesa arranca en 2027 |
| D11 | ~~¿Entra la fase 5 (consumibles, impresoras, plan preventivo, QR, base de conocimiento) y en qué orden?~~ | fase 5 | **Aceptada (13-10-2026):** se decide al cerrar la fase 3 |
| D12 | ~~Los accesorios que se prestan sin código institucional: ¿se registran? ¿Con qué código?~~ | — | **Resuelta (13-10-2026):** no existen. Todo lo prestable sale del inventario y tiene su código institucional, porque todo entra por Activos Fijos |
| D13 | ~~¿El servidor pide un equipo concreto o un tipo?~~ | — | **Resuelta (13-10-2026):** el servidor elige uno o varios equipos concretos entre los disponibles |
| D14 | ~~¿Quién aprueba el préstamo: solo TIC, o también el jefe inmediato del servidor?~~ | 2.1 | **Aceptada (13-10-2026):** Solo TIC; el jefe no interviene en un préstamo de horas |
| D15 | ~~En la disponibilidad del Portal, ¿el servidor ve quién tiene el equipo o solo que está ocupado y hasta cuándo?~~ | 2.2 | **Aceptada (13-10-2026):** Solo «ocupado hasta las 15:00»; el nombre lo ve TIC |
| D16 | ~~Reglas del préstamo: plazo máximo, anticipación mínima, si se presta fuera del edificio, fuera del horario o en fin de semana, y si quien tiene un préstamo vencido puede pedir otro~~ | 2.1 | **Aceptada (13-10-2026):** Configurables en `/tic`; por defecto hasta 5 días hábiles, sin préstamo nuevo mientras tenga uno vencido, y margen de 1 hora para retirar |
| D17 | ~~Constancia: ¿basta el registro con la fecha y la hora reales, o se imprime un acta de préstamo para firmar?~~ | 2.3 | **Aceptada (13-10-2026):** Registro en el sistema y correo al servidor en la entrega y la devolución; acta en PDF opcional para préstamos de varios días o fuera del edificio |
