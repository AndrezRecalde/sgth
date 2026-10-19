# Diseño del registro de cargas familiares desde el Portal

**SGTH — GAD Provincial de Esmeraldas** · Versión 2, 19 de octubre de 2026 · Base: `4bda7d4e`

Estado: **diseño decidido**. Talento Humano respondió el 19-10-2026 (sección 10): D9, D11 y D12 con observaciones propias, y las demás aceptando la propuesta. El cuestionario está en `docs/Preguntas_TH_Cargas_Familiares.docx`, con la misma numeración.

Marcas que usa el documento:

- **[Hoy]**: lo que existe en el código.
- **[Propuesta]**: lo que se plantea construir.
- **[Dn]**: decisión de Talento Humano (sección 10).

---

## 1. Resumen

1. **Lo que se pide.** Que el servidor registre sus cargas familiares desde el Portal y que Talento Humano las valide antes de que cuenten.
2. **El servidor solicita y Talento Humano valida.** Lo que el servidor manda es una **solicitud** de alta, de corrección o de baja, con su documento de respaldo (obligatorio para los hijos [D11]). No tiene ningún efecto hasta que Talento Humano la valida: no aparece en el Dispensario ni cuenta para la licencia por cuidado de hijos.
3. **Por qué así.** El Portal ya tuvo un registro directo (`mis-cargas-familiares`) y se retiró el 03-10-2026 (#313): cualquier servidor podía darse de alta familiares, que entran como pacientes del Dispensario, o borrar los que había registrado Talento Humano. La validación resuelve ese problema.
4. **Las solicitudes van en una tabla propia** (sección 3.1), no dentro de `cargas_familiares`. Así el Dispensario, las licencias y la historia clínica siguen leyendo solo cargas validadas, sin cambiar una línea.
5. **Mismo patrón que los títulos.** «Mi perfil» ya deja registrar títulos y cursos que Talento Humano valida o rechaza con motivo (17-10-2026). Las cargas usan la misma idea, con una bandeja «Cargas por validar» al lado de «Títulos por validar».
6. **Siempre abierto** [D1]: el botón *Registrar familiar* está siempre en «Mi perfil». Como nada cuenta sin validación, no hace falta un interruptor.
7. **Las condiciones de salud, en persona** [D12]: el Portal no las pide, pero avisa al servidor que se acerque a Talento Humano si su familiar tiene una discapacidad o una enfermedad catastrófica.
8. **Plan:** 3 PRs (sección 9): backend, Portal y pantallas de Talento Humano.

---

## 2. Lo que hay hoy [Hoy]

| Pieza | Dónde | Qué significa para este diseño |
|---|---|---|
| Cargas familiares del expediente | `cargas_familiares`; `CargaFamiliarController`; pestaña *Personal* (`FamiliaTab.tsx`) | Las registra solo Talento Humano (`admin-uath`, `asistente-uath`). Es la fuente que leen los demás módulos |
| Parentescos | `TipoParentesco`: `conyugue` (cónyuge o conviviente) e `hijo` | Las únicas dos opciones, y se quedan así [D3] |
| Activa / Inactiva | `cargas_familiares.estado` y el botón *Desactivar* | Significa «sigue siendo dependiente». No sirve para «pendiente» (sección 3.1) |
| Cédula única | `cargas_familiares.cedula` con índice único, que también incluye las filas borradas | Una cédula es carga de **un solo** servidor [D6] |
| Recuperar un familiar borrado | `CargaFamiliarController::store`: si la cédula es de una fila borrada, la recupera con sus datos nuevos, **aunque fuera de otro servidor** | Conserva la historia clínica del Dispensario. Desde el Portal esto no puede pasar sin validación (regla 6) |
| Dispensario | `PacienteService` busca pacientes entre las cargas **activas**, por cédula o por nombre; `HistoriaClinica::buscarOCrearPorCedula` numera la historia por cédula | Una carga validada pasa a ser paciente. Una pendiente no debe serlo |
| Licencia por cuidado de hijos | `MovimientoPersonalService::validarCuidadoDeHijos` (LOSEP, Art. 28 f): exige un hijo **activo** en Cargas familiares y que la licencia termine dentro de sus primeros 15 meses de vida | Es un motivo concreto para que el servidor registre a su hijo sin esperar a Talento Humano |
| Fecha de nacimiento | `StoreCargaFamiliarRequest`: `before:today` | Un hijo nacido hoy recién se puede registrar mañana; pasa a `before_or_equal:today` [D8] |
| Validación de títulos | `historial_academico_servidor.estado` (`por_validar`, `validado`, `rechazado`), `FormacionAcademicaService`, `MiFormacionController`, `FormacionPorValidarView` | El modelo de la bandeja, del rechazo con motivo y de «nadie valida lo suyo» |
| Solicitudes del Portal a acciones de personal | `solicitudes_accion_personal` (`pendiente`, `atendida`, `rechazada`, `retirada`), `SolicitudAccionPersonalService` | El modelo de una solicitud en tabla propia que, al atenderse, produce el registro real |
| «Mi perfil» | `MiPerfilView.tsx`: pestañas *Datos personales* y *Formación académica* | Hoy el servidor no ve sus cargas familiares en ninguna parte del Portal |

No hay ningún ajuste del sistema que Talento Humano pueda encender o apagar desde la interfaz, y no hace falta: el registro queda siempre abierto [D1].

---

## 3. Conceptos

### 3.1 La solicitud no es la carga [Propuesta]

Los títulos guardan su estado en la misma tabla del historial académico. Para las cargas eso no sirve, por tres motivos:

1. **Una carga tiene efectos fuera del Expediente.** El Dispensario y las licencias leen `cargas_familiares` filtrando por `estado = true`. Si las pendientes vivieran en la misma tabla, habría que tocar cada consulta para que no las vieran, y cualquiera que se olvide les daría acceso al Dispensario.
2. **La cédula es única en la tabla, incluidas las filas borradas.** Una solicitud pendiente o rechazada ocuparía la cédula. Una cédula escrita por error bloquearía al servidor que sí es el padre o la madre, y una que ya existe borrada ni siquiera se podría insertar.
3. **También hay correcciones y bajas.** Una solicitud de baja de una carga que ya existe no es una fila nueva de `cargas_familiares`.

Por eso las solicitudes van en `solicitudes_carga_familiar`. Al validarse, el servicio aplica el cambio sobre `cargas_familiares` con el mismo código que usa Talento Humano al registrar directamente.

### 3.2 Tipos de solicitud [D10]

| Tipo | Qué pide | Qué hace al validarse | Documento |
|---|---|---|---|
| **Alta** | Registrar un familiar nuevo | Crea la carga, o recupera la fila borrada de esa cédula con su historia clínica | Obligatorio para un hijo; opcional para el cónyuge o conviviente [D11] |
| **Corrección** | Corregir nombres, apellidos, fecha de nacimiento, sexo o parentesco de una carga suya | Actualiza la carga. La cédula no se corrige, igual que hoy | Para un hijo, obligatorio si cambia la fecha de nacimiento o el parentesco; si no, opcional |
| **Baja** | Que un familiar deje de ser carga (divorcio, fallecimiento, el hijo dejó de depender) | **Desactiva** la carga; no la borra, y la historia clínica se conserva | Opcional, con motivo obligatorio |

### 3.3 Estados [Propuesta]

| Estado | Cuándo | Lo ve el servidor |
|---|---|---|
| Pendiente | Al presentarla | Sí, con «Retirar» |
| Validada | Talento Humano la valida; el cambio ya está aplicado | Sí |
| Rechazada | Talento Humano la rechaza con motivo | Sí, con el motivo. Puede presentar otra |
| Retirada | El servidor la retira mientras está pendiente | No; queda en la bitácora |

Los nombres siguen los de `EstadoSolicitudAccion`, salvo «validada» en vez de «atendida», que es la palabra de los títulos.

### 3.4 Documentos de respaldo [D11]

| Parentesco | Documento | Obligatorio |
|---|---|---|
| Hijo o hija | Partida de nacimiento o cédula del hijo | Sí |
| Cónyuge | Acta de matrimonio | No |
| Conviviente | Documento que pruebe la unión de hecho: inscripción en el Registro Civil o declaración ante notario | No |

Sin documento, Talento Humano valida al cónyuge o conviviente con lo que tenga a mano; si le falta algo, rechaza la solicitud con el motivo («Acérquese con el acta de matrimonio»).

Un PDF de hasta 10 MB, como los títulos. Va al disco privado y se descarga solo por su endpoint, nunca por URL pública.

### 3.5 Cuándo está abierto el registro [D1]

**Siempre.** Como nada tiene efecto sin validación, tener el botón siempre visible no abre ningún riesgo, y el servidor no ve un botón que un día está y al otro no.

Se descartaron las otras dos formas que se plantearon: las campañas con fecha que abre Talento Humano y la habilitación servidor por servidor. Las dos pedían un ajuste nuevo, y la segunda obligaba al servidor a pedir permiso para poder pedir.

---

## 4. Reglas [Propuesta]

1. **Solo lo propio.** El servidor solicita para su ficha: el `servidor_id` sale de la cuenta, nunca de la petición. Una solicitud ajena responde 404, como en los títulos.
2. **Nada cuenta sin validar.** Una solicitud pendiente no aparece en el Dispensario ni en las licencias, porque no está en `cargas_familiares`.
3. **Una solicitud pendiente por cédula** y una por carga (para correcciones y bajas): la segunda no se admite, con el mensaje «Ya hay una solicitud pendiente para este familiar».
4. **La cédula ya registrada no se revela.** Si la cédula es de una carga activa de otro servidor, el mensaje es neutro: «Esta cédula ya consta en el sistema. Consulte con Talento Humano.» No dice a nombre de quién [D6].
5. **No se registra como carga a quien ya es servidor del GAD**, ni a uno mismo. El Dispensario ya lo atiende como servidor, así que como carga no le suma nada [D7].
6. **Recuperar un familiar borrado solo al validar.** Si al validar un alta la cédula es de una fila borrada, el servicio la recupera con los datos de la solicitud y su historia clínica, y la bandeja se lo advierte a quien valida antes de confirmar. Al presentar la solicitud no se toca nada: escribir una cédula ajena no debe dar acceso a la historia clínica de nadie.
7. **Un solo cónyuge o conviviente activo** a la vez [D5]. Se comprueba al presentar y otra vez al validar.
8. **Nadie valida lo de su propia familia.** Si quien valida tiene ficha y la solicitud es de esa ficha, el sistema lo impide, como en los títulos.
9. **Se valida o se rechaza una sola vez**, con bloqueo de fila (`lockForUpdate`), como en `FormacionAcademicaService::resolver`.
10. **Las condiciones de salud no se piden desde el Portal** [D12]. La discapacidad y la enfermedad catastrófica las sigue registrando Talento Humano al desplegar la fila de la carga: piden carnet del CONADIS o diagnóstico, y son datos sensibles. A cambio, el Portal avisa al servidor que se acerque a Talento Humano si su familiar tiene alguna (sección 5.4).
11. **Lo que registra Talento Humano entra validado**, como hasta ahora. El botón *Agregar carga familiar* del expediente no cambia.
12. **Sin límite de edad** [D4]. Un hijo sigue siendo carga hasta que el servidor pide la baja o Talento Humano lo desactiva. Como no se fijó una edad, el sistema no avisa por edad; si Talento Humano la fija más adelante, el aviso se agrega en la bandeja, sin desactivar a nadie de forma automática.

---

## 5. Portal del servidor [Propuesta]

### 5.1 «Mi perfil» › pestaña *Cargas familiares*

Una tercera pestaña junto a *Datos personales* y *Formación académica*:

- **Sus cargas** [D13]: nombres, parentesco, fecha de nacimiento con la edad, y si está activa. Cada una con *Pedir corrección* y *Pedir baja*. No se muestran las condiciones de salud.
- **Sus solicitudes**: tipo, familiar, fecha, estado y, si la rechazaron, el motivo. Las pendientes con *Retirar*.
- Botón **Registrar familiar**, siempre disponible [D1].
- El aviso de las condiciones de salud (sección 5.4).
- Arriba, una línea: «Talento Humano revisa cada solicitud. Hasta que la valide, el familiar no puede atenderse en el Dispensario.»

### 5.2 Formulario de alta

Cédula, nombres, apellidos, parentesco, sexo, fecha de nacimiento (hasta hoy [D8]) y documento. Son los mismos campos y las mismas validaciones del modal del expediente (`CargaFamiliarModal`), más el PDF: obligatorio si el parentesco es hijo y opcional si es cónyuge o conviviente [D11]. La etiqueta y la descripción del campo cambian con el parentesco elegido, para que el servidor sepa cuándo puede omitirlo. Al enviarlo: «Solicitud enviada. Talento Humano la revisará; hasta entonces, su familiar no puede atenderse en el Dispensario», seguido del recordatorio de la sección 5.4.

### 5.3 Aviso del resultado [D14]

Sin correo, como en los títulos y los comunicados. El servidor ve el estado en «Mi perfil».

### 5.4 Aviso de las condiciones de salud [D12]

Un aviso informativo arriba de la pestaña *Cargas familiares* y dentro del formulario de alta:

> Si su familiar tiene una discapacidad o una enfermedad catastrófica, acérquese a Talento Humano con el carnet del CONADIS o el diagnóstico para que la registren en su expediente.

Al enviar un alta, el mensaje de confirmación lo recuerda.

No es un comunicado de «Noticias y comunicados»: va donde el servidor registra a su familia y está siempre a la vista, también para quien entra meses después. Si Talento Humano quiere además difundirlo cuando se estrene la función, puede publicar un comunicado; para eso no hace falta código.

---

## 6. Talento Humano [Propuesta]

### 6.1 Menú

*Talento Humano › Cargas por validar*, debajo de *Títulos por validar*, para los mismos roles (`admin-uath`, `asistente-uath`).

### 6.2 Bandeja — `/sgth/expediente/cargas-por-validar`

Una tabla con las solicitudes, de la más vieja a la más nueva, como la de títulos: servidor y unidad, tipo, familiar y cédula, parentesco, fecha, *Ver documento* y las acciones **Validar** y **Rechazar** (con motivo obligatorio).

Antes de validar, la confirmación muestra los datos que se van a aplicar y estos avisos cuando correspondan:

- «Esta cédula tuvo un registro anterior en el Dispensario: se recuperará con su historia clínica» (regla 6).
- «El servidor ya tiene un cónyuge o conviviente activo» (regla 7; impide validar).
- En una corrección, los datos de antes y los de después.

### 6.3 Expediente › *Personal* › Cargas familiares

Si el servidor tiene solicitudes pendientes, la sección lo dice («2 solicitudes del Portal por validar») con un enlace a la bandeja filtrada por ese servidor. Todo lo demás sigue igual.

---

## 7. Arquitectura [Propuesta]

### 7.1 Backend

- **Servicio extraído.** La lógica de `CargaFamiliarController::store`, `update` y `toggleEstado` (alta con recuperación de la fila borrada, edición, desactivar) pasa a `CargaFamiliarService`. El controlador de Talento Humano y la validación de solicitudes lo usan los dos, para que la recuperación por cédula viva en un solo sitio.
- **`SolicitudCargaFamiliarService`**: `presentar`, `retirar`, `validar`, `rechazar`, `porValidar`, `descargar`. Es la misma forma que `FormacionAcademicaService` y `SolicitudAccionPersonalService`.
- **Portal** — `Autoservicio/MisCargasFamiliaresController`, con `permission:acceso-autoservicio`:
  - `GET  autoservicio/mis-cargas-familiares` — sus cargas y sus solicitudes
  - `POST autoservicio/mis-cargas-familiares/solicitudes` — alta, corrección o baja, con el PDF
  - `POST autoservicio/mis-cargas-familiares/solicitudes/{id}/retirar` — retirar una pendiente (como `mis-solicitudes/{id}/retirar` de las acciones de personal: la solicitud no se borra, queda retirada)
  - `GET  autoservicio/mis-cargas-familiares/solicitudes/{id}/documento`

  La ruta reutiliza el nombre de la que se retiró, y el comentario de `routes/api.php` se actualiza para contar por qué vuelve con validación.
- **Talento Humano** — `Expediente/SolicitudCargaFamiliarController`, con `role:admin-uath|asistente-uath`:
  - `GET  expediente/cargas-por-validar` (filtro opcional `servidor_id`)
  - `POST expediente/cargas-por-validar/{id}/validar`
  - `POST expediente/cargas-por-validar/{id}/rechazar`
  - `GET  expediente/cargas-por-validar/{id}/documento`
- **Bitácora**: `LogsActivity` sobre la solicitud (estado, quién resolvió, motivo), como `SolicitudAccionPersonal`.
- **Fecha de nacimiento** [D8]: `before_or_equal:today` en `StoreCargaFamiliarRequest` y en la solicitud. El calendario del modal del expediente (`CargaFamiliarModal`, hasta ayer desde #487) y el del Portal llegan hasta hoy.
- **Documento** [D11]: obligatorio en el alta de un hijo (`required_if:parentesco,hijo`) y en la corrección de un hijo que cambia la fecha de nacimiento o el parentesco; opcional en lo demás.

### 7.2 Modelo de datos

`solicitudes_carga_familiar`:

| Columna | Tipo | Nota |
|---|---|---|
| `id` | bigint | |
| `servidor_id` | FK `servidores` | Sale de la cuenta |
| `tipo` | string(20) | `alta`, `correccion`, `baja` (`TipoSolicitudCarga`) |
| `carga_familiar_id` | FK `cargas_familiares`, nula | La carga que se corrige o se da de baja; en un alta, la que se creó al validar |
| `cedula`, `nombres`, `apellidos` | string | Los datos propuestos (vacíos en una baja) |
| `parentesco` | string | `TipoParentesco` |
| `fecha_nacimiento` | date, nula | |
| `genero` | string, nula | `masculino` / `femenino` |
| `motivo` | text, nula | Obligatorio en una baja |
| `documento_ruta`, `documento_nombre` | string, nulas | Disco privado; la ruta no viaja al cliente. Nulas en un cónyuge sin documento o en una baja sin él |
| `estado` | string(20) | `pendiente`, `validada`, `rechazada`, `retirada` (`EstadoSolicitudCarga`) |
| `registrado_por` | FK `users` | |
| `resuelto_por`, `resuelto_en` | FK `users`, timestamp | Quién validó o rechazó, y cuándo |
| `motivo_rechazo` | string(500), nula | |
| `created_at`, `updated_at` | | |

Índices: `(estado, created_at)` para la bandeja; dos índices únicos parciales de PostgreSQL, `cedula WHERE estado = 'pendiente'` y `carga_familiar_id WHERE estado = 'pendiente'`, que sostienen la regla 3 aunque lleguen dos peticiones a la vez.

`cargas_familiares` no cambia.

### 7.3 Roles y permisos

- **Servidor**: `acceso-autoservicio`, como el resto de «Mi perfil».
- **Validar**: `admin-uath` y `asistente-uath`, los mismos que ya gestionan las cargas en el expediente [D2].
- `admin-ti` pasa por `Gate::before` en las comprobaciones con `can()`, pero las rutas de Talento Humano van por middleware de rol, como las demás del expediente.

### 7.4 Frontend

- `features/portal`: `MisCargasFamiliares.tsx` (pestaña), `SolicitudCargaModal.tsx` (alta y corrección), `SolicitudBajaModal.tsx`, `misCargas.columns.tsx`, `useMisCargas.ts` y su servicio.
- `features/expediente`: `CargasPorValidarView.tsx`, `cargasPorValidar.columns.tsx`, `RechazarSolicitudCargaModal.tsx`, `useValidacionCargas.ts`; el aviso en `FamiliaTab.tsx`.
- `config/routes.ts` y `config/nav.ts`: la ruta y la entrada del menú.
- Se reutilizan `PARENTESCO_OPTIONS`, `SEXO_OPTIONS` y `PARENTESCO_LABELS` de `constants/cargaFamiliar.ts`.
- Tipos con `npm run types:sync`.

---

## 8. Lo que no cambia

- El Dispensario, la historia clínica y la licencia por cuidado de hijos siguen leyendo `cargas_familiares` tal cual. Una carga validada desde el Portal es igual a una registrada por Talento Humano.
- Talento Humano sigue registrando, editando, desactivando y eliminando cargas desde el expediente.
- Las cargas que ya existen quedan como están: entraron validadas.
- Ningún otro beneficio depende de las cargas [D9]: ni la nómina ni el contrato colectivo las leen.

---

## 9. Plan de trabajo

| PR | Contenido | Pruebas |
|---|---|---|
| **1. Backend** | Migración, enums, `CargaFamiliarService` extraído (sin cambio de comportamiento), `SolicitudCargaFamiliarService`, rutas del Portal y de Talento Humano, documento en disco privado, bitácora | Pest: solo lo propio (404 a lo ajeno), una pendiente por cédula, cédula de otro con mensaje neutro, servidor como carga, un cónyuge activo, recuperar al validar y no al presentar, nadie valida lo suyo, validar dos veces, baja que desactiva, PDF obligatorio para un hijo y opcional para el cónyuge, hijo nacido hoy, las pruebas actuales de cargas en verde |
| **2. Portal** | Pestaña *Cargas familiares* de «Mi perfil», formularios, retirar, estado y motivo, aviso de las condiciones de salud | Lint, tipos, navegador con un usuario servidor |
| **3. Talento Humano** | Bandeja *Cargas por validar*, validar con avisos, rechazar, documento, aviso en el expediente | Lint, tipos, navegador |

Producción: `php artisan migrate`. No hay datos que conciliar: las cargas actuales no cambian.

---

## 10. Decisiones

Todas tomadas el 19-10-2026: D9, D11 y D12 con respuestas propias de Talento Humano, y las demás aceptando la propuesta.

| # | Pregunta | Sección | Decisión |
|---|---|---|---|
| D1 | ~~¿Cuándo puede el servidor registrar cargas: siempre, por campañas con fecha, o solo si Talento Humano se lo habilita?~~ | 3.5 | **Aceptada (19-10-2026):** siempre. No hay interruptor |
| D2 | ~~¿Quién valida?~~ | 7.3 | **Aceptada (19-10-2026):** `admin-uath` y `asistente-uath`; nadie valida lo de su propia familia |
| D3 | ~~¿Quiénes cuentan como carga familiar en el GAD?~~ | 2 | **Aceptada (19-10-2026):** los dos de hoy, cónyuge o conviviente e hijos |
| D4 | ~~¿Un hijo deja de ser carga a cierta edad? ¿El sistema debe avisar o desactivarlo?~~ | 4, regla 12 | **Aceptada (19-10-2026):** el sistema no desactiva a nadie solo. Como no se fijó edad, tampoco avisa; si Talento Humano la fija, el aviso se agrega en la bandeja |
| D5 | ~~¿Un solo cónyuge o conviviente activo a la vez?~~ | 4, regla 7 | **Aceptada (19-10-2026):** sí |
| D6 | ~~Si padre y madre trabajan en el GAD, ¿el hijo es carga de uno solo o de los dos?~~ | 4, regla 4 | **Aceptada (19-10-2026):** de uno solo, como hoy. Si el otro lo registra, mensaje neutro y Talento Humano decide |
| D7 | ~~¿Se puede registrar como carga a otro servidor del GAD?~~ | 4, regla 5 | **Aceptada (19-10-2026):** no; el Dispensario ya lo atiende como servidor |
| D8 | ~~¿Se podrá registrar a un hijo desde el mismo día en que nace?~~ | 7.1 | **Aceptada (19-10-2026):** sí, `before_or_equal:today`, también en el expediente |
| D9 | ~~¿Qué otros beneficios dependen de las cargas?~~ | 8 | **Resuelta (19-10-2026):** ninguno: queda como hoy, solo el Dispensario y la licencia por cuidado de hijos. La baja no exige documento |
| D10 | ~~¿El servidor también puede pedir correcciones y bajas, o solo altas?~~ | 3.2 | **Aceptada (19-10-2026):** las tres. La baja desactiva y no borra |
| D11 | ~~¿Qué documento de respaldo se pide?~~ | 3.4 | **Resuelta (19-10-2026):** obligatorio para los hijos (partida de nacimiento o cédula); opcional para el cónyuge o conviviente |
| D12 | ~~¿Las condiciones de salud del familiar también se piden desde el Portal?~~ | 4, regla 10; 5.4 | **Resuelta (19-10-2026):** no, pero el Portal avisa al servidor que, si su familiar tiene una discapacidad o una enfermedad catastrófica, se acerque a Talento Humano |
| D13 | ~~¿Qué ve el servidor de sus cargas?~~ | 5.1 | **Aceptada (19-10-2026):** nombres, parentesco, fecha de nacimiento, edad y si está activa; no las condiciones de salud |
| D14 | ~~¿Se avisa al servidor por correo cuando validan o rechazan?~~ | 5.3 | **Aceptada (19-10-2026):** no; lo ve en «Mi perfil» |
