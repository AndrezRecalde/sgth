# Diseño de Acciones de Personal y actos laborales

**SGTH — GAD Provincial de Esmeraldas** · Versión 2, 9 de octubre de 2026 · Base: `main` en `0f5a9397`

Estado: **diseño decidido**. Incorpora las respuestas de Talento Humano del 9 de octubre (sección 11). Solo quedan pendientes los documentos y confirmaciones de la sección 11.2.

Marcas que usa el documento:

- **[Ley]**: lo resuelve una norma vigente, con su artículo.
- **[TH]**: decisión de Talento Humano, con la pregunta que la tomó (por ejemplo, [TH N9]) o su fecha.
- **[Hoy]**: lo que hace el sistema en `main`.
- **[Pendiente]**: falta un documento o una confirmación de TH (11.2).

---

## 1. Resumen

1. **Cinco regímenes, cinco instrumentos y un solo registro.** Cada régimen formaliza sus actos con el documento que le da la ley. Todos los actos comparten el registro, el historial, la aplicación en la fecha en que rigen y la nómina.

   | Régimen | Quiénes | Instrumento | Sección |
   |---|---|---|---|
   | LOSEP, con nombramiento | permanente, provisional (incluido el de prueba), libre nombramiento | **Acción de personal** (`AP-AAAA-NNNN`) | 4 a 7 |
   | LOSEP, servicios ocasionales | ocasionales | Ingreso: **registro del contrato**, sin acción [TH N7]. Lo demás: acción de personal | 9.11 |
   | Código del Trabajo | obreros | **Contrato, adenda y hoja de registro interno**, sin acción de personal [TH] | 9.15 |
   | Contrato civil | servicios profesionales | **Contrato y adendas**, sin acción [TH N6] | 9.10 |
   | Dignidades de elección popular | Prefecto y Viceprefecto | **Acto con su documento de origen**, sin acción [TH] | 9.14 |

2. **Las acciones de personal de la LOSEP quedan en 16 clases y 7 familias**, alineadas con el Art. 21 del Reglamento. Se usan los nombres de la ley [TH N1]:
   - **traslado**: la persona pasa a otro puesto vacante de la misma institución;
   - **traspaso**: el puesto cambia de unidad;
   - **ascenso**: con prueba y reserva del puesto anterior [TH N2].

   El grupo sigue llamándose «Cambio administrativo», como hoy [TH N4].
3. **Lo que el sistema hace mal hoy y este diseño corrige:**
   - cuatro acciones que se registran y no cambian nada;
   - una cesación que no desactiva al servidor;
   - actos que pasan fuera del módulo sin acto (cambios de puestos ocupados, borrado de puestos);
   - una nómina que no lee ningún acto;
   - un catálogo que mezcla actos, tipos antiguos y bitácora.
4. **Lo que decidió TH y cambia la propuesta original:**
   - las licencias con remuneración y las vacaciones van **sin acción**, en Asistencia [TH N11, N12];
   - no se usa el cambio administrativo temporal del Art. 38 [TH N4];
   - la licencia sin remuneración queda solo para permanentes [TH N9];
   - los reemplazos siguen siendo ocasionales o profesionales [TH N10];
   - no hay delegación de firma [TH 20, 21, N14];
   - la amonestación escrita **sí** lleva acción [TH N13];
   - se agrega la restitución con datos judiciales [TH N15];
   - la supresión de puestos se aplaza [TH N16].
5. **Plan**: 7 fases y unos 32 PRs (sección 12). La fase 1 no depende de nada pendiente. Los documentos que faltan de TH (contrato colectivo y reglamento interno) bloquean solo una parte del bloque de obreros.

---

## 2. Diagnóstico: la ley contra el sistema

Esta sección describe el sistema **antes** del rediseño.

### 2.1 Los actos del Art. 21 del Reglamento, uno por uno

El Art. 21 del Reglamento a la LOSEP enumera lo que se formaliza en el formulario «Acción de Personal». El formulario oficial del Ministerio (formato del 27-05-2014, aún en uso) trae 23 casillas.

| Acto (Art. 21) | Base | [Hoy] en el sistema | Brecha |
|---|---|---|---|
| Ingreso / nombramiento | LOSEP 16–18 | `ingreso`: crea el vínculo | Pisa la antigüedad (`ContratoServidorService.php:645-648`). Concurso formal → permanente directo, sin prueba (`SolicitudCertificacionController.php:662`). Sin acta del concurso |
| Reingreso | Reg. 21 | Es un ingreso más | Pisa `fecha_ingreso_institucion` |
| Restitución | LOSEP 46 | No existe | — |
| Reintegro | LOSEP 32 | No existe: lo único posible es anular la ausencia | — |
| Ascenso | LOSEP 68 | Retirado el 2026-07-23; se operaba como cesación + ingreso | Pierde el derecho a volver al puesto anterior y reinicia la antigüedad |
| Traslado | LOSEP 35–36 | Se llama «Traspaso» (solo permanentes) y «Prestación de servicios» (los demás) | Nombre cruzado; no valida igual remuneración ni vacante del mismo nivel |
| Traspaso | LOSEP 37 | No existe como acto sobre el puesto. Estructura cambia la unidad de un puesto ocupado sin acción | — |
| Intercambio voluntario | LOSEP 39 | El «Traslado administrativo» (entre instituciones) es documental | No cierra ni crea vínculo |
| Comisión con remuneración | LOSEP 30 | Existe | Exige 2 años de antigüedad y 1–6 años de duración. La ley vigente pide 1 año y permite hasta 2 años. El «2 años» venía de la LOIP anulada |
| Comisión sin remuneración | LOSEP 31 | Existe | Igual. Además, el tope legal es 6 años **acumulados en toda la carrera** |
| Licencia con remuneración | LOSEP 27 | Solo como «motivo» de una solicitud de vacaciones (`MotivoVacacion.php:6-16`) | Sin topes ni elegibilidad |
| Licencia sin remuneración | LOSEP 28 | Existe como acción **y** como motivo de vacaciones | Duplicada. La acción no pide fechas ni causal |
| Vacaciones | LOSEP 29 | Solicitud con PDF propio en Asistencia | — |
| Subrogación | LOSEP 126 | Módulo propio que crea la acción | No valida que el puesto sea del nivel jerárquico superior |
| Encargo | LOSEP 127 | Mismo módulo; se guarda como `subrogacion` | No se distingue en la acción; exige fecha de fin |
| Sanciones | LOSEP 43 | Desde Disciplinario (multa y suspensión) y **también a mano** desde el formulario | Se puede sancionar sin sumario. Anular la acción no revierte la sanción |
| Incremento de remuneración | Reg. 21 | Acción solo para Código del Trabajo | **No cambia el sueldo**: no hay campo para el monto |
| Revisión de la clasificación | LOSEP 61–62 | No existe. Estructura cambia el grupo ocupacional (el sueldo) de un puesto ocupado sin acto | — |
| Cesación | LOSEP 47 | 6 causales | Faltan remoción, supresión, fallecimiento, fin del contrato ocasional y las del Código del Trabajo. **No desactiva al servidor** |
| Destitución | LOSEP 48 | Cesación con causal destitución, desde Disciplinario y **a mano** | Igual que la sanción |
| Supresión de puesto | LOSEP 60 | `eliminarPuesto` borra el puesto aunque esté ocupado (`EstructuraService.php:496-500`) | Supresión sin cesación ni indemnización |

### 2.2 Problemas de fondo

1. **Catálogo mezclado.** `TipoMovimientoPersonal` tiene 18 valores:
   - 8 que ofrece el formulario;
   - 1 que solo crea Subrogaciones;
   - 5 tipos antiguos (traslado, traspaso, comision_servicios, comision_sin_remuneracion, destitucion);
   - 4 que son bitácora (egreso, cambio_regimen, cambio_puesto, novedad_contrato).

   La API acepta los 18 (`StoreMovimientoPersonalRequest.php:21`), así que se pueden crear por fuera tipos sin efecto, o una subrogación sin fila en `subrogaciones`.
2. **El frontend copia las reglas.** `taxonomiaAccionPersonal.ts` replica a mano la matriz del backend. Hoy coinciden, pero cada cambio hay que hacerlo dos veces.
3. **Efectos inmediatos aunque la acción rija en el futuro.** Una renuncia registrada hoy que rige en 15 días cierra el contrato hoy.
4. **La situación del servidor se puede editar sin acto.** `regimen_laboral` y `fecha_ingreso_institucion` son editables en la ficha (`UpdateServidorRequest.php:76, 135`). Nadie escribe `Servidor.estado = false`. La nómina y los períodos de vacaciones solo miran ese estado, así que un cesado sigue «activo».
5. **Nómina desconectada.**
   - Toma `puesto->rmu` (`NominaService.php:70`), no `contratos_servidor.remuneracion`.
   - No consume ningún movimiento.
6. **Documento distinto del oficial.**
   - Imprime la fecha en que rige como fecha de emisión.
   - No marca la casilla del movimiento ni tiene campo de base legal.
   - Imprime el acta de posesión en sanciones y cesaciones.
   - No hay vista previa.
7. **Permisos por rol, sin separación de funciones.**
   - El asistente suscribe, registra y anula.
   - La pantalla no consulta permisos.
   - El titular ve sus propios borradores y acciones anuladas (`api.php:383, 411-412`).
8. **Inmutabilidad parcial.** Tras registrar se pueden reescribir la explicación, las fechas y los puestos. El visto bueno impugnado reescribe la explicación de una acción registrada (`VistoBuenoService.php:369-371`).
9. **Un solo instrumento para regímenes distintos.**
   - Obreros, profesionales, ocasionales y autoridades electas reciben acciones de personal.
   - Al Prefecto saliente no se le puede cesar.
   - A los obreros se les aplican figuras de la LOSEP que el Código del Trabajo no tiene.

---

## 3. Principios del diseño

1. **Un acto, una clase legal.** Cada acto tiene una sola clase con su base legal. La familia solo agrupa en la pantalla.
2. **Las reglas viven en un solo sitio.** El catálogo del backend (un enum) declara de cada clase:
   - su régimen e instrumento;
   - quién puede recibirla;
   - qué datos pide;
   - qué efecto produce;
   - la base legal que se imprime;
   - la casilla del formulario;
   - si es temporal;
   - si compromete presupuesto;
   - si se reporta al SIITH.

   El frontend lo pide por API y no lo copia.
3. **Ley primero, Talento Humano encima.** Cada regla dice de dónde sale: un artículo o una decisión de TH. Si TH restringe algo que la ley permite (N9, N10), queda escrito como decisión de TH.
4. **El efecto ocurre el día en que rige** [TH 10]. Registrar fija el acto; el cambio en el vínculo se aplica en la fecha «Rige a partir de».
5. **Lo que se sabe de una persona sale de sus actos registrados**: vínculo, puesto, sueldo, ausencias, estado y antigüedad. Nada de eso se edita a mano sin un acto, o queda auditado como corrección.
6. **Corregir no es lo mismo que terminar antes.**
   - Un error se **anula** y se emite de nuevo [TH 2026-09-29, 23].
   - Una ausencia que termina antes se cierra con un **reintegro**, que es otro acto [TH 17].
7. **Cada régimen con su instrumento** (tabla del resumen). El orden común no está en usar el mismo formulario para todos, sino en que **todo cambio en la situación de una persona sale de un acto registrado con su documento**.
8. **Quien tiene el procedimiento crea el borrador.** Disciplinario, Reclutamiento, Subrogaciones, Asistencia y Estructura generan sus actos en borrador; el registro de actos los formaliza. A mano solo se crean los actos sin procedimiento previo.

---

## 4. Catálogo de la LOSEP (acciones de personal)

Esta sección vale para permanentes, provisionales y libre nombramiento, y para los ocasionales salvo su ingreso. Los otros regímenes tienen su propio catálogo: obreros en 9.15, contrato civil en 9.10 y autoridades electas en 9.14.

### 4.1 Familias

| Familia | Clases |
|---|---|
| **Ingreso** | ingreso, nombramiento definitivo, reintegro, restitución |
| **Cambio administrativo** | ascenso, traslado, traspaso de puesto, intercambio voluntario, comisiones de servicios |
| **Licencias** | licencia sin remuneración |
| **Reemplazo de autoridades** | subrogación, encargo |
| **Puesto y remuneración** | revisión de la clasificación |
| **Régimen disciplinario** | sanción |
| **Cesación** | cesación con su causal |

Notas:
- «Cambio administrativo» se mantiene como nombre del grupo [TH N4]. La figura temporal del Art. 38 (hasta 10 meses al año) no se usa en el GAD y no entra al catálogo.
- Las licencias con remuneración y las vacaciones no son acciones de personal [TH N11, N12]: se registran en Asistencia (9.4).

### 4.2 Clases

- **Casilla**: la del formulario del Ministerio.
- **Efecto**: se detalla en la sección 7.
- **Origen**: «Manual» = se crea desde Acciones de Personal; los demás nombres son el módulo que crea el borrador.

| Familia | Clase (código) | Nombre | Base | Casilla | Efecto | Origen |
|---|---|---|---|---|---|---|
| Ingreso | `ingreso` | Ingreso y vinculación | LOSEP 16–18; Reg. 19 | INGRESO | CREA_VINCULO | Reclutamiento / Manual |
| Ingreso | `nombramiento_definitivo` | Nombramiento definitivo | LOSEP 17 b.5; Reg. 224–227 | NOMBRAMIENTO | CONVIERTE_NOMBRAMIENTO | Período de prueba (9.2) |
| Ingreso | `reintegro` | Reintegro | LOSEP 32 | REINTEGRO | TERMINA_AUSENCIA | Ausencias / Manual |
| Ingreso | `restitucion` | Restitución | LOSEP 46 | RESTITUCIÓN | CREA_VINCULO (con continuidad) | Manual |
| Cambio administrativo | `ascenso` | Ascenso | LOSEP 68; Reg. 190–191 | ASCENSO | REUBICA + RESERVA_PUESTO | Reclutamiento |
| Cambio administrativo | `traslado` | Traslado | LOSEP 35–36 | TRASLADO | REUBICA | Manual |
| Cambio administrativo | `traspaso_puesto` | Traspaso de puesto | LOSEP 37 | TRASPASO | ACTO_SOBRE_PUESTO | Estructura |
| Cambio administrativo | `intercambio_voluntario` | Intercambio voluntario | LOSEP 39–40 | INTERCAMBIO | CIERRA_VINCULO (y el ingreso del otro) | Manual |
| Cambio administrativo | `comision_con_remuneracion` | Comisión de servicios con remuneración | LOSEP 30 | COMISIÓN DE SERVICIOS | AUSENCIA | Manual |
| Cambio administrativo | `comision_sin_remuneracion` | Comisión de servicios sin remuneración | LOSEP 31 | COMISIÓN DE SERVICIOS | AUSENCIA + ECONÓMICO | Manual |
| Licencias | `licencia_sin_remuneracion` | Licencia sin remuneración | LOSEP 28 | LICENCIA | AUSENCIA + ECONÓMICO | Manual / Portal |
| Reemplazo | `subrogacion` | Subrogación | LOSEP 126; Reg. 270 | SUBROGACIÓN | ECONÓMICO + FIRMA | Subrogaciones |
| Reemplazo | `encargo` | Encargo | LOSEP 127; Reg. 271 | ENCARGO | ECONÓMICO + FIRMA | Subrogaciones |
| Puesto y remuneración | `revision_clasificacion` | Revalorización / Reclasificación / Ubicación | LOSEP 61–62 | REVALORIZACIÓN / RECLASIFICACIÓN / UBICACIÓN | ACTO_SOBRE_PUESTO + ECONÓMICO | Estructura (en lote) |
| Disciplinario | `sancion` | Sanción disciplinaria | LOSEP 43; Reg. 87 | OTRO | ECONÓMICO (multa) / AUSENCIA (suspensión) / DOCUMENTAL (amonestación escrita) | **Solo** Disciplinario |
| Cesación | `cesacion` | Cesación de funciones (con causal, 4.3) | LOSEP 47; Reg. 105, 146 | según la causal (RENUNCIA, DESTITUCIÓN, REMOCIÓN, JUBILACIÓN, SUPRESIÓN, OTRO) | CIERRA_VINCULO | Manual / Disciplinario / automática |

**`ingreso`**
- Exige puesto, partida, nombramiento y, si es provisional, su supuesto (b.1 a b.5).
- Quien gana un **concurso** entra con nombramiento **provisional de prueba (b.5)**, no permanente [TH N3, Ley 17 b.5]. El ingreso registra el **acta final del concurso** (número y fecha), que el formulario del Ministerio imprime.
- Detecta solo el **reingreso** (hubo un vínculo cerrado antes) y lo enlaza.
- **Ocasionales**: el ingreso no lleva acción de personal [TH N7, Ley: LOSEP 18 y Reg. 19]. Ver 9.11.
- Ficha médica ocupacional obligatoria [TH 25].

**`nombramiento_definitivo`**
- Convierte el vínculo provisional de prueba en permanente **sin cerrarlo**, así que la antigüedad sigue corriendo.
- El sistema lo prepara en borrador al cumplirse los 3 meses. Si no hubo evaluación, procede igual: la ley dice que «se otorgará el nombramiento definitivo» (LOSEP 17 b.5). Si la evaluación no fue satisfactoria, prepara en cambio la cesación.

**`reintegro`** [TH 17]
- Cierra la ausencia abierta en su fecha real: comisión, licencia sin remuneración o ascenso no superado.
- Prepara en borrador la salida del reemplazo, si lo hay [TH 18].

**`restitucion`** [TH N15]
- Vuelve al servidor a su puesto por sentencia (LOSEP 46). Se enlaza con la destitución o cesación que deja sin efecto, y mantiene la antigüedad continua.
- Datos obligatorios:
  - número de la causa judicial;
  - unidad judicial o tribunal;
  - fecha de la sentencia y de su ejecutoría;
  - puesto al que vuelve;
  - remuneraciones dejadas de percibir, que van a la nómina.
- Si el puesto lo ocupa un provisional (b.1), prepara su remoción.

**`ascenso`** [TH N2]
- Reubica al servidor en el puesto ganado con nombramiento provisional de prueba de hasta 6 meses.
- Su puesto de origen queda **reservado**: no se ocupa definitivamente ni se suprime (Reg. 190–191).
- Si supera la prueba, viene un `nombramiento_definitivo`. Si no la supera, un `reintegro` al puesto anterior con su sueldo.
- Sustituye la cesación + ingreso, que cortaba la antigüedad.

**`traslado`** [TH N1, 9]: la **persona** pasa a otro puesto **vacante** de la misma institución, con **igual remuneración**, sin cambiar de domicilio (Art. 35). Requisitos:
- informe de la UATH;
- que el servidor cumpla el perfil del nuevo puesto (Art. 36);
- la partida pasa a ser la del nuevo puesto.

Reúne lo que hoy son el «Traspaso» y la «Prestación de servicios».

**`traspaso_puesto`** (Art. 37)
- Es un acto sobre el **puesto**: el puesto, con su partida, cambia de unidad.
- Nace en Estructura y emite una acción por cada ocupante, porque cambia su unidad.
- El traspaso a otra entidad exige dictamen del Ministerio de Finanzas (si sube la masa salarial) y aprobación del Ministerio del Trabajo, y cierra el vínculo aquí.

**`intercambio_voluntario`** (Art. 39): entre servidores de instituciones distintas. Reemplaza al «Traslado administrativo» que hoy se registra como movimiento entre instituciones.

**`comision_*`** [TH N5]
- Es servir en **otra entidad del Estado**, así que exige la **institución de destino**.
- **Con remuneración**: servidor de carrera, **1 año** de servicio a la fecha de inicio, **hasta 2 años**. Si es para estudios o eventos, al volver debe servir un tiempo igual al de la comisión.
- **Sin remuneración**: servidor de carrera, 1 año de servicio, **6 años acumulados** en toda la carrera, nunca para puestos del nivel jerárquico superior.

**`licencia_sin_remuneracion`**: siempre con fechas y una causal con su tope (4.4) [TH 16]. Solo para permanentes [TH N9].

**`subrogacion`**
- Solo sobre un puesto **del nivel jerárquico superior** (grupo ocupacional NJS-*) cuyo titular está legalmente ausente.
- Se enlaza con la ausencia que la motiva: vacación, comisión o licencia.
- La ausencia de una jefatura se cubre **siempre** con subrogación o encargo; el sistema impide contratar un reemplazo para ella [TH 19].
- La subrogación del Prefecto por el Viceprefecto no es esta clase: va en el bloque de autoridades electas (9.14).

**`encargo`**: puesto **directivo vacante**. La fecha de fin es opcional: dura «hasta la designación del titular» (Art. 127).

**`sancion`**
- Causal: **amonestación escrita** [TH N13], **sanción pecuniaria** (multa ≤10 %) o **suspensión** sin remuneración (≤30 días, con fechas).
- La amonestación verbal no lleva acción.
- Solo la crea Disciplinario. Las sanciones de obreros van en su bloque (9.15).

### 4.3 Causales de cesación

PERM = permanente · PROV = provisional (incluido el de prueba) · LNR = libre nombramiento y remoción · OCAS = servicios ocasionales.

| Causal | Base | PERM | PROV | LNR | OCAS |
|---|---|---|---|---|---|
| Renuncia | LOSEP 47 a; Reg. 102; Reg. 146 c | ✓ | ✓ | ✓ | ✓ |
| Remoción [TH 8] | LOSEP 47 e; Reg. 105 | | ✓ (fin del supuesto b.1–b.4) | ✓ | |
| No superar el período de prueba | LOSEP 17 b.5 | | ✓ (de prueba) | | |
| Terminación por cumplimiento del plazo [TH 11] | Reg. 146 a | | | | ✓ |
| Terminación unilateral | Reg. 146 f | | | | ✓ ⚠ |
| Mutuo acuerdo | Reg. 146 b | | | | ✓ |
| Supresión del puesto (aplazada, [TH N16]) | LOSEP 47 c, 60 | ✓ | | | |
| Destitución | LOSEP 47 f, 48; Reg. 146 h | ✓ | ✓ | ✓ | ✓ |
| Jubilación | LOSEP 47 j | ✓ | ✓ | ✓ | ✓ |
| Retiro voluntario o compra de renuncia | LOSEP 47 i, k; Mandato 2 | ✓ | | | |
| Incapacidad absoluta y permanente | LOSEP 47 b; Reg. 146 d | ✓ | ✓ | ✓ | ✓ |
| Pérdida de los derechos de ciudadanía | LOSEP 47 d; Reg. 146 e | ✓ | ✓ | ✓ | ✓ |
| Fallecimiento | LOSEP 47 l; Reg. 146 i | ✓ | ✓ | ✓ | ✓ |
| Evaluación regular o insuficiente | Reg. 146 g | | | | ✓ |

Notas:
- ⚠ Una ocasional embarazada o en lactancia no se termina unilateralmente: la Corte Constitucional (sentencia 309-16-SEP-CC) condiciona el Art. 146 para protegerla. Lo mismo vale para los provisionales vulnerables (reforma de la LOSEP de junio de 2025). El sistema avisa cuando conste embarazo o lactancia [TH 11].
- **Dónde nace cada causal:**
  - destitución: **solo** desde Disciplinario;
  - supresión: **solo** desde Estructura;
  - fin del plazo de un ocasional: borrador automático, como ya ocurre con Servicios Profesionales [TH 11].
- Las causales de obreros, profesionales y autoridades están en 9.15, 9.10 y 9.14.

### 4.4 Licencias: causales, topes y dónde se registran

| Licencia | Causal | Tope | Base |
|---|---|---|---|
| Con remuneración | Enfermedad | 3 meses + 3 de rehabilitación | LOSEP 27 a |
| | Discapacidad, enfermedad catastrófica o rara, accidente grave | El tiempo que diga el certificado; no se descuenta de vacaciones | 27 b |
| | Maternidad | 12 semanas (+10 días si es parto múltiple) | 27 c |
| | Paternidad | 15 días (+5 por cesárea o parto múltiple; +8 si es prematuro; 25 días si nace con enfermedad degenerativa o discapacidad severa) | 27 d–e |
| | Fallecimiento de la madre | Lo que reste de la licencia de maternidad | 27 f |
| | Adopción | 30 días | 27 g |
| | Hijos hospitalizados o con patologías degenerativas | 25 días | 27 h |
| | Calamidad doméstica | Hasta 8 días (cónyuge, padres, hijos) o 3 (otros parientes) | 27 i |
| | Matrimonio o unión de hecho | 3 días | 27 j |
| Sin remuneración | Asuntos particulares | 15 días con permiso del jefe; hasta 60 al año con la autoridad | LOSEP 28 a |
| | Estudios de posgrado | Lo que dure el programa; exige 2 años de servicio | 28 b |
| | Servicio militar | Lo que dure | 28 c |
| | Reemplazo de un dignatario electo | Lo que dure | 28 d |
| | Candidatura a elección popular | Desde la inscripción hasta el día siguiente de la elección | 28 e |
| | Cuidado de hijos | Hasta 12 meses, dentro de los primeros 15 meses de vida | 28 f |

Dónde se registra cada una:
- **Con remuneración**: en Asistencia, como **licencia con su causa y su tope**, **sin acción de personal** [TH N12]. Sale de los «motivos» de la solicitud de vacaciones (9.4).
- **Sin remuneración**: acción de personal, solo para permanentes [TH N9].
- **Obreros**: sus licencias del Código del Trabajo (Arts. 42, 152 y 152.1) y las del contrato colectivo, en su bloque (9.15).
- **Autoridades electas**: licencia concedida por el Consejo, en su bloque (9.14).

### 4.5 Del catálogo actual al nuevo

| Hoy (tipo / subtipo) | Pasa a |
|---|---|
| `ingreso` de LOSEP con nombramiento | `ingreso` |
| `ingreso` de un ocasional | Registro del contrato (instrumento `registro_contrato`); su número AP queda en el histórico |
| `ingreso` de un obrero | Contrato de trabajo (instrumento `documento_ct`); su número AP queda en el histórico |
| `ingreso` de servicios profesionales | Contrato civil (instrumento `contrato_civil`) |
| `ingreso` de una autoridad electa | `inicio_periodo` (instrumento `acto_autoridad`) |
| `cambio_administrativo` / `traspaso`; `traspaso` (tipo antiguo); `prestacion_servicios` de LOSEP | `traslado` |
| `prestacion_servicios` de servicios profesionales | Adenda del contrato civil |
| `cambio_administrativo` / `traslado_administrativo`; `traslado` (tipo antiguo) | `intercambio_voluntario`, o «movimiento interinstitucional (histórico)» si los datos no permiten decidir. **Revisar cuántos hay en producción** |
| `cambio_administrativo` / `comision_con_remuneracion`; `comision_servicios` | `comision_con_remuneracion` |
| `cambio_administrativo` / `comision_sin_remuneracion`; `comision_sin_remuneracion` (tipo) | `comision_sin_remuneracion` |
| `licencia_sin_remuneracion` de un permanente | `licencia_sin_remuneracion`, causal «no indicada (histórico)» |
| `licencia_sin_remuneracion` de un obrero | Licencia sin remuneración del bloque de obreros |
| `licencia_sin_remuneracion` de una autoridad electa | `licencia_consejo` (instrumento `acto_autoridad`) |
| `incremento_remuneracion`, `cambio_denominacion` (obreros) | Incremento y cambio de ocupación del bloque de obreros |
| `regimen_disciplinario` / `sancion_disciplinaria` de LOSEP | `sancion`, con la causal tomada de la sanción enlazada |
| `regimen_disciplinario` / `sancion_disciplinaria` de un obrero | Sanción del bloque de obreros |
| `cesacion_funciones` / `renuncia`, `destitucion`, `jubilacion`, `incapacidad`; `destitucion` (tipo) | `cesacion` con esa causal |
| `cesacion_funciones` / `visto_bueno` | Terminación por visto bueno del bloque de obreros |
| `cesacion_funciones` / `contrato_finalizado` | Terminación del contrato civil |
| `subrogacion` | `subrogacion` o `encargo`, según `subrogaciones.tipo` |
| `egreso`, `cambio_regimen`, `cambio_puesto`, `novedad_contrato` | Salen de `movimientos_personal` y van a la bitácora del vínculo (8.5) |

Las acciones ya emitidas a obreros, profesionales, ocasionales y autoridades **conservan su número AP** en el historial. El cambio de instrumento vale para lo que se emita desde el despliegue.

---

## 5. Elegibilidad en la LOSEP

✓ = la ley lo permite y se habilita · ✗ = la ley lo excluye · ✗TH = la ley lo permite, pero TH decidió no habilitarlo · — = no aplica.

| Clase | PERM | PROV | LNR | OCAS |
|---|---|---|---|---|
| Ingreso | ✓ | ✓ (incluido el de prueba) | ✓ | Registro del contrato, sin acción [TH N7] |
| Nombramiento definitivo | — | ✓ (de prueba) | — | — |
| Reintegro | ✓ | ✓ (ascendido que vuelve) | — | — |
| Restitución | ✓ (con sentencia) | — | — | — |
| Ascenso | ✓ | ✗ | ✗ | ✗ |
| Traslado [TH 9] | ✓ | ✓ | ✓ | ✓ |
| Traspaso de puesto (arrastra al ocupante) | ✓ | ✓ | ✓ | ✓ |
| Intercambio voluntario | ✓ | ✗ | ✗ | ✗ |
| Comisión con remuneración | ✓ (carrera, 1 año) | ✗ | ✗ | ✗ |
| Comisión sin remuneración | ✓ (carrera, 1 año, no NJS) | ✗ | ✗ | ✗ |
| Licencia sin remuneración [TH N9] | ✓ | ✗TH | ✗TH | ✗TH |
| Ser subrogante o encargado | ✓ | ✓ | ✓ | Si el contrato lo prevé |
| Revisión de la clasificación | ✓ | ✓ | ✓ | ✓ |
| Sanción | ✓ | ✓ | ✓ | ✓ |
| Cesación | según 4.3 | | | |

Diferencias con lo que hay hoy:
- **Libre nombramiento** deja de tener «Prestación de servicios» y pasa a tener traslado y remoción [TH 8, 9].
- **Provisional, libre nombramiento y ocasional** dejan de tener «Prestación de servicios»: tienen traslado.
- **Obreros, profesionales y autoridades electas** salen de esta matriz: cada uno tiene su bloque (9.15, 9.10, 9.14).

---

## 6. Ciclo de vida, documento y permisos

El ciclo es el mismo para todos los instrumentos. Cambian la numeración, el documento y quién firma (8.1).

### 6.1 Estados

Los estados actuales siguen: `borrador → suscrita → registrada → notificada`, con `anulada` desde cualquiera. Se añaden dos situaciones **derivadas**, que no son estados nuevos:

- **Pendiente de vigencia**: registrada, pero con fecha «Rige a partir de» futura. El efecto todavía no se aplicó.
- **En curso / concluida**: solo para clases temporales (comisión, licencia, suspensión, subrogación, encargo). Se calcula con las fechas y con el reintegro.

### 6.2 Motor de efectos con fecha [TH 10]

- Al **registrar**:
  - si la fecha en que rige es hoy o ya pasó, el efecto se aplica en la misma transacción;
  - si es futura, la acción queda «pendiente de vigencia».
- Un comando diario (`sgth:acciones:aplicar-vigentes`) hace cuatro cosas:
  - aplica los efectos que vencen hoy;
  - cierra las ausencias cuya fecha de fin llegó, y deja un **reintegro en borrador** para que TH lo firme;
  - cierra los períodos de las autoridades electas que terminan (9.14);
  - avisa de lo que vence en 30 días: comisiones, licencias, contratos ocasionales y períodos de prueba.
- **Anular** revierte el efecto solo si ya se aplicó. Se sigue anulando de la última hacia atrás [TH 2026-09-29]. Se puede anular también una acción notificada [TH 23].
- **No se puede anular** una ausencia que tiene un reemplazo vigente [TH 18]. Para terminarla antes se usa el reintegro.

### 6.3 Quién hace qué [TH 22, 24]

Hoy todo va por roles (`admin-uath`, `asistente-uath`). Se crean permisos de Spatie, que la pantalla también consulta:

| Permiso | Para qué | Rol |
|---|---|---|
| `preparar-accion-personal` | crear y editar borradores | asistente-uath, admin-uath |
| `suscribir-accion-personal` | pasar a suscrita (sella firmantes y dictamen presupuestario) | admin-uath |
| `registrar-accion-personal` | pasar a registrada (numera y aplica efectos) | admin-uath |
| `notificar-accion-personal` | notificar | asistente-uath, admin-uath |
| `anular-accion-personal` | anular | admin-uath |

Reglas añadidas:
- **Nadie tramita actos sobre sí mismo** [TH 24]. No vale como guarda en la policy, por el `Gate::before` de admin-ti; va en el servicio.
- El titular solo ve sus actos **registrados, notificados o anulados con número**, nunca los borradores.

### 6.4 Notificación, posesión y plazo para presentarse

- **Notificar** (Reg. 22) [TH 26]:
  - el documento llega al **portal del servidor** y a su **correo institucional**;
  - el servidor deja un **acuse de recibo**;
  - si se le notifica en persona y se niega a recibirlo, se registra la **razón con un testigo** (nombre y cédula).
- **Posesión**: solo en ingreso, ascenso, nombramiento definitivo y encargo. Se registra la fecha.
- **Plazo para presentarse**: si quien es nombrado no se presenta en 3 días desde el registro (5 por distancia), el nombramiento queda sin efecto (Reg. 20). El sistema avisa a TH, que lo anula.

### 6.5 El documento de la acción de personal (formato del Ministerio)

Se mantiene el membrete del GAD y se ordena según el formulario oficial.

**Página 1**
1. N.º (`AP-AAAA-NNNN`), **fecha de emisión** (la de suscripción, no la de vigencia) y casillas Decreto / Acuerdo / Resolución con número y fecha del acto que la origina.
2. Datos del servidor y **«Rige a partir de»**. En las clases temporales, «hasta».
3. **Explicación**: la base legal que pone la clase (editable) más el texto de TH y la referencia (memorando o resolución).
4. **Rejilla de las 23 casillas**, con la de la clase marcada. «Otro: ___» lleva el nombre de la clase (sanción…).
5. **Situación actual / Situación propuesta**:
   - unidad, puesto, grupo, grado, lugar, remuneración y partida;
   - **más la modalidad del vínculo**;
   - en las temporales, «Período» en lugar de propuesta;
   - en comisiones e intercambios, «Institución de destino».
6. **Acta final del concurso**, número y fecha, si la acción viene de un concurso.
7. Firmas selladas de la autoridad nominadora y de Talento Humano. Bloque **«Registro y control»** con número y fecha de registro.

**Página 2**
- Caución.
- «Reemplaza a / en el puesto de / quien cesó por / acción N.º», que se llena con el reemplazo o con el supuesto del provisional.
- **Posesión del cargo**: solo en las clases que la llevan.
- **Notificación**: «Recibí», o razón de negativa con un testigo.

Además:
- **Vista previa** en borrador y suscrita: PDF con la marca «BORRADOR» y sin número [TH 27].
- **Firma electrónica**: queda fuera de este diseño; el documento la admitiría después.

### 6.6 Firmantes [TH 20, 21, N14]

Se mantiene la derivación del organigrama [TH 2026-07-28], con estos ajustes:
- El titular se resuelve **en la fecha de suscripción**, no «hoy» (`FirmanteOrganigramaService::titularDe`), y se excluyen los reemplazos (hallazgo del 2026-10-05).
- **No hay delegación de firma.** Si falta un firmante porque el puesto está vacante, la suscripción **se bloquea**. Si el firmante está ausente y nadie lo subroga, **se espera** a que vuelva.
- Mientras el Viceprefecto subroga al Prefecto (9.14), él firma como autoridad nominadora, con «(S)».

---

## 7. Efectos sobre el vínculo

| Efecto | Qué cambia | Al anular | Clases |
|---|---|---|---|
| CREA_VINCULO | Crea el vínculo; ocupa la plaza (salvo los vínculos que no la ocupan); persona activa; la antigüedad **no** se reinicia (8.3) | Borra el vínculo (borrado lógico) y deja a la persona como estaba | ingreso, restitución; ingreso de obreros, profesionales y autoridades |
| CONVIERTE_NOMBRAMIENTO | Cambia el tipo de nombramiento del vínculo vigente (provisional de prueba → permanente) y fija la fecha de nombramiento | Vuelve al provisional | nombramiento definitivo |
| REUBICA | Cambia puesto, unidad, partida y, si corresponde, remuneración del vínculo. Valida plaza, igual remuneración (traslado) y que el puesto pertenezca a la unidad | Vuelve al origen congelado **revalidando la plaza** | traslado, ascenso; cambio de ocupación y de lugar de obreros |
| RESERVA_PUESTO | El puesto de origen del ascendido queda reservado: no cuenta como vacante para concurso ni puede suprimirse | Libera la reserva | ascenso |
| AUSENCIA | Ausencia entre fechas; habilita el reemplazo; bloquea vacaciones y permisos en ese período; avisa a Asistencia | Quita la ausencia. **Bloqueado** si hay un reemplazo vigente | comisiones, licencia sin remuneración, suspensión; licencias de obreros y autoridades |
| TERMINA_AUSENCIA | Fija la fecha real de fin de la ausencia; deja en borrador la salida del reemplazo | Reabre la ausencia | reintegro |
| MODIFICA_REMUNERACION | Escribe la nueva remuneración **en el vínculo**; nunca menor que la anterior | Restaura la anterior | revisión de clasificación; incremento de obreros |
| ACTO_SOBRE_PUESTO | Cambia el puesto (unidad, grupo, denominación o estado) y emite un acto por ocupante | Revierte el puesto y anula el lote | traspaso de puesto, revisión de clasificación |
| ECONÓMICO | Genera una **novedad de nómina**: diferencia de subrogación o encargo, multa, días sin sueldo, remuneraciones de una restitución | Anula la novedad | subrogación, encargo, sanción, licencia y comisión sin remuneración, restitución; subrogación del Viceprefecto; multa de obreros |
| FIRMA | El subrogante o encargado firma por el titular mientras dure | Termina la firma | subrogación, encargo, subrogación del Viceprefecto |
| CIERRA_VINCULO | Cierra el vínculo en la fecha en que rige; libera la plaza. Si no queda otro vínculo vigente: persona inactiva, sus subrogaciones cerradas y **desvinculación** abierta (9.9) | Reabre el vínculo **revalidando la plaza** y reactiva a la persona | cesación, intercambio (salida); terminación de obreros, profesionales y autoridades |
| DOCUMENTAL | Nada sobre el vínculo | — | amonestación escrita; amonestación de obreros |

---

## 8. Modelo de datos

### 8.1 `movimientos_personal`: el registro de actos

La tabla guarda **todos** los actos que cambian la situación de una persona. Lo que distingue un acto de otro es su **instrumento**:

| Instrumento | Régimen | Numeración | Documento | Firman |
|---|---|---|---|---|
| `accion_personal` | LOSEP con nombramiento, y ocasionales salvo el ingreso | `AP-AAAA-NNNN` | Formato del Ministerio (6.5) | Autoridad nominadora y Talento Humano |
| `registro_contrato` | Ingreso de ocasionales | Número del contrato | Ninguno: el contrato se firma aparte y aquí se registra | — |
| `documento_ct` | Obreros | Contrato y adendas: número del contrato. Hoja de registro interno: correlativo propio [Pendiente] | Contrato, adenda, registro interno, liquidación | Empleador y trabajador; el registro interno, Talento Humano |
| `contrato_civil` | Servicios profesionales | Número del contrato | Contrato, adenda, terminación | Las partes del contrato |
| `acto_autoridad` | Autoridades electas | Sin número; constancia opcional con número propio | El documento de origen (credencial, resolución del Consejo) | — |

Así hay un solo historial, un solo motor de efectos y una sola bandeja, filtrable por instrumento. El correlativo de acciones de personal solo cuenta acciones de personal.

**Columnas nuevas**

| Columna | Tipo | Para qué |
|---|---|---|
| `instrumento` | enum (los cinco de arriba) | Decide la numeración, el documento y quién firma |
| `clase` | enum string | La clase (4.2, 9.10, 9.14 o 9.15). Sustituye a `tipo_movimiento` y `subtipo_movimiento` |
| `causal` | string, validada por clase | Causal de cesación o terminación, licencia o sanción, o supuesto del provisional |
| `base_legal` | text | La pone la clase por defecto; TH la edita en borrador |
| `acto_tipo`, `acto_numero`, `acto_fecha` | enum (decreto, acuerdo, resolución, memorando, credencial CNE, acta de posesión, resolución del Consejo, oficio, sentencia) + string + date | Lo que origina el acto. Hoy solo existe `resolucion_numero`, sin fecha |
| `institucion_destino` | string | Comisiones e intercambio |
| `fecha_fin_real` | date | Fin efectivo de una ausencia (lo fija el reintegro) |
| `movimiento_relacionado_id` | FK a sí misma | Reintegro → ausencia; nombramiento definitivo → ingreso; restitución → destitución; reemplazo → ausencia. Junta `movimiento_previo_id` y `cubre_movimiento_id` en un solo enlace con su rol |
| `acta_concurso_numero`, `acta_concurso_fecha` | string + date | Ingreso y ascenso por concurso |
| `causa_judicial_numero`, `unidad_judicial`, `fecha_sentencia`, `fecha_ejecutoria` | string, string, date, date | Restitución [TH N15]; reintegro de obreros por despido ineficaz |
| `efecto_aplicado_en` | timestamp | Si es nulo y está registrada, el acto está pendiente de vigencia |
| `notificacion_medio`, `notificacion_testigo_nombre`, `notificacion_testigo_cedula`, `acuse_en`, `correo_enviado_en` | | Reg. 22 [TH 26] |
| `lote_id` | FK a `lotes_accion` | Actos en lote |
| `origen` | enum (manual, disciplinario, reclutamiento, subrogaciones, asistencia, estructura, automatico, portal) | Impide crear a mano las clases que tienen procedimiento previo |

**Retirar después de migrar**
- `tipo_movimiento` y `subtipo_movimiento`: se conservan unos meses, solo de lectura.
- `categoria`: siempre vale lo mismo.
- `codigo`: es texto libre que nadie usa.
- `documento_respaldo`: pasa a la tabla de adjuntos.

**Inmutabilidad**: desde que un acto está registrado no cambia **ningún** campo de contenido. Las notas posteriores van a una tabla de anotaciones, por ejemplo la impugnación del visto bueno.

### 8.2 `contratos_servidor` (el vínculo)

| Columna | Para qué |
|---|---|
| `causal_provisional` (b1–b5) | Por qué es provisional; decide cuándo se le remueve (Reg. 105.1) |
| `fin_periodo_prueba` | 3 meses en el ingreso por concurso y 6 en el ascenso (LOSEP); hasta 90 días en obreros (CT 15) |
| `puesto_reservado_id` | El puesto de origen del ascendido |
| `tipo_contrato_ct` | Obreros: indefinido, eventual, ocasional, de obra cierta, de temporada… (CT 11) |
| `contrato_colectivo_id` | Obreros amparados por el contrato colectivo vigente |
| `periodo_inicio`, `periodo_fin` | Autoridades electas: el período para el que fueron elegidas; `periodo_fin` cierra el vínculo solo |

### 8.3 `servidores`

- **`estado`** pasa a significar «tiene un vínculo vigente hoy». Lo mantiene el motor de efectos, y un comando nocturno lo concilia.
- **`fecha_ingreso_institucion`** deja de pisarse en cada ingreso.
- **Antigüedad** [TH 12, 13]: se calcula con la historia de vínculos.
  - Si un vínculo empieza el día siguiente al cierre del anterior, es servicio continuo y se conserva la fecha original.
  - Si hubo tiempo fuera, la antigüedad cuenta desde el reingreso.

  Es la que usan la comisión de servicios y las vacaciones de los obreros.
- `regimen_laboral` y `fecha_ingreso_institucion` dejan de ser editables en la ficha. Corregirlos queda reservado a un permiso de corrección auditada.

### 8.4 `puestos`

- `es_nivel_jerarquico_superior`: derivado del grupo ocupacional (los NJS-1 a NJS-10 ya existen). Lo usan la subrogación y la comisión sin remuneración.
- `es_jefe` ya existe: con él se impide contratar un reemplazo para una jefatura [TH 19].
- Historial de clasificación (grupo, denominación, unidad) con el acto o el lote que lo cambió.
- `plazas` nunca puede quedar por debajo de las ocupadas.
- Supresión aplazada [TH N16]: por ahora solo se impide borrar un puesto ocupado o con historia.

### 8.5 Tablas nuevas

| Tabla | Para qué |
|---|---|
| `eventos_vinculo` | Bitácora del expediente: novedad de contrato, reprogramación de plazo, carga inicial, correcciones. Saca la bitácora de `movimientos_personal` |
| `lotes_accion` | Actos en lote: revisión de clasificación, incremento de obreros, traspaso de puestos |
| `novedades_nomina` | Lo que la nómina debe aplicar en un mes: ingreso o salida a mitad de mes, diferencias, multas, días sin sueldo, remuneraciones de una restitución |
| `desvinculaciones` | Lista de pasos tras una cesación o terminación (9.9) |
| `adjuntos_accion` | Respaldos: renuncia firmada, sentencia, certificado, resolución, credencial |
| `contratos_colectivos` | El contrato colectivo vigente y sus cláusulas parametrizadas (9.15) |
| `faltas_reglamento_interno` | El catálogo de faltas y sanciones del reglamento interno de obreros (9.15) |
| `licencias` (en Asistencia) | Licencias con remuneración, con causa, tope y certificado, sin acción [TH N12] |

### 8.6 Migración del histórico

1. Agregar `instrumento`, `clase` y `causal` y llenarlos según la tabla 4.5 en una migración que **no borra nada**. Las acciones ya emitidas conservan su número AP.
2. Antes de correrla en producción: contar las filas de cada tipo antiguo y revisar a mano las de `traslado_administrativo`.
3. Pasar las filas de bitácora a `eventos_vinculo`.
4. Pasar los «motivos» de licencia de las solicitudes de vacaciones a `licencias`.
5. Calcular `efecto_aplicado_en` = `fecha_registro` en todo lo ya registrado.
6. Recalcular `servidores.estado` y dejar un informe de quién cambia de estado. Hoy hay cesados que aparecen como activos.
7. Ensayar en una copia de la base. Según la memoria del proyecto, los cambios de estado persistido se verifican con datos de prueba, no con la sesión real.

---

## 9. Integración con los demás módulos y bloques por régimen

### 9.1 Reclutamiento
- **Concurso → nombramiento provisional de prueba (b.5)** [TH N3]. El ingreso registra el **acta final del concurso**.
- **Ganador interno → `ascenso`**, no cesación + ingreso [TH N2].
- **Express**: según la modalidad prevista.
  - Provisional: ingreso con acción.
  - Ocasional: registro del contrato (9.11).
  - Obrero: contrato de trabajo (9.15).
  - Profesional: contrato civil (9.10).
- La persona no queda «activa» con puesto antes de que el ingreso se registre (`SolicitudCertificacionController.php:557-573`).
- Ficha médica obligatoria en todo ingreso con relación de dependencia; opcional en Servicios Profesionales [TH 25].

### 9.2 Período de prueba (nuevo)
- Pantalla en Expediente: personas en prueba, con su fecha de fin y los días restantes.
  - Provisionales de prueba: 3 meses.
  - Ascendidos: hasta 6 meses.
  - Obreros: hasta 90 días (CT 15).
- Al vencer, el sistema prepara en borrador:
  - el `nombramiento_definitivo`, también si no hubo evaluación (LOSEP 17 b.5);
  - si la evaluación (9.3) fue regular o insuficiente: la cesación por no superar la prueba, o el `reintegro` al puesto reservado en el caso del ascenso;
  - para obreros que no superan la prueba: la terminación por período de prueba.

### 9.3 Evaluación del desempeño
- Agregar la «evaluación del período de prueba», que alimenta 9.2.
- Quitar la inserción directa en `sumarios` (`EvaluacionService.php:69-76, 107-120`). Debe pasar por `DisciplinarioService` o ser una causal de terminación del ocasional (Reg. 146 g).

### 9.4 Asistencia: vacaciones, licencias, permisos, certificados y Sirha7
- **Vacaciones**: siguen como hoy, con su solicitud y sin acción de personal [TH N11].
- **Licencias** [TH N12]:
  - **salen de los «motivos» de la solicitud de vacaciones** (maternidad, paternidad, matrimonio, calamidad, enfermedad, licencia sin goce, estudios, capacitación);
  - se registran como **licencias**, con su causa, su tope legal (4.4) y su certificado, **sin acción de personal**;
  - la licencia sin remuneración de un permanente es acción de personal (4.2), y la de un obrero va en su bloque;
  - la «capacitación» es comisión de servicios.
- Una licencia registrada:
  - bloquea vacaciones y permisos en su período;
  - se escribe en Sirha7 como ausencia justificada, igual que los certificados médicos.
- **Certificado médico de reposo largo**: se propone que genere en borrador una licencia por enfermedad, sin acción [Pendiente].
- Los períodos de vacaciones se generan solo para personas con vínculo vigente, lo que arregla el efecto del `estado` que nunca se apagaba.
- Al cesar se liquidan las vacaciones no gozadas (LOSEP 29; CT para obreros). Eso va a la desvinculación (9.9).

### 9.5 Subrogaciones y encargos
- Dos clases separadas, `subrogacion` y `encargo`.
- La subrogación valida que el puesto sea del **nivel jerárquico superior**. El encargo valida un puesto **directivo vacante** y admite que no tenga fecha de fin.
- Se enlaza con la ausencia del titular: vacación, comisión, licencia o cesación.
- Una jefatura ausente o vacante se cubre solo así: no admite reemplazo contratado [TH 19].
- La subrogación del Prefecto por el Viceprefecto **no** pasa por este módulo (9.14).
- La diferencia de remuneración va a `novedades_nomina`.
- Cuando el subrogante o el titular cesan, la subrogación se cierra sola.

### 9.6 Régimen disciplinario
- La sanción y la destitución salen del formulario manual: **solo** las crea Disciplinario, con su clase, su porcentaje o sus días, y las fechas de la suspensión.
- **Amonestación escrita** con acción de personal [TH N13]. La verbal queda solo en Disciplinario.
- **Anular la acción** devuelve la sanción a un estado coherente; hoy queda «resuelta» aunque la acción esté anulada.
- La **suspensión**:
  - es ausencia sin sueldo (bloquea la asistencia);
  - genera una novedad de nómina.
- **Obreros**: las faltas y sanciones salen del reglamento interno (9.15), sin acción de personal. Ya no hay suspensión [TH 2026-10-04, CT 44 i].
- La impugnación del visto bueno se guarda como anotación y no reescribe el acto registrado.

### 9.7 Estructura
- Si un puesto **ocupado** cambia de unidad, de grupo ocupacional (sueldo) o de denominación, el cambio se hace con un traspaso de puesto o una revisión de clasificación, que emiten un lote de actos. La edición directa se bloquea.
- **Supresión de puestos**: aplazada; no se prevé por ahora [TH N16]. Mientras tanto, `eliminarPuesto` queda solo para puestos sin ocupantes ni historia. Si algún día se necesita, se diseña según el Art. 60: informe técnico, protecciones, indemnización y una partida que no se recrea en 2 años.

### 9.8 Nómina
- La nómina toma la remuneración **del vínculo** (`contratos_servidor.remuneracion`), no la del puesto.
- Consume `novedades_nomina`.
- Es un cambio de alto riesgo: se propone **una corrida en paralelo** de un mes antes de cambiar.

### 9.9 Desvinculación (nuevo)
Al registrarse una cesación o terminación se abre una lista de pasos con responsable y estado:

1. Liquidación de vacaciones no gozadas y de haberes. En obreros, además, la liquidación del Código del Trabajo y el acta de finiquito (9.15).
2. Indemnización o bonificación, dentro de los topes del Mandato 2 (7 SBU por año, máximo 210) y, en obreros, del contrato colectivo.
3. Devolución de bienes: acta de Inventario TI.
4. Baja del usuario (`UsuarioService::desvincularServidor`).
5. Certificado laboral.
6. Declaración patrimonial de fin de gestión.
7. Marca de **prohibición de reingreso** si cobró indemnización (Mandato 2, art. 8).

### 9.10 Servicios Profesionales: contrato civil [TH N6]
Es un contrato civil sin relación de dependencia (Reg. 148): no es servidor ni recibe acciones de personal.
- **Instrumento** `contrato_civil`: el contrato, sus **adendas** (cambio de unidad, lo que hoy es «Prestación de servicios») y su **terminación**, sin número de acción.
- Se mantiene el borrador automático al vencer, ahora como «terminación del contrato». Sigue valiendo la regla del año calendario [TH 2026-07-27].
- Ficha médica opcional [TH 25]. No marca asistencia ni tiene vacaciones ni permisos (ya es así hoy).
- Puede cubrir la ausencia de un servidor como reemplazo [TH N10].

### 9.11 Servicios ocasionales
- **Ingreso sin acción de personal** [TH N7; LOSEP 18, Reg. 19]: instrumento `registro_contrato`, con número de contrato, plazo, puesto, partida y remuneración. Queda en el historial; no se imprime acción.
- La **prórroga del plazo** sigue como edición auditada, sin documento [TH 2026-08-14].
- Los demás actos llevan acción de personal: traslado, sanción, cesación. El **fin del plazo** prepara la cesación en borrador [TH 11].
- No tienen licencia sin remuneración [TH N9]. Pueden cubrir la ausencia de un servidor como reemplazo [TH N10].

### 9.12 Reporte SIITH / SUT
- Se configura por **clase**, no por tipo.
- Hoy solo reporta ingreso, traslado (antiguo) y cambio administrativo: faltan cesación, subrogación, licencias, sanciones e incrementos. Los obreros se reportan desde su bloque.
- Agregar la pantalla de exportación; hoy no existe.

### 9.13 Portal del servidor
- **«Mis documentos laborales»**:
  - acciones de personal, adendas y registros internos, ya registrados y notificados;
  - descarga del PDF y **acuse de recibo**;
  - el mismo aviso llega a su correo institucional [TH 26].
- **Solicitudes** que crean un borrador en la bandeja de TH:
  - renuncia (15 días de anticipación; si no se responde, se tiene por aceptada, Reg. 102);
  - licencia sin remuneración (permanentes);
  - jubilación.

### 9.14 Autoridades electas [TH 1–7]

**Por qué es un bloque aparte.** El Prefecto y el Viceprefecto son servidores públicos (Constitución, Art. 229): siguen en el expediente, en la nómina, en la declaración patrimonial y como firmantes. Pero ninguno de sus actos nace de una autoridad nominadora:

| Acto | Servidor LOSEP | Prefecto o Viceprefecto |
|---|---|---|
| Ingreso | Nombramiento registrado en acción de personal (Reg. Art. 19) | Elección, credencial del CNE y posesión, sin concurso (Constitución, Art. 228) |
| Firma | «La autoridad nominadora o su delegado» (Reg. Art. 21) | El Prefecto *es* la autoridad nominadora: firmaría su propio acto |
| Licencias | Las concede la UATH o la autoridad (LOSEP, Arts. 27–28) | Las concede el Consejo Provincial, hasta 60 días acumulados, prorrogables por enfermedad catastrófica o calamidad doméstica (COOTAD, Art. 47, literal s) |
| Reemplazo | Subrogación por orden escrita (LOSEP, Art. 126) | Por ley: el Viceprefecto subroga si la ausencia temporal pasa de 3 días y cobra la remuneración de la primera autoridad; si es definitiva, asume hasta terminar el período (COOTAD, Arts. 51 y 52.1) |
| Salida | Cesación del Art. 47 de la LOSEP | Fin del período, revocatoria del mandato (Constitución, Art. 105), remoción por el Consejo (COOTAD, Arts. 333–336), renuncia, fallecimiento |
| Carrera y disciplina | Sí | No (LOSEP, Art. 83; régimen del COOTAD) |

En la nómina solo hay estas dos dignidades [TH 7].

**Actos del bloque** (instrumento `acto_autoridad`, sin número de acción):

| Acto (clase) | Documento de origen | Efecto |
|---|---|---|
| Inicio del período (`inicio_periodo`) | Credencial del CNE, **obligatoria**; acta de posesión, **opcional** [TH 2]. Rige desde la posesión | CREA_VINCULO con `periodo_inicio` y `periodo_fin` fijados desde el inicio |
| Licencia concedida por el Consejo (`licencia_consejo`) | Resolución del Consejo Provincial [TH 3] | AUSENCIA; controla los 60 días acumulados |
| Vacaciones (`vacaciones_autoridad`) | Resolución del Consejo Provincial [TH 3] | AUSENCIA |
| Subrogación por ley (`subrogacion_ley`) | Oficio que comunica la ausencia, o resolución del Consejo [TH 6] | ECONÓMICO (el Viceprefecto cobra la remuneración del Prefecto) + FIRMA («(S)») mientras dure. Solo si la ausencia pasa de 3 días |
| Asunción por ausencia definitiva (`asuncion_definitiva`) | Resolución del Consejo | El Viceprefecto pasa a Prefecto hasta terminar el período |
| Fin del período (`fin_periodo`) [TH 1] | Ninguno: lo marca la fecha | CIERRA_VINCULO automático en `periodo_fin` |
| Revocatoria, remoción, renuncia, fallecimiento | Resultado del CNE, resolución del Consejo, renuncia aceptada, partida de defunción | CIERRA_VINCULO |

**Reglas propias**
- El período se cierra solo en su fecha [TH 1].
- La reelección abre un período nuevo el mismo día, sin cortar la antigüedad ni la nómina [TH 4].
- Los puestos de Prefecto y Viceprefecto no pasan por el control de plazas vacantes: el saliente y el entrante pueden coincidir el día del cambio.
- No pasan por la ficha médica ocupacional [TH 5]. No marcan asistencia ni reciben sanciones de la LOSEP.
- La constancia impresa es opcional y lleva su propia numeración [TH 6].
- Los actos del Prefecto sobre **otros** servidores siguen siendo acciones de personal: él las firma como autoridad nominadora.

**Pantalla**: una pestaña «Autoridades electas» en Expediente, con el período vigente de cada autoridad, sus actos y la subrogación en curso. Los actos aparecen también en la pestaña Laboral de la persona.

### 9.15 Obreros (Código del Trabajo) [TH 2026-10-09]

**Por qué es un bloque aparte.**
- La Constitución sujeta a los obreros del sector público al Código del Trabajo (Art. 229, inciso 3; Art. 326.16). La acción de personal es un instrumento del Reglamento a la LOSEP (Art. 21), y ninguna norma la exige para ellos.
- Las figuras de la LOSEP no existen para ellos:
  - no tienen traslado, traspaso, ascenso, subrogación ni encargo;
  - la comisión de servicios solo existe para becas (CT 42.27);
  - cambiarlos de ocupación sin su consentimiento equivale a despido intempestivo (CT 192).
- El Código del Trabajo tiene sus propios instrumentos: contrato, adenda, reglamento interno, visto bueno, liquidación y contrato colectivo.

**Documentos** (instrumento `documento_ct`)

| Documento | Para qué | Firman | Número |
|---|---|---|---|
| Contrato individual de trabajo | Ingreso: tipo de contrato, puesto, remuneración, jornada, período de prueba | El empleador y el trabajador [Pendiente: quién firma por el empleador] | Número de contrato |
| Adenda | Todo cambio de condiciones: ocupación, lugar, jornada, remuneración individual | Las mismas partes | «Adenda n.º k» del contrato |
| Hoja de registro interno [TH N8] | Acompaña a **cada** acto del bloque. Deja en el expediente la situación actual y la propuesta, la base legal y el documento de origen | Talento Humano | Correlativo propio, distinto del de acciones, por ejemplo `RI-AAAA-NNNN` [Pendiente: confirmar formato] |
| Liquidación y acta de finiquito | Terminación | Empleador y trabajador | — |

**Actos del bloque**

| Acto | Documentos | Efecto | Base |
|---|---|---|---|
| Ingreso | Contrato + registro interno | CREA_VINCULO; período de prueba de hasta 90 días, una sola vez | CT 11, 14, 15 |
| Cambio de ocupación [TH 15] | Adenda con consentimiento escrito + registro interno | REUBICA hacia otro puesto del catálogo de obreros; el salario puede subir, nunca bajar | CT 192 |
| Cambio de lugar o de jornada | Adenda + registro interno | REUBICA (unidad o lugar) | CT 192 |
| Incremento de remuneración [TH 14] | Individual: adenda + registro. Colectivo (tabla del Ministerio o contrato colectivo): un lote con un registro por trabajador | MODIFICA_REMUNERACION, sin disminución | CT 118; contrato colectivo |
| Licencia sin remuneración [TH N9] | Registro interno | AUSENCIA + ECONÓMICO | CT 152.1; contrato colectivo |
| Sanción | Resolución de Disciplinario + registro interno; aviso a Financiero en las multas | ECONÓMICO (multa ≤10 %) o DOCUMENTAL (amonestación). Nunca suspensión | CT 44, 64; reglamento interno |
| Visto bueno | Resolución del Inspector del Trabajo (ya en Disciplinario) | Lleva a la terminación | CT 172, 183 |
| Terminación | Causal + liquidación + acta de finiquito + registro interno | CIERRA_VINCULO + desvinculación | CT 169, 184–188, 216 |
| Reintegro por despido ineficaz | Sentencia (con los datos judiciales de 8.1) | Reabre el vínculo | CT 195.1–195.3 |

Las licencias con remuneración y los permisos del Código del Trabajo (Arts. 42 y 152) y las vacaciones (Art. 69, más lo que añada el contrato colectivo) siguen en Asistencia, sin documento del bloque.

**Causales de terminación**

| Causal | Base | Qué se paga |
|---|---|---|
| Renuncia (desahucio del trabajador) | CT 184–185 | Aviso de 15 días; bonificación del 25 % de la última remuneración por año de servicio |
| Acuerdo de partes | CT 169.2, 185 | La misma bonificación del 25 % por año |
| Conclusión de la obra, temporada o servicio | CT 169.3 | Liquidación ordinaria |
| No superar el período de prueba | CT 15 | Liquidación ordinaria |
| Visto bueno del empleador | CT 172, 183 | Liquidación ordinaria; nace en Disciplinario |
| Despido intempestivo | CT 188, 185; Mandato 4 | Indemnización del Art. 188 + bonificación del 185; tope de 300 SBU |
| Jubilación | CT 216; Mandato 2; contrato colectivo | Jubilación patronal con 25 años (los GAD la regulan por ordenanza, regla 2); bonificación dentro de los topes del Mandato 2 |
| Retiro voluntario o compra de renuncia | Mandato 2, art. 8 | Hasta 7 SBU por año, máximo 210; prohibición de reingreso |
| Muerte o incapacidad permanente | CT 169.5 | Liquidación a los herederos o al trabajador |
| Caso fortuito o fuerza mayor | CT 169.6 | Liquidación ordinaria |

**Contrato colectivo y reglamento interno** (TH confirmó que existen los dos)
- **Contrato colectivo vigente**. El sistema guarda:
  - su número, vigencia, fecha de registro y dictamen del Ministerio de Finanzas;
  - las cláusulas que cambian cálculos: días adicionales de vacaciones, licencias adicionales, bonificaciones por jubilación o renuncia e incrementos pactados.

  Esas cláusulas valen dentro de los límites de la ley:
  - el CT 224 prohíbe ciertas cláusulas;
  - el Mandato 2 fija los topes;
  - sin dictamen del Ministerio de Finanzas, el contrato se tiene por inexistente (Acuerdo MDT-2024-080, reformado por el MDT-2025-056).

  **[Pendiente: copia del contrato colectivo]**
- **Reglamento interno aprobado**: es el catálogo de faltas y su sanción (amonestación o multa con su porcentaje). Disciplinario solo ofrece a los obreros lo que está en ese catálogo, porque sin él la ley no permite multar (CT 44 a). **[Pendiente: copia del reglamento interno]**

**Reglas**
- Sin consentimiento escrito del trabajador no hay cambio de ocupación.
- La remuneración nunca baja.
- No hay suspensión como sanción.
- Ficha médica obligatoria al ingreso [TH 25].
- La antigüedad sigue la regla de 8.3. Es la que da los días adicionales de vacaciones (CT 69).
- Los puestos de obreros conservan su propio catálogo y sus plazas, como hoy.
- El aviso a Financiero por una multa (#344) se mantiene, con el registro interno como adjunto en lugar de la acción.

**Qué cambia respecto de hoy**
- Lo que hoy reciben los obreros como acción de personal pasa a este bloque: incremento, cambio de denominación, sanción, visto bueno y licencia sin remuneración. Lo ya emitido conserva su número AP en el historial.
- Los obreros salen de la matriz de elegibilidad de la LOSEP (sección 5).

**Pantalla**
- Desde el expediente de un obrero, «Nuevo documento laboral» ofrece solo los actos de este bloque.
- La bandeja muestra todos los actos, con filtro por instrumento.
- Una pantalla de configuración guarda el contrato colectivo vigente y el catálogo de faltas del reglamento interno.

---

## 10. Módulos: agregar, quitar, fusionar

| Acción | Qué | Por qué |
|---|---|---|
| Agregar | **Obreros (Código del Trabajo)** (9.15): contrato, adenda, registro interno, terminación con liquidación, contrato colectivo y reglamento interno | Su régimen no es la LOSEP [TH] |
| Agregar | **Autoridades electas** (9.14) | Su ingreso, licencias, subrogación y salida no son acciones de personal; hoy el Prefecto saliente no se puede desvincular [TH] |
| Agregar | **Contrato civil** de servicios profesionales (9.10) | No son servidores [TH N6] |
| Agregar | **Catálogo de actos** en el backend: endpoint del catálogo y de los actos disponibles para cada persona, con el motivo de los no disponibles | Una sola fuente de reglas; el frontend deja de copiarlas |
| Agregar | **Pantalla «Reglas de las acciones»**, de solo lectura | Que TH vea y valide la matriz sin leer código |
| Agregar | **Período de prueba** (9.2) | LOSEP 17 b.5 [TH N3], ascenso [TH N2], CT 15 |
| Agregar | **Restitución** con datos judiciales | [TH N15] |
| Agregar | **Ausencias y reintegros**, ampliando el panel actual | Todas las ausencias en un sitio: vencimientos, reintegros y reemplazos |
| Agregar | **Licencias** en Asistencia, fuera de las vacaciones | Con causa y tope, sin acción [TH N12] |
| Agregar | **Actos en lote** | Incremento de obreros, revisión de clasificación, traspaso de puestos |
| Agregar | **Desvinculación** (9.9) | Lo que pasa después de una cesación o terminación |
| Agregar | **Mis documentos laborales** y solicitudes en el portal, más el aviso al correo institucional | Notificación con constancia (Reg. 22) [TH 26] |
| Agregar | **Pantalla del reporte SIITH** | El endpoint existe y no se usa |
| Quitar | Tipos de bitácora y tipos antiguos como acciones | Se crean sin efecto; van a `eventos_vinculo` o a la migración |
| Quitar | Licencias como «motivos» de vacaciones | Están duplicadas y no tienen topes [TH N12] |
| Quitar | Sanción y destitución en el formulario manual | Saltan el sumario y el visto bueno |
| Quitar | Edición directa en Estructura de un puesto ocupado (unidad, grupo, denominación, borrado) | Cambia la situación del servidor sin acto |
| Quitar | Edición libre de `regimen_laboral` y `fecha_ingreso_institucion` en la ficha | Lo mismo |
| Quitar | Acción de personal en el ingreso de ocasionales | La ley no la exige [TH N7] |
| Fusionar | «Traspaso» + «Prestación de servicios» → **Traslado** (Art. 35) | Son la misma figura legal [TH N1, 9] |
| Separar | Encargo como clase propia | Hoy se guarda como subrogación |
| No hacer | Cambio administrativo temporal (Art. 38) | No se usa [TH N4] |
| Aplazar | Supresión de puestos | No se prevé [TH N16] |

---

## 11. Decisiones de Talento Humano

### 11.1 Respuestas del 9 de octubre

TH contestó el cuestionario (`docs/Preguntas_TH_Acciones_de_Personal.docx`) y, el mismo día, las aclaraciones por separado.

| Tema | Preguntas | Decisión |
|---|---|---|
| Autoridades electas | 1–7 | Bloque propio sin acción. El período se cierra solo. Credencial del CNE obligatoria y acta de posesión opcional. Licencias y vacaciones por resolución del Consejo. Reelección sin corte. Sin ficha médica. Subrogación del Viceprefecto registrada, con constancia opcional. No hay otras dignidades |
| Libre nombramiento | 8, 9 | Remoción; la prestación de servicios pasa a traslado |
| Cesación | 10, 11 | El efecto se aplica en la fecha en que rige; borrador automático al vencer un ocasional |
| Antigüedad | 12, 13 | Continua si no hay hueco; con hueco, desde el reingreso |
| Obreros | 14, 15, N8 | Incremento con monto, en lote y sin disminución. Cambio de ocupación con consentimiento y adenda. Hoja de registro interno además de la adenda. Bloque propio. Hay contrato colectivo y reglamento interno aprobado |
| Licencias y ausencias | 16–19, N9, N10, N12 | Licencia sin remuneración con fechas y topes, solo permanentes. Reintegro. No se anula con reemplazo vigente. Jefaturas solo por subrogación o encargo. Reemplazos solo ocasionales o profesionales. Licencias con remuneración sin acción, fuera de las vacaciones |
| Firmas y trámite | 20–24, 26, 27, N14 | Sin firmante se bloquea; si nadie subroga, se espera; sin delegados. Asistente prepara y notifica; Director suscribe, registra y anula. Se puede anular una acción notificada. Nadie tramita sobre sí mismo. Notificación por portal y correo institucional. Vista previa |
| Ficha médica | 25 | Obligatoria con relación de dependencia; opcional en Servicios Profesionales |
| Nombres y figuras | N1, N2, N3, N4, N5 | Nombres legales. Ascenso con prueba. Concurso → provisional de prueba. Sin cambio administrativo temporal. Comisiones con la regla legal |
| Modalidades | N6, N7 | Servicios Profesionales fuera de las acciones. Ocasional sin acción en el ingreso |
| Vacaciones y sanciones | N11, N13 | Vacaciones sin acción. Amonestación escrita con acción |
| Casos especiales | N15, N16 | Restitución con datos judiciales. Sin supresiones por ahora |

### 11.2 Pendientes

| Pendiente | Bloquea |
|---|---|
| Copia del **contrato colectivo** vigente | Fase 4.6 (sus cláusulas en vacaciones, licencias, jubilación e indemnizaciones) |
| Copia del **reglamento interno** de obreros | Fase 4.4 (catálogo de faltas y multas) |
| Quién firma contratos y adendas **por el empleador** (¿el Prefecto o un delegado por resolución?) | Fase 4.3 |
| Formato y numeración de la **hoja de registro interno** (`RI-AAAA-NNNN` propuesto) | Fase 4.3 |
| Si un **certificado médico de reposo largo** genera solo una licencia por enfermedad, y desde cuántos días | Fase 5.1 |
| Si se registra el **despido intempestivo** como causal (cuando ocurre o lo ordena un juez) | Fase 4.5 |

---

## 12. Plan por fases

Cada fase se puede desplegar sola. Los PRs van **en serie**, cada uno verde en CI y probado en el navegador antes del siguiente: en este proyecto, juntar PRs probados por separado ya rompió una prueba que ninguno rompía solo.

**Fase 0 — Decisiones de TH.** Cerrada el 9 de octubre (11.1). Quedan los pendientes de 11.2.

**Fase 1 — Cimientos** (solo cambian nombres en pantalla, ya aprobados por TH)
1. Catálogo servido por el backend y API por clase: enums `ClaseAccionPersonal` y `FamiliaAccionPersonal`; columna `clase` con su migración de datos; endpoint del catálogo; la creación pide `clase` y `causal`; el frontend consume el catálogo y se borra su copia de reglas. La clase convive con el par tipo/subtipo, que sigue mandando sobre cómo opera cada acción: la elegibilidad se delega en él y la traducción entre los dos vive en `ClaseAccionPersonal`.
   - `causal` no tiene columna todavía: hoy es el subtipo de la cesación. La tendrá cuando lleguen causales que no son subtipos (fase 2.1).
   - `instrumento` llega con el primer instrumento distinto de la acción de personal (fase 2.7).
2. Bitácora fuera de `movimientos_personal` (`eventos_vinculo`); la API solo acepta clases creables a mano.
   - Pasan la novedad de contrato (ahora «contrato registrado sin acción», que cubre la carga inicial y el alta directa), las constancias de una subrogación terminada o cancelada antes, y los tipos `cambio_puesto`, `cambio_regimen` y `egreso`, que salen del catálogo. El expediente las muestra como «Novedades del vínculo», aparte de las acciones.
   - La reprogramación del plazo se queda en el registro de auditoría, de donde ya la lee la pantalla. Las correcciones llegan con las anotaciones del punto 4.
   - La API dejó de aceptar clases no creables a mano en la fase 1.1.
3. Permisos de Spatie y gates en la pantalla; el titular no ve borradores; nadie tramita sobre sí mismo.
   - Los cinco permisos de 6.3 se reparten por migración, como los demás, para no pisar los ajustes hechos desde Usuarios. Cada ruta pide el suyo; la de transición, el del paso concreto.
   - La regla de no tramitar lo propio está en los servicios (crear, editar, transicionar y registrar una subrogación), así que el `Gate::before` de admin-ti no se la salta. No aplica sin usuario autenticado (comandos).
   - El titular ve sus acciones registradas, notificadas y anuladas con número, también si trabaja en Talento Humano: en su expediente, en el detalle (un borrador responde 404) y en la bandeja.
   - Cada acción trae `transiciones_permitidas` y `puede_editar` para quien la mira, y la pantalla dibuja los botones con eso. La copia del grafo que tenía el frontend se borró.
4. Inmutabilidad completa tras registrar; anotaciones en lugar de reescrituras.
   - El candado del modelo pasa de una lista de campos prohibidos a una de permitidos: de una acción registrada o notificada solo cambia el estado, con los datos de ese paso (quién notificó y cuándo; el motivo de anulación). Una anulada no cambia nada más.
   - Tabla `anotaciones_accion_personal` (impugnación del visto bueno y nota de Talento Humano), que tampoco se edita. Anotar pide `preparar-accion-personal`, no vale sobre lo propio ni sobre un borrador.
   - La impugnación del visto bueno deja una anotación en vez de añadir texto a la explicación. Las que ya existían se copian desde `vistos_buenos`; el texto ya añadido no se toca, porque si la acción se registró después es parte del acto.
   - Las anotaciones se ven en el cajón de la acción y no salen en el documento.
5. Estado de la persona derivado del vínculo; `fecha_ingreso_institucion` no se pisa; antigüedad calculada con la regla de 8.3.
6. Motor de efectos con fecha y comando diario.

**Fase 2 — Completar la LOSEP**
1. Cesación con las causales de 4.3 (incluida la remoción), cascadas (plaza, subrogaciones, reemplazos) y borrador automático del ocasional vencido.
2. Licencia sin remuneración con causal, fechas y topes, solo para permanentes.
3. Comisiones con la regla legal y la institución de destino.
4. Reintegro y fin anticipado, con la salida del reemplazo; no se anula una ausencia con reemplazo vigente.
5. Sanción solo desde Disciplinario, con sus datos; amonestación escrita con acción; anular ↔ sanción.
6. Restitución con datos judiciales.
7. Ocasionales: ingreso como registro del contrato, sin acción.

**Fase 3 — Movilidad y carrera**
1. Traslado (Art. 35) unificado, con validaciones; jefaturas sin reemplazo contratado.
2. Período de prueba + nombramiento definitivo; el concurso entra con provisional de prueba; acta del concurso.
3. Ascenso con prueba de 6 meses y reserva del puesto.

**Fase 4 — Bloques por régimen**
1. Autoridades electas (9.14).
2. Servicios Profesionales como contrato civil (9.10).
3. Obreros: contrato, adenda y hoja de registro interno; cambio de ocupación y de lugar; incremento individual. Depende del firmante por el empleador y del formato del registro.
4. Obreros: sanciones con el catálogo del reglamento interno; visto bueno y aviso a Financiero con el registro interno. Depende del reglamento interno.
5. Obreros: terminación con sus causales, liquidación y acta de finiquito; reintegro por despido ineficaz.
6. Obreros: contrato colectivo parametrizado e incremento en lote. Depende del contrato colectivo.

**Fase 5 — Asistencia y reemplazos**
1. Licencias fuera de las vacaciones: registro con causa, tope y certificado, sin acción; Sirha7.
2. Subrogación y encargo separados; nivel jerárquico superior; enlace con la ausencia; Viceprefecto por el bloque 9.14.

**Fase 6 — Documento y notificación**
1. PDF de la acción de personal con el formato del Ministerio (6.5).
2. Vista previa con la marca BORRADOR, para todos los instrumentos que imprimen.
3. Portal «Mis documentos laborales» con acuse de recibo, aviso al correo institucional y razón con testigo; solicitudes de renuncia, licencia y jubilación.

**Fase 7 — Estructura, nómina y salida**
1. Operaciones sobre puestos ocupados (traspaso de puesto, revisión de clasificación) y bloqueo de la edición directa y del borrado.
2. Actos en lote.
3. Novedades de nómina; la nómina lee el vínculo (con un mes de corrida en paralelo).
4. Desvinculación.
5. Reporte SIITH por clase, con su pantalla.

Total: 6 + 7 + 3 + 6 + 2 + 3 + 5 = **32 PRs**.

---

## 13. Riesgos

| Riesgo | Mitigación |
|---|---|
| La migración cambia el histórico | Agrega columnas sin borrar; conteo previo en producción; ensayo en una copia; los números AP ya emitidos se conservan; tipos antiguos solo de lectura durante unos meses |
| El motor con fecha cambia cuándo pasan las cosas | Las acciones ya registradas se marcan aplicadas; el comando es idempotente; informe diario de lo aplicado |
| El cambio en `servidores.estado` saca de la nómina a «activos» que ya no lo son | Informe previo de quién cambia; revisión con TH antes de desplegar |
| Nómina sobre el vínculo | Corrida en paralelo un mes; diferencias revisadas por Financiero |
| TH opera hoy con otros nombres (traslado y traspaso) y emite acciones a obreros | Etiquetas con el artículo en pantalla; la pantalla de reglas; capacitación corta |
| El contrato colectivo puede cambiar cálculos que hoy el sistema hace con la ley sola | Parametrizar sus cláusulas antes de la fase 4.6; hasta entonces, el sistema avisa que el cálculo es el de la ley |
| Faltan documentos de TH (11.2) | Las fases 1 a 3 y 4.1–4.2 no dependen de ellos |

---

## Anexo: fuentes

- LOSEP, compilación de Lexis con última reforma del 2026-04-09: Arts. 16–18, 27–32, 35–40, 46–48, 58, 60–62, 68, 83, 126–127. Texto anterior a la Ley Orgánica de Integridad Pública, que la Corte Constitucional anuló entera (sentencia 52-25-IN/25, RO 3-X-2025).
- Reglamento General a la LOSEP: Arts. 16, 19–22 (acción de personal), 87 (suspensión), 102 (renuncia), 105 (remoción), 143 y 146 (ocasionales), 148 (contratos civiles), 190–191 (ascenso), 224–227 (período de prueba), 270–271 (subrogación y encargo).
- Código del Trabajo: Arts. 10, 11, 14, 15, 42, 44, 64, 69, 118, 152, 169, 172, 183–188, 192, 195.1–195.3, 216, 224.
- Constitución: Arts. 105 (revocatoria), 228 (ingreso sin concurso de los electos), 229 (servidores públicos; obreros sujetos al CT), 326.16.
- COOTAD: Arts. 47 literal s (licencias del Consejo), 51 y 52.1 (Viceprefecto), 333–336 (remoción de autoridades), 354, 359, 360.
- Mandatos Constituyentes 2 (art. 8) y 4.
- Acuerdos del Ministerio del Trabajo MDT-2024-080 y MDT-2025-056 (contratación colectiva en el sector público).
- Instrumento Andino de Seguridad y Salud en el Trabajo (Decisión 584 de la CAN), Art. 14.
- Sentencia 309-16-SEP-CC (ocasionales embarazadas o en lactancia).
- Formulario «Acción de Personal» del Ministerio del Trabajo (formato del 2014-05-27).
- Cuestionario y respuestas de TH: `docs/Preguntas_TH_Acciones_de_Personal.docx` (5 y 9 de octubre de 2026).
