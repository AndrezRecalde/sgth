# SGTH Frontend — Plan de Sprints
# GAD Provincial de Esmeraldas
# Versión 2.0 — Mayo 2026

---

## INSTRUCCIÓN DE USO PARA EL AGENTE

Lee este archivo junto con FRONTEND.md antes de ejecutar cualquier sprint.
Marca cada tarea como [x] cuando esté completada.
Nunca omitas una tarea sin notificar al usuario primero.
Si un componente ya existe en la lista de "Componentes creados", reutilízalo.
Si un tipo ya existe en api.ts, impórtalo desde ahí — nunca lo redefinas.

---

## ESTADO GLOBAL DEL PROYECTO

| Sprint | Módulo                        | Estado         |
|--------|-------------------------------|----------------|
| F-01   | Setup base                    | ✅ Completo    |
| F-02   | Auth + AppShell               | ✅ Completo    |
| F-03a  | Estructura — Vistas lectura   | ✅ Completo    |
| F-03b  | Estructura — CRUD completo    | ✅ Completo    |
| F-03c  | Estructura — Cargos y Grupos  | ✅ Completo    |
| F-04   | Expediente Digital            | ⏳ Pendiente   |
| F-05   | Nómina y Asistencia           | ⏳ Pendiente   |
| F-06   | Viáticos                      | ✅ Completo    |
| F-07   | Dispensario Médico            | ⏳ Pendiente   |
| F-08   | Autoservicio del Servidor     | ⏳ Pendiente   |
| F-09   | Selección y Evaluación        | ⏳ Pendiente   |
| F-10   | Capacitación y Bienestar      | ⏳ Pendiente   |
| F-11   | SSO + Disciplinario + SGD     | ⏳ Pendiente   |
| F-12   | Helpdesk + Inventario TI      | ⏳ Pendiente   |
| F-13   | Reportería y BI               | ⏳ Pendiente   |
| F-14   | Dashboard ejecutivo           | ⏳ Pendiente   |
| F-15   | QA + Optimización + Deploy    | ⏳ Pendiente   |

---

## COMPONENTES GLOBALES YA CREADOS

Antes de crear cualquier componente nuevo, verifica esta lista.
Si el componente existe, impórtalo — no lo vuelvas a crear.

```
src/components/ui/PageHeader.tsx
src/components/ui/SgthTable.tsx          ← wrapper DataTable estándar
src/components/ui/TableActions.tsx       ← menú de acciones de tabla
src/components/ui/EmptyState.tsx         ← empty state estándar
src/components/layout/AppShell.tsx
src/components/layout/Sidebar.tsx
src/components/layout/Sidebar.module.css
src/components/layout/Topbar.tsx
src/components/layout/Topbar.module.css
src/components/layout/NavItem.tsx
src/components/layout/NavGroup.tsx
src/components/layout/SidebarUserRow.tsx
src/hooks/useContainedInput.ts
src/hooks/useMobileBreakpoint.ts
src/hooks/useAuth.ts
src/lib/axios.ts
src/lib/queryClient.ts
src/lib/tablerIcons.ts
src/styles/inputs.contained.module.css
src/store/auth.store.ts
src/store/ui.store.ts
src/config/routes.ts
src/config/nav.ts
src/config/env.ts
src/config/mantine.theme.ts
```

---

## PATRONES OBLIGATORIOS — RECORDATORIO v2.0

```
✅ Formularios: React Hook Form + zodResolver(@hookform/resolvers/zod)
✅ Zod: siempre importar desde 'zod/v4', nunca desde 'zod'
✅ Selects y Switches en RHF: siempre con <Controller>
✅ TextInput y Textarea en RHF: usar register() directamente
✅ Inputs: siempre {...useContainedInput()} y SIEMPRE con label
✅ Tablas: siempre SgthTable (no DataTable directo)
✅ Acciones de tabla: siempre TableActions (no ActionIcon sueltos)
✅ Gráficas: siempre echarts-for-react con tema dinámico dark/light
✅ Iconos: siempre @tabler/icons-react, nunca emojis
✅ Estilos: CSS Modules, nunca styles={{}} inline reutilizables
✅ Tipos: importar desde @/types/api, nunca redefinir
✅ Notificaciones: @mantine/notifications con IconCheck/IconX
✅ Modals: fullScreen={isMobile} y radius={isMobile ? 0 : 'xl'}
✅ Grids: span={{ base: 12, sm: 6, md: 4 }} siempre responsive
✅ Botones crear: variant="light" color="emerald" leftSection={<IconX size={16} />}
✅ Botones cancelar: variant="default"
✅ Sidebar: var(--mantine-color-body) — adaptativo al dark mode
✅ Dark mode: useComputedColorScheme() para ECharts y componentes
✅ Metadata: export const metadata en cada page.tsx (formato: 'GADPE — Módulo')
✅ Páginas: máx 80 líneas → extraer *View.tsx si supera
✅ Componentes: máx 200 líneas
✅ Hooks: máx 150 líneas
✅ Services: máx 100 líneas
✅ NUNCA as unknown as T — corregir el tipo en api.ts
✅ NUNCA any en TypeScript
```

---

## SPRINT F-01 — Setup Base ✅ COMPLETO

**Fecha:** Mayo 2026

### Archivos creados
```
src/app/layout.tsx                       ← metadata global GADPE + ColorSchemeScript
src/app/Providers.tsx                    ← MantineProvider + localStorageColorSchemeManager
src/app/(dashboard)/layout.tsx
src/app/(dashboard)/page.tsx
src/config/env.ts
src/config/mantine.theme.ts              ← emerald como color primario, v9
src/config/nav.ts
src/config/routes.ts
src/hooks/useMobileBreakpoint.ts
src/hooks/useAuth.ts
src/hooks/useContainedInput.ts
src/lib/axios.ts
src/lib/queryClient.ts
src/lib/tablerIcons.ts
src/store/auth.store.ts                  ← UsuarioAuth sin name, con nombre_completo
src/store/ui.store.ts
src/styles/inputs.contained.module.css
src/types/api.generated.ts
src/types/api.ts
src/proxy.ts                             ← middleware Next.js 16 (en src/)
```

---

## SPRINT F-02 — Autenticación + AppShell ✅ COMPLETO

**Fecha:** Mayo 2026

### Archivos creados
```
src/app/(auth)/login/page.tsx
src/app/cambiar-password/page.tsx
src/components/layout/AppShell.tsx
src/components/layout/Sidebar.tsx        ← var(--mantine-color-body)
src/components/layout/Sidebar.module.css ← navItemActive con borde izq esmeralda
src/components/layout/Topbar.tsx         ← toggle dark mode en menú usuario
src/components/layout/Topbar.module.css
src/components/layout/NavItem.tsx        ← clase .navItemActive CSS Module
src/components/layout/NavGroup.tsx
src/components/layout/SidebarUserRow.tsx ← displayName + usuario_ti como subtítulo
src/features/auth/components/LoginForm.tsx        ← RHF + Zod
src/features/auth/components/CambiarPasswordForm.tsx ← RHF + Zod
src/features/auth/hooks/useLogin.ts
src/features/auth/hooks/useCambiarPassword.ts
src/features/auth/schemas/login.schema.ts
src/features/auth/schemas/cambiarPassword.schema.ts
src/features/auth/services/authService.ts
```

### Funcionalidades implementadas
- Login con RHF + Zod (usuario + contraseña)
- Redirección automática si ya hay token
- Cambio de contraseña obligatorio en primer login
- Cookie sgth_primer_login para bloquear navegación
- AppShell responsive (desktop/tablet/móvil)
- Sidebar con var(--mantine-color-body) — dark mode adaptativo
- Toggle dark/light en menú de usuario del Topbar
- Sidebar muestra usuario_ti como subtítulo del usuario

### Endpoints consumidos
```
POST /auth/login
POST /auth/logout
POST /auth/cambiar-contrasena   → payload: { nueva_contrasena, confirmar_contrasena }
```

---

## SPRINT F-03a — Estructura Organizacional (Lectura) ✅ COMPLETO

**Fecha:** Mayo 2026

### Archivos creados
```
src/app/(dashboard)/estructura/page.tsx          ← Server Component con metadata
src/app/(dashboard)/estructura/EstructuraView.tsx ← Client Component
src/components/ui/PageHeader.tsx
src/components/ui/SgthTable.tsx                  ← wrapper DataTable estándar
src/components/ui/TableActions.tsx               ← menú de acciones
src/components/ui/EmptyState.tsx
src/features/estructura/components/OrganigramaNode.tsx
src/features/estructura/components/OrganigramaTree.tsx
src/features/estructura/components/OrganigramaChart.tsx  ← ECharts tree LR
src/features/estructura/components/UnidadDrawer.tsx      ← drawer detalle nivel 2
src/features/estructura/components/DirectorioTable.tsx
src/features/estructura/components/DirectorioToolbar.tsx ← Select nivel 2
src/features/estructura/components/directorio.columns.tsx
src/features/estructura/hooks/useOrganigrama.ts
src/features/estructura/hooks/useUnidades.ts
src/features/estructura/hooks/useUnidad.ts
src/features/estructura/hooks/useDirectorio.ts
src/features/estructura/hooks/useTiposUnidad.ts
src/features/estructura/services/estructuraService.ts
```

### Funcionalidades implementadas
- Organigrama árbol recursivo expandible/colapsable (vista acordeón)
- Organigrama ECharts tree chart LR (vista nodo) con zoom y pan
- Al hacer clic en nodo nivel 2 → Drawer con subprocesos y puestos
- SegmentedControl para alternar entre vistas
- Directorio telefónico con SgthTable
- Búsqueda y filtro por unidad nivel 2 en directorio

### Endpoints consumidos
```
GET /estructura/organigrama
GET /estructura/unidades-administrativas
GET /estructura/unidades-administrativas/{id}
GET /estructura/directorio-telefonico
GET /catalogos/tipos-unidad
```

---

## SPRINT F-03b — Estructura Organizacional (CRUD) ✅ COMPLETO

**Fecha:** Mayo 2026

### Archivos creados
```
src/features/estructura/components/UnidadModal.tsx
src/features/estructura/components/UnidadForm.tsx          ← RHF + Zod
src/features/estructura/components/ExtensionModal.tsx
src/features/estructura/components/ExtensionForm.tsx       ← RHF + Zod
src/features/estructura/components/PuestosTab.tsx          ← con filtro por gestión
src/features/estructura/components/PuestoModal.tsx
src/features/estructura/components/PuestoForm.tsx          ← RHF + Zod + LoadingOverlay
src/features/estructura/components/puesto.columns.tsx
src/features/estructura/schemas/unidad.schema.ts
src/features/estructura/schemas/extension.schema.ts
src/features/estructura/schemas/puesto.schema.ts           ← cargo_id reemplaza denominacion
src/features/estructura/hooks/useUnidadMutations.ts
src/features/estructura/hooks/useExtensionMutations.ts
src/features/estructura/hooks/usePuestos.ts
src/features/estructura/hooks/usePuestoMutations.ts
src/features/estructura/hooks/useGruposOcupacionales.ts
```

### Funcionalidades implementadas
- CRUD Unidades Administrativas (con tipo y padre)
- CRUD Extensiones Telefónicas (filtro Select nivel 2)
- CRUD Puestos (vinculado a cargos, grupos ocupacionales)
- Formularios migrados a RHF + Zod
- Puestos con filtro por gestión nivel 2
- Paginación server-side en tabla de puestos

### Endpoints consumidos
```
POST   /estructura/unidades-administrativas
PUT    /estructura/unidades-administrativas/{id}
DELETE /estructura/unidades-administrativas/{id}
GET    /estructura/puestos
POST   /estructura/puestos
PUT    /estructura/puestos/{id}
DELETE /estructura/puestos/{id}
POST   /estructura/extensiones
PUT    /estructura/extensiones/{id}
DELETE /estructura/extensiones/{id}
```

---

## SPRINT F-03c — Estructura — Cargos y Grupos ✅ COMPLETO

**Fecha:** Mayo 2026

### Archivos creados
```
src/features/estructura/components/CargosTab.tsx
src/features/estructura/components/CargoModal.tsx          ← RHF + Zod + useEffect reset
src/features/estructura/components/GruposOcupacionalesTab.tsx
src/features/estructura/schemas/cargo.schema.ts
src/features/estructura/hooks/useCargos.ts
src/features/estructura/hooks/useCargoMutations.ts
src/features/estructura/services/cargoService.ts
```

### Funcionalidades implementadas
- Tab "Cargos": CRUD con clasificación empleado/contratado/obrero
- Tab "Grupos Ocupacionales": lectura de escala RMU con filtro por régimen
- 5 pestañas en Estructura: Organigrama / Directorio / Puestos / Cargos / Grupos
- Cargos separados de puestos (tabla cargos independiente)
- ClasificacionPersonal enum: empleado / contratado / obrero

### Endpoints consumidos
```
GET    /estructura/cargos
POST   /estructura/cargos
PUT    /estructura/cargos/{id}
DELETE /estructura/cargos/{id}
GET    /estructura/grupos-ocupacionales
```

---

## SPRINT F-03d — Usuarios del Sistema ✅ COMPLETO

**Fecha:** Mayo 2026

### Archivos creados
```
src/app/(dashboard)/usuarios/page.tsx            ← Server Component con metadata
src/app/(dashboard)/usuarios/UsuariosView.tsx    ← Client Component
src/features/usuarios/components/UsuarioDrawer.tsx  ← flujo 2 pasos crear usuario
src/features/usuarios/components/UsuarioModal.tsx   ← editar usuario
src/features/usuarios/components/UsuarioForm.tsx    ← RHF + Zod
src/features/usuarios/components/UsuarioTable.tsx
src/features/usuarios/components/UsuarioToolbar.tsx
src/features/usuarios/components/usuario.columns.tsx
src/features/usuarios/schemas/usuario.schema.ts
src/features/usuarios/hooks/useUsuarios.ts
src/features/usuarios/hooks/useUsuarioMutations.ts
src/features/usuarios/hooks/useRoles.ts
src/features/usuarios/hooks/useServidoresSinUsuario.ts
src/features/usuarios/hooks/usePermisos.ts
src/features/usuarios/services/usuarioService.ts
```

### Funcionalidades implementadas
- Drawer crear usuario (2 pasos):
  Paso 1: buscar servidor por cédula/nombre
  Paso 2: email, usuario_ti editable con sugerencia del backend,
          roles, permisos adicionales agrupados por módulo
- Badge "cubierto" para permisos incluidos en roles seleccionados
- Tooltip que indica qué rol cubre cada permiso
- Modal editar usuario (email y roles)
- Toggle activo/inactivo con Switch
- Restablecer contraseña a cédula del servidor
- EmptyState cuando no hay usuarios
- Sidebar y Topbar muestran usuario_ti
- users.servidor_id como FK (relación correcta)
- users.name eliminado — nombre_completo desde servidor

### Endpoints consumidos
```
GET    /admin/usuarios
POST   /admin/usuarios
PUT    /admin/usuarios/{id}
DELETE /admin/usuarios/{id}
POST   /admin/usuarios/{id}/toggle-activo
POST   /admin/usuarios/{id}/restablecer-contrasena
GET    /admin/usuarios/sugerir-usuario-ti?servidor_id=X
GET    /admin/permisos
GET    /admin/usuarios/{id}/permisos
POST   /admin/usuarios/{id}/permisos
GET    /admin/usuarios-roles
GET    /expediente/servidores/sin-usuario
```

---

## SPRINT F-04 — Expediente Digital ⏳ PENDIENTE

**Rama:** feature/sprint-f04
**Ruta:** /expediente
**Metadata:** 'GADPE — Expediente Digital'

### Objetivo
Gestión completa del expediente digital de servidores públicos.
La ficha del servidor cubre: datos personales, datos laborales,
historial académico, cargas familiares, discapacidad,
enfermedades catastróficas, cuentas bancarias,
declaraciones juramentadas y contratos.

### Contexto del backend

La tabla `servidores` tiene estos campos relevantes:
```
Identificación:  cedula, nombre, segundo_nombre, apellido,
                 segundo_apellido
Personal:        fecha_nacimiento, genero, estado_civil,
                 tipo_sangre, es_extranjero, nacionalidad,
                 pais_origen, provincia_nacimiento_id,
                 canton_nacimiento_id
Documentos:      numero_papeleta_votacion, pasaporte_numero,
                 pasaporte_vencimiento
Contacto:        telefono_celular, telefono_convencional,
                 correo_personal
Domicilio:       direccion_domicilio, provincia_domicilio,
                 ciudad_domicilio
Condición:       tiene_discapacidad, tiene_enfermedad_catastrofica
Laboral:         regimen_laboral, tipo_nombramiento,
                 numero_contrato, fecha_ingreso_institucion,
                 fecha_ingreso_sector_publico, fecha_nombramiento,
                 fecha_inicio_ultimo_contrato,
                 fecha_fin_ultimo_contrato,
                 unidad_administrativa_id, puesto_id, estado
```

Tablas relacionadas confirmadas:
```
contratos_servidor          ← contratos del servidor
historial_academico_servidor ← historial académico
cargas_familiares           ← cargas familiares
declaraciones_juramentadas  ← declaraciones de bienes
cuentas_bancarias_servidor  ← cuentas bancarias
```

Controladores disponibles en backend:
```
ServidorController
ContratoServidorController
HistorialAcademicoController
CargaFamiliarController
DeclaracionJuramentadaController
CuentaBancariaServidorController
DiscapacidadServidorController
EnfermedadCatastroficaServidorController
DocumentoServidorController
MovimientoPersonalController
SubrogacionController
CertificadoLaboralController
```

### Endpoints a consumir
```
# Servidores
GET    /expediente/servidores
POST   /expediente/servidores/basico
GET    /expediente/servidores/{id}
PUT    /expediente/servidores/{id}

# Documentos del servidor
GET    /expediente/servidores/{servidorId}/documentos
POST   /expediente/servidores/{servidorId}/documentos
DELETE /expediente/servidores/{servidorId}/documentos/{documentoId}
GET    /expediente/servidores/{servidorId}/documentos/{documentoId}/descargar

# Contratos
GET    /expediente/servidores/{servidorId}/contratos
POST   /expediente/servidores/{servidorId}/contratos
PUT    /expediente/servidores/{servidorId}/contratos/{contrato}
DELETE /expediente/servidores/{servidorId}/contratos/{contrato}

# Historial académico
GET    /expediente/servidores/{servidorId}/historial-academico
POST   /expediente/servidores/{servidorId}/historial-academico

# Cargas familiares
GET    /expediente/servidores/{servidorId}/cargas-familiares
POST   /expediente/servidores/{servidorId}/cargas-familiares
PUT    /expediente/servidores/{servidorId}/cargas-familiares/{id}
DELETE /expediente/servidores/{servidorId}/cargas-familiares/{id}

# Declaraciones juramentadas
GET    /expediente/servidores/{servidorId}/declaraciones-juramentadas
POST   /expediente/servidores/{servidorId}/declaraciones-juramentadas
GET    /expediente/servidores/{servidorId}/declaraciones-juramentadas/exportar
DELETE /expediente/servidores/{servidorId}/declaraciones-juramentadas/{id}

# Cuentas bancarias
GET    /expediente/servidores/{id}/cuentas-bancarias
POST   /expediente/servidores/{id}/cuentas-bancarias
PUT    /expediente/servidores/{id}/cuentas-bancarias/{cuenta}
DELETE /expediente/servidores/{id}/cuentas-bancarias/{cuenta}
POST   /expediente/servidores/{id}/cuentas-bancarias/{cuenta}/set-principal

# Discapacidades
GET    /expediente/servidores/{servidorId}/discapacidades
POST   /expediente/servidores/{servidorId}/discapacidades
PUT    /expediente/servidores/{servidorId}/discapacidades/{id}
DELETE /expediente/servidores/{servidorId}/discapacidades/{id}

# Enfermedades catastróficas
GET    /expediente/servidores/{servidorId}/enfermedades
POST   /expediente/servidores/{servidorId}/enfermedades
PUT    /expediente/servidores/{servidorId}/enfermedades/{id}
DELETE /expediente/servidores/{servidorId}/enfermedades/{id}

# Beneficiarios (dispensario)
GET    /expediente/servidores/{servidorId}/beneficiarios
POST   /expediente/servidores/{servidorId}/beneficiarios
PUT    /expediente/servidores/{servidorId}/beneficiarios/{id}
DELETE /expediente/servidores/{servidorId}/beneficiarios/{id}

# Certificado laboral
GET    /expediente/servidores/{id}/certificado-laboral

# Catálogos
GET    /catalogos/provincias
GET    /catalogos/provincias/{id}/cantones
GET    /catalogos/entidades-financieras
```

### Schemas Zod a crear
```
src/features/expediente/schemas/servidorBasico.schema.ts
  Datos mínimos para crear servidor:
  - cedula:            string length 10, solo dígitos
  - nombre:            string min 2
  - segundo_nombre:    string opcional
  - apellido:          string min 2
  - segundo_apellido:  string opcional
  - fecha_nacimiento:  string (date)
  - genero:            enum ['masculino','femenino','otro']
  - estado_civil:      enum ['soltero','casado','divorciado','viudo','union_libre']
  - tipo_sangre:       enum ['A+','A-','B+','B-','AB+','AB-','O+','O-']
  - provincia_nacimiento_id: number
  - canton_nacimiento_id:    number
  - regimen_laboral:   enum ['losep','codigo_trabajo']
  - tipo_nombramiento: enum (ver TipoNombramiento en api.ts)
  - unidad_administrativa_id: number
  - puesto_id:         number opcional

src/features/expediente/schemas/servidorContacto.schema.ts
  - telefono_celular:       string opcional
  - telefono_convencional:  string opcional
  - correo_personal:        email opcional
  - direccion_domicilio:    string opcional
  - provincia_domicilio:    string opcional
  - ciudad_domicilio:       string opcional

src/features/expediente/schemas/contrato.schema.ts
  - tipo_nombramiento:         enum TipoNombramiento
  - unidad_administrativa_id:  number
  - puesto_id:                 number opcional
  - fecha_ingreso_institucion: string (date)
  - fecha_inicio:              string (date)
  - fecha_fin:                 string opcional (date)
  - remuneracion:              number positivo opcional

src/features/expediente/schemas/historialAcademico.schema.ts
  - nivel_instruccion:   string
  - titulo:              string
  - institucion:         string
  - pais:                string
  - anio_graduacion:     number opcional
  - numero_registro:     string opcional
  - es_afin_al_cargo:    boolean default false

src/features/expediente/schemas/cargaFamiliar.schema.ts
  - nombres:            string min 2
  - apellidos:          string min 2
  - cedula:             string length 10 opcional
  - parentesco:         enum ['conyuge','hijo','padre','madre','hermano','otro']
  - fecha_nacimiento:   string opcional (date)
  - discapacidad:       boolean default false

src/features/expediente/schemas/declaracion.schema.ts
  - tipo_declaracion:    enum ['ingreso','salida','actualizacion']
  - fecha_declaracion:   string (date)
  - numero_declaracion:  string
  - observaciones:       string opcional

src/features/expediente/schemas/cuentaBancaria.schema.ts
  - entidad_financiera_id: number
  - numero_cuenta:         string min 5
  - tipo_cuenta:           enum ['ahorros','corriente']
  - es_principal:          boolean default false

src/features/expediente/schemas/documento.schema.ts
  - tipo_documento:     enum TipoDocumentoServidor
  - descripcion:        string opcional
  - fecha_vencimiento:  string opcional (date)
  - archivo:            File (manejado por Dropzone de Mantine,
                        no por Zod — validar en el componente)

src/features/expediente/schemas/discapacidad.schema.ts
  - tipo_discapacidad:   string
  - porcentaje:          number min 1 max 100
  - numero_carnet:       string opcional

src/features/expediente/schemas/enfermedad.schema.ts
  - nombre_enfermedad:   string min 3
  - codigo_cie10:        string opcional
  - fecha_diagnostico:   string opcional (date)
  - observaciones:       string opcional
```

### Hooks a crear
```
src/features/expediente/hooks/useServidores.ts
  - useQuery GET /expediente/servidores con ServidorParams
  - Paginación server-side, staleTime 3 min

src/features/expediente/hooks/useServidor.ts
  - useQuery GET /expediente/servidores/{id}
  - enabled: id !== null, staleTime 5 min

src/features/expediente/hooks/useServidorMutations.ts
  - crearBasico: POST /expediente/servidores/basico
  - actualizar:  PUT  /expediente/servidores/{id}
  - onSuccess: invalidar ['servidores', 'servidor']
  - Notificaciones estándar

src/features/expediente/hooks/useContratos.ts
  - useQuery GET /expediente/servidores/{servidorId}/contratos
  - enabled: servidorId !== null

src/features/expediente/hooks/useContratoMutations.ts
  - crear, actualizar, eliminar contrato
  - onSuccess: invalidar ['contratos', servidorId]

src/features/expediente/hooks/useHistorialAcademico.ts
  - useQuery GET /expediente/servidores/{servidorId}/historial-academico

src/features/expediente/hooks/useHistorialAcademicoMutations.ts
  - crear historial académico

src/features/expediente/hooks/useCargasFamiliares.ts
  - useQuery GET /expediente/servidores/{servidorId}/cargas-familiares

src/features/expediente/hooks/useCargaFamiliarMutations.ts
  - crear, actualizar, eliminar carga familiar

src/features/expediente/hooks/useDeclaraciones.ts
  - useQuery GET /expediente/servidores/{servidorId}/declaraciones-juramentadas

src/features/expediente/hooks/useDeclaracionMutations.ts
  - crear, eliminar declaración
  - exportar: GET .../exportar (descarga PDF)

src/features/expediente/hooks/useCuentasBancarias.ts
  - useQuery GET /expediente/servidores/{id}/cuentas-bancarias

src/features/expediente/hooks/useCuentaBancariaMutations.ts
  - crear, actualizar, eliminar, setPrincipal

src/features/expediente/hooks/useDiscapacidades.ts
  - useQuery GET /expediente/servidores/{servidorId}/discapacidades

src/features/expediente/hooks/useDiscapacidadMutations.ts
  - crear, actualizar, eliminar discapacidad

src/features/expediente/hooks/useEnfermedades.ts
  - useQuery GET /expediente/servidores/{servidorId}/enfermedades

src/features/expediente/hooks/useEnfermedadMutations.ts
  - crear, actualizar, eliminar enfermedad

src/features/expediente/hooks/useProvincias.ts
  - useQuery GET /catalogos/provincias, staleTime 1 hora

src/features/expediente/hooks/useCantones.ts
  - useQuery GET /catalogos/provincias/{id}/cantones
  - enabled: provinciaId !== null

src/features/expediente/hooks/useDocumentos.ts
  - useQuery GET /expediente/servidores/{servidorId}/documentos
  - enabled: servidorId !== null
  - staleTime: 2 min

src/features/expediente/hooks/useDocumentoMutations.ts
  - subir: POST /expediente/servidores/{id}/documentos
    (multipart/form-data con archivo)
  - eliminar: DELETE /expediente/servidores/{id}/documentos/{documentoId}
  - onSuccess: invalidar ['documentos', servidorId]
  - Notificaciones estándar

src/features/expediente/hooks/useEntidadesFinancieras.ts
  - useQuery GET /catalogos/entidades-financieras, staleTime 1 hora
```

### Componentes a crear

#### Listado de servidores
```
src/features/expediente/components/ServidorToolbar.tsx
  - TextInput búsqueda por cédula o nombre (contained, label: "Buscar servidor")
  - Select filtro por unidad nivel 2 (contained, label: "Unidad")
  - Select filtro por estado: activo/inactivo (contained, label: "Estado")
  - Select filtro por régimen: losep/codigo_trabajo (contained, label: "Régimen")
  - Botón limpiar filtros (IconX)

src/features/expediente/components/ServidorTable.tsx
  - SgthTable con paginación server-side
  - Al hacer clic en una fila → abre ServidorDetailDrawer

src/features/expediente/components/servidor.columns.tsx
  DataTableColumn<ServidorConRelaciones>[]:
  - Cédula: ff="monospace" size="sm"
  - Nombre completo: apellidos + nombres, fw={500}
  - Unidad: nombre de unidad_administrativa
  - Régimen: Badge 'LOSEP' emerald / 'Cód. Trabajo' blue
  - Estado: Badge 'Activo' emerald / 'Inactivo' gray
  - Acciones: TableActions con:
    · Ver expediente (IconEye, color blue)
    · Editar datos básicos (IconEdit, color gray)
    · Descargar certificado (IconDownload, color green)
```

#### Drawer del expediente (vista principal)
```
src/features/expediente/components/ServidorDetailDrawer.tsx
  - Drawer derecho, size=720px desktop, fullScreen móvil
  - Header: Avatar con iniciales + nombre completo + cédula + Badge régimen
  - Tabs con iconos:
    · Datos personales  (IconUser)
    · Laboral           (IconBriefcase)
    · Académico         (IconSchool)
    · Familia           (IconUsers)
    · Cuentas           (IconCreditCard)
    · Documentos        (IconPaperclip) — historial de archivos subidos
    · Declaraciones     (IconFileDescription)
    · Condición         (IconHeart) — discapacidad + enfermedades
  - Botón "Editar datos" en header del drawer

src/features/expediente/components/tabs/DatosPersonalesTab.tsx
  Muestra en formato de ficha:
  - Sección Identificación:
    · Cédula, nombre completo
    · Fecha nacimiento, edad calculada
    · Género, estado civil, tipo sangre
    · Nacionalidad (si es extranjero: país origen + pasaporte)
    · Número papeleta votación
  - Sección Contacto:
    · Teléfono celular, convencional
    · Correo personal
    · Dirección, provincia, ciudad de domicilio
  - Sección Nacimiento:
    · Provincia y cantón de nacimiento
  - Botón "Editar datos personales" abre ServidorPersonalModal

src/features/expediente/components/tabs/LaboralTab.tsx
  - Card superior: tipo nombramiento + régimen laboral + estado
  - Fechas: ingreso institución, ingreso sector público, nombramiento
  - Contratos: SgthTable con contratos del servidor
    · Columnas: tipo nombramiento, unidad, cargo, fecha inicio, fecha fin, estado
    · Badge estado: vigente=emerald, terminado=gray, cancelado=red
    · Botón "Nuevo contrato"
    · TableActions: ver, editar, eliminar contrato

src/features/expediente/components/tabs/AcademicoTab.tsx
  - SgthTable historial académico
  - Columnas: nivel instrucción, título, institución, país, año, afín al cargo
  - Badge "Afín al cargo" si aplica
  - Botón "Agregar título"
  - TableActions: eliminar registro

src/features/expediente/components/tabs/FamiliaTab.tsx
  Dos secciones con Divider:
  CARGAS FAMILIARES:
  - SgthTable cargas
  - Columnas: nombres, parentesco, cédula, fecha nacimiento, discapacidad
  - Badge parentesco
  - Botón "Agregar carga familiar"
  - TableActions: editar, eliminar

  BENEFICIARIOS DISPENSARIO:
  - SgthTable beneficiarios
  - Columnas: nombres, parentesco, activo
  - Botón "Agregar beneficiario"

src/features/expediente/components/tabs/CuentasTab.tsx
  - SgthTable cuentas bancarias
  - Columnas: entidad financiera, número (enmascarado ***1234),
              tipo cuenta, principal
  - Badge "Principal" emerald si es_principal
  - Badge tipo: Ahorros / Corriente
  - Botón "Nueva cuenta"
  - TableActions: editar, eliminar, establecer como principal

src/features/expediente/components/tabs/DeclaracionesTab.tsx
  - SgthTable declaraciones juramentadas
  - Columnas: tipo, número, fecha, observaciones
  - Badge tipo: ingreso=blue, salida=red, actualización=orange
  - Botón "Nueva declaración"
  - Botón "Exportar todas" (descarga PDF Contraloría)
  - TableActions: descargar documento, eliminar

src/features/expediente/components/tabs/DocumentosTab.tsx
  Historial de documentos del servidor agrupados por tipo:
  - Sección Identificación: cédula, papeleta de votación, pasaporte
  - Sección Académico: títulos académicos, certificados
  - Sección Laboral: nombramientos, resoluciones
  - Sección Declaraciones: declaraciones juramentadas
  - Sección Otros: documentos adicionales
  Para cada sección:
    · Lista de documentos subidos con: nombre, tamaño,
      fecha de subida, usuario que subió
    · Botón "Subir documento" por sección
    · TableActions por documento: Descargar (IconDownload),
      Eliminar (IconTrash)
  - Botón "Subir documento" abre DocumentoModal
  - Los contratos tienen su propio adjunto en LaboralTab
  - Las declaraciones tienen su propio PDF en DeclaracionesTab

src/features/expediente/components/DocumentoModal.tsx
  Modal subir documento al expediente
  - size="md", fullScreen={isMobile}
  - Select Tipo de documento (contained, agrupado por grupo):
    · Identificación: Cédula, Papeleta votación, Pasaporte
    · Académico: Título académico, Certificado
    · Laboral: Contrato, Nombramiento, Resolución
    · Declaraciones: Declaración juramentada
    · Otros: Otro
  - Dropzone de Mantine:
    · Acepta: PDF, JPG, PNG, DOC, DOCX
    · Tamaño máximo: 10MB
    · Muestra preview del nombre del archivo
  - TextInput Descripción (contained, opcional)
  - DateInput Fecha de vencimiento (contained, opcional)
    (útil para pasaporte que vence)
  - Botones: Cancelar / Subir documento

src/features/expediente/components/tabs/CondicionTab.tsx
  Dos secciones con Divider:
  DISCAPACIDAD:
  - Si tiene_discapacidad:
    · SgthTable discapacidades
    · Columnas: tipo, porcentaje, número carnet CONADIS
    · Botón "Registrar discapacidad"
  - Si no tiene:
    · EmptyState simple (sin acción)

  ENFERMEDADES CATASTRÓFICAS:
  - Si tiene_enfermedad_catastrofica:
    · SgthTable enfermedades
    · Columnas: nombre, código CIE-10, fecha diagnóstico
    · Botón "Registrar enfermedad"
  - Si no tiene:
    · EmptyState simple
```

#### Formularios (Modals)
```
src/features/expediente/components/ServidorNuevoDrawer.tsx
  Drawer crear servidor básico (no modal — drawer para tener más espacio)
  - size=600 desktop, fullScreen móvil
  - Sección 1 — Identificación:
    · TextInput Cédula (contained, maxLength 10)
    · TextInput Primer nombre (contained)
    · TextInput Segundo nombre (contained, opcional)
    · TextInput Primer apellido (contained)
    · TextInput Segundo apellido (contained, opcional)
  - Sección 2 — Datos personales:
    · DateInput Fecha de nacimiento (contained)
    · Select Género (contained)
    · Select Estado civil (contained)
    · Select Tipo de sangre (contained)
  - Sección 3 — Nacimiento:
    · Select Provincia de nacimiento (contained, carga cantones)
    · Select Cantón de nacimiento (contained, dependiente de provincia)
  - Sección 4 — Datos laborales:
    · Select Régimen laboral (contained): LOSEP / Código del Trabajo
    · Select Tipo de nombramiento (contained)
    · Select Unidad administrativa (contained, nivel 2)
    · Select Puesto (contained, opcional)
  - Botones: Cancelar / Registrar servidor

src/features/expediente/components/ServidorPersonalModal.tsx
  Modal editar datos personales y contacto
  - size="xl", fullScreen={isMobile}
  - Tabs: Datos personales | Contacto | Domicilio
  - Reutiliza schemas servidorBasico + servidorContacto

src/features/expediente/components/ContratoModal.tsx
  Modal crear/editar contrato
  - size="lg", fullScreen={isMobile}
  - Select Tipo de nombramiento (contained)
  - Select Unidad administrativa (contained, nivel 2)
  - Select Puesto (contained, filtrado por unidad)
  - DateInput Fecha inicio (contained)
  - DateInput Fecha fin (contained, opcional)
  - NumberInput Remuneración (contained, prefix="$")
  - Switch Contrato vigente

src/features/expediente/components/HistorialAcademicoModal.tsx
  Modal registrar título académico
  - Select Nivel de instrucción (contained)
  - TextInput Título obtenido (contained)
  - TextInput Institución (contained)
  - TextInput País (contained)
  - NumberInput Año de graduación (contained)
  - TextInput Número de registro SENESCYT (contained, opcional)
  - Switch Afín al cargo

src/features/expediente/components/CargaFamiliarModal.tsx
  Modal agregar/editar carga familiar
  - TextInput Nombres (contained)
  - TextInput Apellidos (contained)
  - TextInput Cédula (contained, opcional)
  - Select Parentesco (contained)
  - DateInput Fecha de nacimiento (contained, opcional)
  - Switch Tiene discapacidad

src/features/expediente/components/CuentaBancariaModal.tsx
  Modal agregar/editar cuenta bancaria
  - Select Entidad financiera (contained, searchable)
  - TextInput Número de cuenta (contained)
  - Select Tipo de cuenta: Ahorros / Corriente (contained)
  - Switch Cuenta principal

src/features/expediente/components/DeclaracionModal.tsx
  Modal registrar declaración juramentada
  - Select Tipo: ingreso / salida / actualización (contained)
  - TextInput Número de declaración (contained)
  - DateInput Fecha de declaración (contained)
  - Textarea Observaciones (contained, opcional)

src/features/expediente/components/DiscapacidadModal.tsx
  Modal registrar discapacidad
  - Select Tipo de discapacidad (contained)
  - NumberInput Porcentaje (contained, min=1 max=100)
  - TextInput Número carnet CONADIS (contained, opcional)

src/features/expediente/components/EnfermedadModal.tsx
  Modal registrar enfermedad catastrófica
  - TextInput Nombre de la enfermedad (contained)
  - TextInput Código CIE-10 (contained, opcional)
  - DateInput Fecha de diagnóstico (contained, opcional)
  - Textarea Observaciones (contained, opcional)
```

### Páginas a crear
```
src/app/(dashboard)/expediente/page.tsx
  Server Component:
  export const metadata = { title: 'GADPE — Expediente Digital' }
  → importa ExpedienteView

src/app/(dashboard)/expediente/ExpedienteView.tsx
  Client Component (máx 80 líneas):
  - PageHeader: "Expediente Digital" + IconFolder
  - Group justify="flex-end": Botón "Nuevo servidor"
    (color="emerald" variant="light" leftSection=<IconUserPlus>)
  - ServidorToolbar
  - ServidorTable (con EmptyState si no hay registros)
  - ServidorNuevoDrawer (estado: opened)
  - ServidorDetailDrawer (estado: opened, servidorId)
```

### Tipos a verificar/agregar en api.ts
```
Verificar que existen:
  Servidor, ServidorConRelaciones, ServidorParams
  ContratoServidor, TipoNombramiento, EstadoContrato
  CuentaBancariaServidor, EntidadFinanciera
  Provincia, Canton

Agregar si no existen:
  HistorialAcademicoServidor
  CargaFamiliar
  DeclaracionJuramentada
  DiscapacidadServidor
  EnfermedadCatastroficaServidor
  DocumentoServidor:
    id, tipo_documento, nombre_archivo, tamanio_bytes,
    mime_type, fecha_vencimiento, descripcion, estado,
    subido_por, created_at, url_descarga
```

---

## SPRINT F-05 — Nómina y Asistencia ⏳ PENDIENTE

**Rama:** feature/sprint-f05
**Rutas:** /nomina, /asistencia
**Metadata:** 'GADPE — Nómina' / 'GADPE — Asistencia'

### Endpoints a consumir
```
# Nómina
GET  /nomina
POST /nomina/calcular
GET  /nomina/{id}
POST /nomina/{id}/cerrar
GET  /nomina/{id}/rol-pago/{servidorId}

# Asistencia
GET  /asistencia/marcaciones
GET  /asistencia/permisos
POST /asistencia/permisos
GET  /asistencia/permisos/{id}
POST /asistencia/permisos/{id}/confirmar
POST /asistencia/permisos/{id}/anular
GET  /asistencia/vacaciones
POST /asistencia/vacaciones
```

### Componentes a crear — Nómina
```
src/features/nomina/components/NominaToolbar.tsx
  - Select mes/año (contained)
  - Select estado nómina (contained)
  - Botón "Calcular nómina" (solo admin)

src/features/nomina/components/NominaTable.tsx
  - SgthTable: período, estado, total servidores, total monto
  - Badge estado: abierta=blue / cerrada=gray / contabilizada=emerald

src/features/nomina/components/nomina.columns.tsx

src/features/nomina/components/NominaDetail.tsx
  - Drawer: detalle de nómina con lista de roles de pago
  - Resumen: ingresos totales, descuentos totales, neto

src/features/nomina/components/RolPagoModal.tsx
  - Vista del rol de pago individual
  - Tabla de conceptos (ingresos/descuentos)
  - Total a pagar
```

### Componentes a crear — Asistencia
```
src/features/asistencia/components/MarcacionesTab.tsx
  - SgthTable marcaciones con filtro por servidor y fechas
  - Columnas: servidor, fecha, hora entrada, hora salida, estado

src/features/asistencia/components/marcacion.columns.tsx

src/features/asistencia/components/PermisosTab.tsx
  - SgthTable permisos
  - Badge estado: solicitado / confirmado / anulado
  - Botones confirmar/anular según rol

src/features/asistencia/components/permiso.columns.tsx

src/features/asistencia/components/PermisoModal.tsx
  - Select servidor (admin) o solo del propio (autoservicio)
  - Select tipo permiso (contained)
  - DateInput fecha (contained)
  - NumberInput horas (contained)
  - Textarea justificación (contained)

src/features/asistencia/components/VacacionesTab.tsx
  - SgthTable vacaciones con días disponibles
  - Estado de solicitudes
```

---

## SPRINT F-06 — Viáticos ⏳ PENDIENTE

**Rama:** feature/sprint-f06
**Ruta:** /viaticos
**Metadata:** 'GADPE — Viáticos'

### Endpoints a consumir
```
GET    /viaticos
POST   /viaticos
GET    /viaticos/{id}
POST   /viaticos/{id}/liquidar
GET    /viaticos/{id}/destinos
POST   /viaticos/{id}/destinos
GET    /viaticos/{id}/transportes
POST   /viaticos/{id}/transportes
GET    /viaticos/{id}/facturas
POST   /viaticos/{id}/facturas
GET    /comisiones
POST   /comisiones
GET    /autorizaciones-vuelo
POST   /autorizaciones-vuelo/{id}/aprobar
POST   /autorizaciones-vuelo/{id}/rechazar
```

### Componentes a crear
```
src/features/viaticos/components/ViaticoToolbar.tsx
  - TextInput búsqueda (contained)
  - Select filtro por estado EstadoViatico (contained)
  - Select filtro por servidor (contained, admin)

src/features/viaticos/components/ViaticoTable.tsx
  - SgthTable con paginación
  - Badge estado con color por fase del workflow

src/features/viaticos/components/viatico.columns.tsx
  - Código, Servidor, Comisión, Destino, Fechas, Estado, Acciones

src/features/viaticos/components/ViaticoDetail.tsx
  - Drawer detalle completo
  - Tabs: Info general | Destinos | Transporte | Facturas | Liquidación

src/features/viaticos/components/WorkflowBadge.tsx
  - Badge de estado con colores del workflow de aprobación

src/features/viaticos/components/ViaticoModal.tsx
src/features/viaticos/components/ViaticoForm.tsx
  - Select comisión (searchable, contained)
  - Select servidor admin (contained)
  - Select zona geográfica (contained)
  - DateInput fecha inicio/fin (contained)
  - NumberInput anticipo solicitado (contained)
  - Textarea motivo (contained)
```

---

## SPRINT F-07 — Dispensario Médico ⏳ PENDIENTE

**Rama:** feature/sprint-f07
**Ruta:** /dispensario
**Metadata:** 'GADPE — Dispensario Médico'

### Endpoints a consumir
```
GET  /dispensario/dashboard/kpis
GET  /dispensario/agenda
POST /dispensario/agenda
POST /dispensario/agenda/{id}/confirmar
POST /dispensario/agenda/{id}/cancelar
GET  /dispensario/consultas
POST /dispensario/consultas
GET  /dispensario/consultas/{id}
POST /dispensario/triaje
GET  /dispensario/triaje/{consultaId}
POST /dispensario/recetas
GET  /dispensario/recetas/{id}
POST /dispensario/recetas/{id}/despachar
GET  /dispensario/cie10/buscar?q=
GET  /dispensario/historia-clinica/{beneficiarioId}
GET  /dispensario/inventario
POST /dispensario/inventario
```

### Componentes clave a crear
```
src/features/dispensario/components/DashboardTab.tsx
  - KPI cards: consultas hoy, agendadas, medicamentos críticos
  - Gráfica ECharts: consultas por mes
  - Gráfica ECharts: diagnósticos más frecuentes (CIE-10)

src/features/dispensario/components/AgendaTab.tsx
  - Lista/tabla de citas con filtro por médico y fecha
  - Badge estado: programada/confirmada/cancelada

src/features/dispensario/components/ConsultaDetail.tsx
  - Drawer con detalle de consulta
  - Tabs: Triaje | Diagnóstico | Receta | Historia

src/features/dispensario/components/DiagnosticoForm.tsx
  - Autocomplete búsqueda CIE-10 (AsyncAutocomplete)
  - Lista diagnósticos seleccionados

src/features/dispensario/components/RecetaForm.tsx
  - Búsqueda medicamento del inventario
  - Lista ítems: medicamento, dosis, frecuencia, días

src/features/dispensario/components/InventarioTab.tsx
  - SgthTable medicamentos con stock
  - Badge alerta stock mínimo (rojo si crítico)
```

---

## SPRINT F-08 — Autoservicio del Servidor ⏳ PENDIENTE

**Rama:** feature/sprint-f08
**Ruta:** /autoservicio/*
**Metadata:** 'GADPE — Mi Portal'

### Objetivo
Portal donde el servidor gestiona sus propias solicitudes
sin intervención de UATH.

### Endpoints a consumir
```
GET  /autoservicio/mi-expediente
GET  /autoservicio/permisos
POST /autoservicio/permisos/solicitar-cita
GET  /autoservicio/vacaciones
GET  /asistencia/marcaciones (propias)
GET  /actividades/mis-actividades
POST /actividades
PUT  /actividades/{id}
DELETE /actividades/{id}
GET  /helpdesk/tickets (propios)
POST /helpdesk/tickets
```

### Páginas a crear
```
src/app/(dashboard)/autoservicio/mi-perfil/page.tsx
src/app/(dashboard)/autoservicio/mis-permisos/page.tsx
src/app/(dashboard)/autoservicio/mis-vacaciones/page.tsx
src/app/(dashboard)/autoservicio/mis-marcaciones/page.tsx
src/app/(dashboard)/autoservicio/mis-actividades/page.tsx
src/app/(dashboard)/autoservicio/mis-tickets/page.tsx
```

---

## SPRINT F-09 — Selección y Evaluación ⏳ PENDIENTE

**Rama:** feature/sprint-f09
**Rutas:** /seleccion, /evaluacion
**Metadata:** 'GADPE — Selección de Personal' / 'GADPE — Evaluación del Desempeño'

### Endpoints a consumir
```
# Selección
GET  /seleccion/convocatorias
POST /seleccion/convocatorias
GET  /seleccion/convocatorias/{id}
GET  /seleccion/postulantes
POST /seleccion/postulantes
POST /seleccion/calificar
POST /seleccion/declarar-ganador

# Evaluación
GET  /evaluacion/criterios
POST /evaluacion/criterios
GET  /evaluacion/evaluaciones
POST /evaluacion/evaluaciones
GET  /evaluacion/resultados
GET  /evaluacion/planes-mejora
POST /evaluacion/planes-mejora
```

---

## SPRINT F-10 — Capacitación y Bienestar ⏳ PENDIENTE

**Rama:** feature/sprint-f10
**Rutas:** /capacitacion, /bienestar
**Metadata:** 'GADPE — Capacitación' / 'GADPE — Bienestar Laboral'

### Endpoints a consumir
```
# Capacitación
GET  /capacitacion/planes
POST /capacitacion/planes
GET  /capacitacion/cursos
POST /capacitacion/cursos
GET  /capacitacion/inscripciones
POST /capacitacion/inscripciones

# Bienestar
GET  /bienestar/planes
POST /bienestar/planes
GET  /bienestar/actividades
POST /bienestar/actividades
GET  /bienestar/encuestas
POST /bienestar/encuestas
```

---

## SPRINT F-11 — SSO + Disciplinario + SGD ⏳ PENDIENTE

**Rama:** feature/sprint-f11
**Rutas:** /sso, /disciplinario, /sgd
**Metadata:** 'GADPE — SSO' / 'GADPE — Disciplinario' / 'GADPE — Gestión Documental'

### Endpoints a consumir
```
# SSO
GET  /sso/riesgos
POST /sso/riesgos
GET  /sso/inspecciones
POST /sso/inspecciones
GET  /sso/accidentes
POST /sso/accidentes

# Disciplinario
GET  /disciplinario/sumarios
POST /disciplinario/sumarios
GET  /disciplinario/sanciones
POST /disciplinario/sanciones

# SGD
GET  /sgd/documentos
POST /sgd/documentos
GET  /sgd/tramites
POST /sgd/tramites
```

---

## SPRINT F-12 — Helpdesk + Inventario TI ⏳ PENDIENTE

**Rama:** feature/sprint-f12
**Rutas:** /helpdesk, /inventario-ti
**Metadata:** 'GADPE — Helpdesk' / 'GADPE — Inventario TI'

### Endpoints a consumir
```
# Helpdesk
GET  /helpdesk/tickets
POST /helpdesk/tickets
GET  /helpdesk/tickets/{id}
POST /helpdesk/tickets/{id}/cambiar-estado
POST /helpdesk/tickets/{id}/asignar
POST /helpdesk/tickets/{id}/cerrar
GET  /helpdesk/tecnicos
GET  /helpdesk/slas

# Inventario TI
GET  /inventario-ti/bienes
POST /inventario-ti/bienes
GET  /inventario-ti/asignaciones
POST /inventario-ti/asignaciones
GET  /inventario-ti/mantenimientos
POST /inventario-ti/mantenimientos
```

---

## SPRINT F-13 — Reportería y BI ⏳ PENDIENTE

**Rama:** feature/sprint-f13
**Ruta:** /reporteria
**Metadata:** 'GADPE — Reportería'

### Endpoints a consumir
```
GET  /reporteria/dashboard
GET  /reporteria/configuraciones
POST /reporteria/configuraciones
POST /reporteria/ad-hoc
POST /reporteria/background
GET  /reporteria/background/{job_id}
GET  /dispensario/dashboard/kpis
```

### Componentes clave
```
src/features/reporteria/components/KpiCard.tsx
  - Props: label, value, unit?, trend?, trendValue?, color?
  - Flecha arriba/abajo según trend

src/features/reporteria/components/charts/ServidoresPorUnidad.tsx
  - ECharts barras horizontales, top 10 unidades

src/features/reporteria/components/charts/EvolucionNomina.tsx
  - ECharts línea, últimos 6 meses

src/features/reporteria/components/charts/DistribucionNombramiento.tsx
  - ECharts dona, por tipo de nombramiento

src/features/reporteria/components/charts/ViaticosPorMes.tsx
  - ECharts barras, monto por mes
```

---

## SPRINT F-14 — Dashboard Ejecutivo ⏳ PENDIENTE

**Rama:** feature/sprint-f14
**Ruta:** / (página principal)
**Metadata:** 'GADPE — Dashboard'

### Objetivo
Reemplazar el placeholder actual de `/` con un dashboard
ejecutivo real con KPIs y gráficas del sistema.

### Componentes a crear
```
src/features/dashboard/components/DashboardPage.tsx
  - Saludo personalizado con nombre_completo del usuario
  - Fecha actual formateada en español
  - KPI cards en SimpleGrid responsive (base:1, sm:2, lg:4)
  - Sección "Pendientes de atención" según rol
  - Gráficas resumidas

src/features/dashboard/components/PendientesSection.tsx
  - Admin UATH: viáticos por aprobar, permisos pendientes
  - Médico: citas del día, recetas pendientes despacho
  - Técnico: tickets asignados abiertos
  - Servidor: mis solicitudes en trámite

src/features/dashboard/components/AccesosRapidos.tsx
  - Grid de botones acceso rápido según rol del usuario
  - Iconos grandes con label
```

---

## SPRINT F-15 — QA + Optimización + Deploy ⏳ PENDIENTE

**Rama:** feature/sprint-f15

### Checklist de calidad
```
□ npm run build limpio sin warnings ni errores TypeScript
□ Sin ningún 'any' en TypeScript
□ Sin ningún 'as unknown as T'
□ Sin console.log de debug
□ Todos los modals tienen fullScreen={isMobile}
□ Todos los Grid.Col tienen span={{ base, sm }}
□ Todos los inputs usan useContainedInput() y tienen label
□ Todas las tablas usan SgthTable
□ Todas las acciones de tabla usan TableActions
□ Todas las páginas tienen metadata exportada
□ Todos los formularios usan RHF + zodResolver
□ Todos los Selects/Switches en RHF usan Controller
□ Zod importado siempre desde 'zod/v4'
□ Todos los iconos son de @tabler/icons-react (sin emojis)
□ Todas las gráficas usan echarts-for-react con tema dinámico
□ Toggle dark/light funciona en todas las páginas
□ Sidebar y Topbar muestran usuario_ti correctamente
□ EmptyState presente en todas las listas vacías
□ Probar en móvil: login, dashboard, formularios
```

### Optimizaciones
```
□ Lazy loading de páginas pesadas con next/dynamic
□ Memoización con useMemo/useCallback donde aplique
□ Revisar staleTime de queries
□ Configurar next.config.js para producción
```

### Deploy
```
□ Variables de entorno de producción configuradas
□ nginx configurado para sgth-frontend
□ PM2 para mantener Next.js corriendo
□ CORS del backend para dominio de producción
```

---

## NOTAS IMPORTANTES PARA EL AGENTE

### Sobre formularios
Todos los formularios nuevos usan React Hook Form + zodResolver.
Los Selects, MultiSelect, Switch, DatePickerInput y NumberInput
de Mantine SIEMPRE usan Controller de RHF.
Los TextInput, PasswordInput y Textarea usan register() directamente.
Todos los inputs tienen label visible — nunca omitir el label.

### Sobre los tipos
Antes de crear cualquier tipo nuevo, verificar en src/types/api.ts.
Si el tipo existe, importarlo. Si no existe, agregarlo al final de api.ts.
Regenerar desde OpenAPI si el backend cambió: npm run types:generate

### Sobre los servicios
Cada feature tiene su propio service. No mezclar llamadas de
diferentes módulos en el mismo service.

### Sobre las mutations
Siempre invalidar las queries relacionadas en onSuccess.
Siempre mostrar notificación con @mantine/notifications.
Siempre tipar el error como AxiosError<ApiResponse>.

### Sobre los drawers vs modals
- Crear/ver entidades complejas con muchos campos → Drawer (más espacio)
- Crear entidades simples (3-5 campos) → Modal
- Detalle con tabs de sub-entidades → Drawer siempre

### Sobre las columnas de DataTable
Siempre en archivo separado: [entidad].columns.tsx
Siempre tipadas con DataTableColumn<TipoEntidad>[].
Nunca definir columnas inline en el componente de tabla.

### Sobre los schemas Zod
Siempre en archivo separado: [entidad].schema.ts
Siempre importar de 'zod/v4' — nunca de 'zod'.
Siempre exportar el schema Y el tipo inferido.

### Sobre metadata
Cada page.tsx exporta metadata estática.
Si la página necesita 'use client', extraer la lógica a *View.tsx.
El page.tsx importa el View y es un Server Component limpio.

---

_Fin del documento — SGTH Sprints v2.0 — Mayo 2026_
