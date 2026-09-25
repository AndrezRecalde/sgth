# Pendientes tras la revisión del módulo Riesgos Laborales / SSO

Estado a 2026-09-25, con `main` en `fd7bb42`. La revisión completa se ejecutó en
tres bloques ([#159](https://github.com/AndrezRecalde/sgth/pull/159)) más la
configuración de pruebas ([#160](https://github.com/AndrezRecalde/sgth/pull/160)).

Esto es lo que **no** se hizo, separado por qué clase de pendiente es. Cada
punto se verificó contra el código de `main`, no se listó de memoria.

---

## 1 · Comprobación manual que sigue debiendo hacerse

Es el pendiente de verdad, y el único que bloquea confiar en lo entregado.

**Por qué**: la mitad frontend se verificó con el API interceptado por un arnés
de Playwright. Los 422 caen en su campo porque esos 422 los fabricó el arnés.
Los mensajes se copiaron del backend real, así que la vuelta en vivo debería
confirmar y no descubrir — pero eso es una expectativa, no un hecho comprobado.

Media hora contra el backend real, en `/sgth/riesgos-laborales`:

- [ ] **Horas trabajadas** (`/indicadores` → botón «Horas trabajadas»). Cargar un
      período que ya existe. Debe rechazarlo con el mensaje **debajo del campo
      Período**, y no sobrescribir la cifra anterior.
- [ ] **EPP por puesto** (`/epp` → «EPP por puesto»). El desplegable no debe
      ofrecer equipos que el puesto ya requiere. Si se fuerza por API, el
      backend responde 422 sobre `equipo_proteccion_id`.
- [ ] **Kit de EPP** (`/entregas-epp` → «Registrar movimiento» → «Entregar kit
      completo»). Entregar un kit, cerrar, reabrir y volver a elegir al mismo
      servidor: los equipos entregados ya no deben aparecer pendientes.
- [ ] **Auditor**. Entrar con un usuario que tenga `ver-reportes-sso` pero no
      `gestionar-sso`. Debe ver las nueve pantallas y no ver ninguna acción de
      gestión.
- [ ] **Tablero** (`/`). Registrar un riesgo o un accidente y confirmar que las
      cifras del tablero se enteran sin recargar la página.
- [ ] **Anchos**. Cargar unidades y normativas con nombres largos reales
      («Dirección de Gestión de Obras Públicas y Vialidad»). Con nombres cortos
      no se nota nada de lo que se arregló.

---

## 2 · Hallazgos del informe que no se ejecutaron

El informe original tenía más ítems que los tres bloques acordados. Estos siguen
abiertos, con la severidad que se les puso entonces.

### 🟠 1.4 · Los índices proactivos mezclan alcances sin decirlo

`SsoService::calcularIndicadoresProactivos()` acepta una unidad, pero solo
`inspecciones_realizadas` filtra por ella. Las capacitaciones y la cobertura de
EPP salen institucionales —`capacitaciones_sso` no tiene columna de unidad—, y
la respuesta se titula con la unidad. Es el tipo de número que acaba en un
informe al Ministerio.

**Arreglo**: o se añade la unidad a `capacitaciones_sso` y al cálculo de
cobertura, o cada indicador declara su alcance (`"alcance": "institucional"`) y
la pantalla lo muestra. Implícito no puede quedarse.

### 🟠 3.1 · Inspecciones y capacitaciones SSO no tienen interfaz

Las rutas y los servicios existen; no hay pantalla. Se cargan solo por API, y
los indicadores proactivos las cuentan.

### 🟡 1.8 · `fecha_apertura` de las campañas no se respeta

`PsicosocialService::campaniaAbiertaPorCodigo()` y su gemelo en `AssistService`
comprueban `activa` y `fecha_cierre`, no la apertura. Una campaña creada con
apertura el mes que viene ya se responde hoy con su enlace. Si la fecha no
manda, el campo es decorativo; si manda, falta la comprobación.

### 🟡 1.10 · Filtros de listado sin validar y `por_pagina` sin techo

Los `index` del módulo pasan `$request->all()` al servicio. `por_pagina=1000000`
es una consulta legítima para el sistema, y un valor no booleano en `estado`
llega crudo a un `where` sobre columna `boolean`.

**Arreglo**: un `FormRequest` por listado con `por_pagina` acotado
(`integer|between:1,100`).

### 🟡 3.2 y 3.3 · La dimensión «por unidad administrativa» está a medias

`DashboardSsoService::resumen()` recibe la unidad y solo se la pasa a los dos
bloques de indicadores; los otros siete la ignoran. La pantalla, además, no
ofrece elegir unidad, así que la dimensión está construida y es inalcanzable.

### 🟡 4.2 y 5.7 · El ausentismo tiene dos fuentes y salta la capa de hooks

`AusentismoTab` llama a `asistenciaService` con un `useQuery` escrito dentro del
componente, en vez de pasar por un hook del módulo. En esta revisión solo se le
cambió la clave de consulta para que entrara en la jerarquía nueva.

---

## 3 · Deuda estructural del repositorio

No es del módulo SSO, pero es lo que hace que todo lo anterior cueste más.

- [ ] **No hay CI.** No existe `.github/workflows/`. Nada comprueba nada
      automáticamente: la revisión humana es el único filtro antes de `main`.
- [ ] **El frontend no tiene herramientas de prueba instaladas.** Ni vitest, ni
      jest, ni Playwright. Los cuatro arneses que verificaron esta revisión
      —formularios, invalidaciones de caché, endpoints por pantalla y anchos de
      campo— viven fuera del repositorio y se perderán. Si se quieren conservar,
      hay que decidir dónde y con qué dependencias.
- [ ] **42 ramas en el remoto**, muchas de sprints cerrados
      (`feature/sprint-01-auth` … `feature/sprint-13-pruebas-golive`). Entre
      ellas `claude/bold-planck-p1tjvp`, la de esta revisión, ya mergeada.
- [ ] **Un aviso de deprecación** en `tests/Feature/InventarioTi/ActaEntregaTest.php:85`:
      `actaRenderizada(): Implicitly marking parameter $entrega as nullable is
      deprecated`. Sale en cada ejecución de la suite.

---

## 4 · Decisiones tomadas que conviene conocer

No son pendientes: se decidieron a propósito y quedan anotadas por si alguien
las quiere al revés.

- **`APP_LOCALE` no se fija en `phpunit.xml`.** `TraduccionesValidacionTest`
  afirma que la aplicación está en español; fijarlo ahí convertiría esa
  aserción en una comprobación del propio `phpunit.xml`. Con `.env.example` en
  `es`, un checkout limpio pasa, y un `.env` en inglés **debe** fallar.
- **La tasa de riesgo sigue calculándose desde los dos índices ya redondeados**
  (`IndicesReactivos`). Arrastra el redondeo de ambos, pero la diferencia
  aparece en el tercer decimal y se reporta el segundo. Cambiarlo movería
  cifras ya entregadas al IESS.
- **`APP_FAKER_LOCALE` se queda en `en_US`.** Ninguna prueba lo exige y
  cambiarlo altera los datos que generan las factories.
- **El `down()` de la migración `descripcion_de_riesgo_laboral_a_texto` trunca
  a 255 caracteres.** Revertirla pierde texto.
- **`horas_trabajadas_periodo` tiene ahora un índice único parcial** para el
  total institucional. Su migración **aborta nombrando los períodos** si
  encuentra duplicados previos, en vez de borrar filas: cuál de las dos cifras
  vale lo decide quien las cargó.
