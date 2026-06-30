// ============================================
// TIPOS DEL API — SGTH GAD Esmeraldas
// ============================================
// Generado automáticamente desde openapi.yaml
// NO editar manualmente los tipos marcados con ←AUTO
//
// Para regenerar ejecutar:
// cd sgth-backend
// php artisan scramble:export --path=storage/app/openapi.yaml
// copy storage\app\openapi.yaml ..\sgth-frontend\openapi.yaml
// cd ..\sgth-frontend
// npm run types:generate
// ============================================

export type { components, paths, operations } from './api.generated'

import type { components } from './api.generated'

// ── Respuesta estándar del API ───────────────
export type ApiResponse<T = unknown> = {
  exito: boolean
  mensaje: string
  datos: T
  errores?: Record<string, string[]>
}

// ── Paginación ───────────────────────────────
export type PaginatedResponse<T> = {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

// ── Auth ─────────────────────────────────────
export type LoginRequest = {
  usuario: string
  contrasena: string
}

export type LoginResponse = {
  token: string
  primer_login: boolean
  usuario: {
    id: number
    name: string
    email: string
    roles: string[]
    permisos: string[]
  }
}
export type CambiarContrasenaRequest = components['schemas']['CambiarContrasenaRequest']

// ── Geografía ────────────────────────────────
// Provincia no tiene schema en OpenAPI — definido manualmente
export type Provincia = {
  id: number
  nombre: string
  codigo: string
  cantones?: Canton[]
}
export type Canton = components['schemas']['Canton']

// ── Estructura ───────────────────────────────
export type UnidadAdministrativa = Omit<
  components['schemas']['UnidadAdministrativaResource'],
  'id' | 'codigo' | 'nombre' | 'acronimo' | 'descripcion' | 'nivel' | 'estado' | 'unidad_padre_id' | 'puestos_count'
> & {
  id: number
  codigo?: string
  nombre?: string
  acronimo?: string
  descripcion?: string
  nivel?: number
  estado?: boolean
  unidad_padre_id?: number | null
  puestos_count?: number
}
export type Puesto               = components['schemas']['PuestoResource']
export type TipoUnidad           = components['schemas']['TipoUnidad']
export type ExtensionTelefonica  = components['schemas']['ExtensionTelefonicaResource']

// ── Expediente ───────────────────────────────
export type Servidor                       = components['schemas']['ServidorResource']
export type ContratoServidor               = components['schemas']['ContratoServidor']
export type DiscapacidadServidor           = components['schemas']['DiscapacidadServidor']
export type EnfermedadCatastroficaServidor = components['schemas']['EnfermedadCatastroficaServidor']
export type CuentaBancariaServidor         = components['schemas']['CuentaBancariaServidor']
export type EntidadFinanciera = {
  id:         number
  nombre:     string
  tipo?:      string
  codigo_bce?: string | null
  estado?:    boolean
}

// ── Viáticos ──────────────────────────────
export type Viatico = components['schemas']['Viatico']
export type LiquidacionViatico =
  components['schemas']['LiquidacionViatico']
export type FacturaViatico =
  components['schemas']['FacturaViatico']
export type AutorizacionVuelo =
  components['schemas']['AutorizacionVueloResource']

// Nuevos tipos del rediseño
export type TramoViatico = {
  id:                    number
  viatico_id:            number
  orden:                 number
  tipo_tramo?:           'ida' | 'destino' | 'escala' | 'regreso' | null
  origen_tipo:           'nacional' | 'internacional'
  origen_provincia_id?:  number | null
  origen_canton_id?:     number | null
  origen_pais?:          string | null
  origen_ciudad:         string
  destino_tipo:          'nacional' | 'internacional'
  destino_provincia_id?: number | null
  destino_canton_id?:    number | null
  destino_pais?:         string | null
  destino_ciudad:        string
  empresa_transporte_id: number
  empresa?: {
    id:      number
    nombre:  string
    codigo:  string
    catalogo?: {
      id:                     number
      nombre:                 string
      tipo_vehiculo:          string
      requiere_autorizacion:  boolean
    }
  }
  origen_provincia?:  { id: number; nombre: string } | null
  origen_canton?:     { id: number; nombre: string } | null
  destino_provincia?: { id: number; nombre: string } | null
  destino_canton?:    { id: number; nombre: string } | null
  datetime_salida:    string
  datetime_llegada:   string
  autorizacion_vuelo?: {
    id:     number
    estado: 'pendiente' | 'aprobada' | 'rechazada'
  } | null
}

export type CatalogoTransporte = {
  id:                    number
  nombre:                string
  codigo:                string
  tipo_vehiculo:         'terrestre' | 'aereo' | 'maritimo' | 'otro'
  requiere_autorizacion: boolean
  activo:                boolean
  orden:                 number
}

export type EmpresaTransporte = {
  id:                     number
  catalogo_transporte_id: number
  nombre:                 string
  codigo:                 string
  activo:                 boolean
  orden:                  number
}

export type CategoriaFactura = {
  id:          number
  nombre:      string
  codigo:      string
  descripcion?: string | null
  activo:      boolean
  orden:       number
  grupo?: 'viatico' | 'movilizacion' | null
}

export type ViaticoConRelaciones = Viatico & {
  coeficiente_exterior?: number | string | null;
  servidor?: {
    id:              number
    cedula?:         string
    nombre?:         string
    segundo_nombre?: string | null
    apellido?:       string
    segundo_apellido?: string | null
    puesto?: {
      id?:   number
      cargo?: { nombre?: string } | null
      unidad_administrativa?: { nombre?: string } | null
    } | null
  }
  tramos?:   TramoViatico[]
  liquidacion?: LiquidacionViatico & {
    actividades?:     ActividadLiquidacion[]
    detalles_factura?: FacturaViatico[]
  }
  todos_servidores?: {
    id:          number
    es_titular:  boolean
    servidor?: {
      id:       number
      nombre?:  string
      apellido?: string
      puesto?: {
        cargo?: { nombre?: string } | null
      } | null
    }
  }[]
  autorizaciones_vuelo?: AutorizacionVuelo[]
}

// Actualiza EstadoViatico
export type EstadoViatico =
  | 'solicitado'
  | 'aprobado'
  | 'con_anticipo'
  | 'en_comision'
  | 'pendiente_liquidacion'
  | 'liquidado'
  | 'contabilizado'
  | 'cancelado'
  | 'rechazado'

export type ViaticoParams = {
  page?:        number
  per_page?:    number
  estado?:      EstadoViatico
  servidor_id?: number
}

export type ActividadLiquidacion = {
  id:                     number
  liquidacion_viatico_id: number
  fecha:                  string
  hora_inicio:            string
  hora_fin:               string
  descripcion:            string
  lugar:                  string
  orden:                  number
}

// ── Dispensario ──────────────────────────────
export type Beneficiario       = components['schemas']['BeneficiarioResource']
export type HistoriaClinica    = components['schemas']['HistoriaClinica']
export type AgendaMedica       = components['schemas']['AgendaMedica']
export type ConsultaMedica     = components['schemas']['ConsultaMedica']
export type Triaje             = components['schemas']['Triaje']
export type RecetaMedica       = components['schemas']['RecetaMedica']
export type DiagnosticoCie10   = components['schemas']['DiagnosticoCie10']
export type AlergiaPaciente    = components['schemas']['AlergiaPaciente']
export type AntecedentePaciente = components['schemas']['AntecedentePaciente']
export type InventarioMedicina = components['schemas']['InventarioMedicina']

// ── Nómina ───────────────────────────────────
// export type RolPago      = components['schemas']['RolPago']
export type DetalleNomina = {
  id: number
  nomina_id: number
  servidor_id: number
  concepto_id: number
  tipo: string
  monto: number
  observacion?: string | null
}

// ── Asistencia ───────────────────────────────
// export type Permiso  = components['schemas']['PermisoServidor']
// export type Vacacion = components['schemas']['Vacacion']

// ── Enums de conveniencia ────────────────────

export type TipoNombramiento =
  | 'nombramiento_permanente'
  | 'nombramiento_provisional'
  | 'servicios_ocasionales'
  | 'libre_nombramiento_remocion'
  | 'codigo_trabajo'
  | 'servicios_profesionales'

export type EstadoContrato =
  | 'vigente'
  | 'terminado'
  | 'cancelado'

export type ConceptoFactura =
  | 'alimentacion'
  | 'hospedaje'
  | 'transporte_terrestre'
  | 'pasaje_aereo'
  | 'combustible'
  | 'peaje'
  | 'materiales'
  | 'otro'

// ── Tipos de parámetros de búsqueda ──────────
export type ServidorParams = {
  page?: number
  per_page?: number
  search?: string
  unidad_id?: number
  estado?: EstadoContrato
}

export type AgendaParams = {
  page?: number
  per_page?: number
  fecha?: string
  medico_id?: number
}

export type UnidadAdministrativaParams = {
  page?: number
  per_page?: number
  search?: string
  tipo_unidad_id?: number
  nivel?: number
  con_puestos?: boolean
}

export type PuestoParams = {
  page?: number
  per_page?: number
  search?: string
  unidad_administrativa_id?: number
}

export type ExtensionTelefonicaParams = {
  page?: number
  per_page?: number
  search?: string
  unidad_administrativa_id?: number
}

// Tipos extendidos con relaciones (no generados por OpenAPI)

export type UnidadConRelaciones = Omit<UnidadAdministrativa, 'id' | 'codigo' | 'nombre' | 'acronimo' | 'descripcion' | 'nivel' | 'estado' | 'unidad_padre_id' | 'tipo_unidad' | 'puestos_count' | 'puestos' | 'hijos'> & {
  id: number
  codigo?: string
  nombre?: string
  acronimo?: string
  descripcion?: string
  nivel?: number
  estado?: boolean
  unidad_padre_id?: number | null
  tipo_unidad?: {
    id: string
    acronimo?: string
    descripcion?: string
  }
  puestos_count?: number
  puestos?: PuestoConRelaciones[]
  hijos?: UnidadConRelaciones[]
}

export type ExtensionConRelaciones = {
  id: number
  numero_extension?: string
  responsable?: string

  estado?: boolean
  unidad_administrativa_id?: number
  unidad_administrativa?: {
    id: number
    nombre?: string
  }
}

export type ContratoParams = {
  page?: number
  per_page?: number
  servidor_id?: number
  estado?: EstadoContrato
}

export type MovimientoPersonalParams = {
  page?: number
  per_page?: number
  servidor_id?: number
}

export type ServidorConRelaciones = Servidor & {
  id: number
  nombre?: string
  segundo_nombre?: string
  apellido?: string
  segundo_apellido?: string
  nombres?: string        // campo calculado para compatibilidad
  apellidos?: string      // campo calculado para compatibilidad
  cedula?: string
  fecha_nacimiento?: string
  genero?: string
  estado_civil?: string
  tipo_sangre?: string | null
  es_extranjero?: boolean
  provincia_nacimiento_id?: number | null
  canton_nacimiento_id?: number | null
  telefono_celular?: string
  telefono_convencional?: string
  telefono_personal?: string
  telefono_institucional?: string

  correo_personal?: string
  direccion?: string
  direccion_domicilio?: string
  tiene_discapacidad?: boolean
  tiene_enfermedad_catastrofica?: boolean
  contrato_vigente?: ContratoConRelaciones
  estado?: boolean
  fecha_ingreso_institucion?:    string | null
  fecha_ingreso_sector_publico?: string | null
  fecha_nombramiento?:           string | null
  numero_contrato?:              string | null
  puesto?: {
    id:     number
    es_jefe?: boolean
    cargo?: {
      id:     number
      nombre?: string
      denominacion_generica?: string
    } | null
  } | null
  unidad_administrativa?: { id: number; nombre?: string }
  regimen_laboral?: 'losep' | 'codigo_trabajo'
  numero_papeleta_votacion?: string | null
  nacionalidad?: string | null
  pais_origen?: string | null
  pasaporte_numero?: string | null
  provincia_domicilio?: string | null
  ciudad_domicilio?: string | null
  puede_marcar?: boolean | null
}

export type ContratoConRelaciones = ContratoServidor & {
  unidad_administrativa?: {
    id:     number
    nombre?: string
    codigo?: string
  } | null
  puesto?: {
    id:              number
    es_jefe?:        boolean
    rol_puesto?:     string
    regimen_laboral?: string
    cargo?: {
      id:                    number
      nombre?:               string
      denominacion_generica?: string
      clasificacion_personal?: string
    } | null
  } | null
  estado?:         string
  fecha_inicio?:   string
  fecha_fin?:      string | null
  numero_contrato?: string | null
  resolucion_numero?: string | null
  puede_marcar?: boolean | null
  documento_ruta?: string | null
  remuneracion?: string | number | null
}

export type DocumentoServidorConRelaciones = {
  id: number
  servidor_id: number
  tipo_documento?: string
  numero_documento?: string
  archivo_ruta?: string
  fecha_emision?: string | null
  fecha_vencimiento?: string | null
  created_at?: string
}

export type CuentaBancariaConRelaciones = CuentaBancariaServidor & {
  entidad_financiera?: EntidadFinanciera | null
  numero_cuenta?:      string
  tipo_cuenta?:        'ahorros' | 'corriente'
  proposito?:          'sueldo' | 'viaticos' | 'ambos'
  es_principal_sueldo?:  boolean
  es_principal_viatico?: boolean
  es_principal?:         boolean
  estado?:               boolean
}

export type MovimientoPersonalConRelaciones = {
  id: number
  servidor_id: number
  tipo_movimiento?: string
  descripcion?: string
  fecha_movimiento?: string
  created_at?: string
}

// ── Historial Académico ───────────────────────
export type TipoEstudio = 'estudio' | 'capacitacion'
export type NivelEstudio = 'primaria' | 'secundaria' | 'tercer_nivel' | 'cuarto_nivel'
export type NacionalidadEstudio = 'nacional' | 'internacional'

export type HistorialAcademicoServidor = {
  id: number
  servidor_id: number
  tipo_estudio: TipoEstudio
  nivel_estudio: NivelEstudio | null
  nacionalidad_estudio: NacionalidadEstudio
  institucion: string
  fecha_inicio: string
  fecha_fin: string | null
  titulo_capacitacion: string
  codigo_senescyt: string | null
  created_at?: string
}

export type HistorialAcademicoParams = {
  servidor_id?: number
}

// ── Cargas Familiares ─────────────────────────
export type TipoParentesco = 'conyugue' | 'hijo'

export type CargaFamiliar = {
  id:          number
  servidor_id: number
  cedula:      string
  apellidos:   string
  nombres:     string
  parentesco:  TipoParentesco
  fecha_nacimiento: string
  persona_con_discapacidad:      boolean
  posee_enfermedad_catastrofica: boolean
  observaciones: string | null
  estado:        boolean
  discapacidades?:              DiscapacidadCargaFamiliar[]
  enfermedades_catastroficas?:  EnfermedadCatastroficaCargaFamiliar[]
  created_at?: string
}

export type DiscapacidadCargaFamiliar = {
  id:                    number
  carga_familiar_id:     number
  tipo_discapacidad:     string
  porcentaje:            number
  numero_carnet_conadis?: string | null
  created_at?:           string
}

export type EnfermedadCatastroficaCargaFamiliar = {
  id:                   number
  carga_familiar_id:    number
  tipo_enfermedad:      string
  codigo_cie10?:        string | null
  fecha_diagnostico?:   string | null
  created_at?:          string
}

// ── Declaraciones Juramentadas ────────────────
export type TipoDeclaracion =
  | 'inicio_gestion'
  | 'periodica'
  | 'fin_gestion'

export type DeclaracionJuramentada = {
  id: number
  servidor_id: number
  fecha_declaracion: string
  codigo_barras: string
  tipo_declaracion: TipoDeclaracion
  documento_ruta: string | null
  documento_nombre_archivo: string | null
  created_at?: string
}

export type DeclaracionExportParams = {
  fecha_inicio: string
  fecha_fin: string
  formato: 'txt' | 'pdf'
}

// ── Usuario del sistema ───────────────────────
export type Usuario = {
  id: number
  nombre_completo?: string
  email: string
  usuario_ti?: string
  activo?: boolean
  primer_login?: boolean
  servidor_id?: number | null
  roles?: string[]
  servidor?: {
    id: number
    cedula?: string
    nombre?: string
  } | null
  created_at?: string
}

export type UsuarioParams = {
  page?: number
  per_page?: number
  search?: string
  rol?: string
  activo?: boolean
  sin_servidor?: boolean
}

export type UsuarioFormData = {
  email:       string
  roles:       string[]
  servidor_id?: number | null
  cedula?:     string | null
  usuario_ti?: string | null
}

export type UsuarioUpdateData = {
  email?:      string
  roles?:      string[]
  usuario_ti?: string | null
}

export type PuestoConRelaciones = {
  id: number
  cargo_id?: number | null
  denominacion?: string | null
  unidad_administrativa_id?: number
  grupo_ocupacional_id?: number | null
  partida_presupuestaria_id?: number | null
  plazas?: number
  rol_puesto?: string | null
  nivel_complejidad?: string | null
  regimen_laboral?: 'losep' | 'codigo_trabajo'
  es_jefe?: boolean
  activo?: boolean
  rmu?: number | null
  cargo?: {
    id: number
    nombre?: string
    denominacion_generica?: string | null
    clasificacion_personal?: ClasificacionPersonal
  } | null
  unidad_administrativa?: { id: number; nombre?: string }
  grupo_ocupacional?: {
    id: number
    grado_codigo?: string
    grupo?: string
    rmu?: number
    regimen?: string
  } | null
}

// ── Cargos ───────────────────────────────────────
export type ClasificacionPersonal =
  | 'empleado'
  | 'contratado'
  | 'obrero'

export type Cargo = {
  id: number
  nombre: string
  denominacion_generica?: string | null
  mision?: string | null
  clasificacion_personal: ClasificacionPersonal
  activo: boolean
}

export type CargoFormData = {
  nombre: string
  denominacion_generica?: string
  mision?: string
  clasificacion_personal: ClasificacionPersonal
}

export type CargoParams = {
  search?: string
  clasificacion?: ClasificacionPersonal
}

export type GrupoOcupacional = {
  id: number
  grado_codigo?: string
  grado_numerico?: number | null
  grupo?: string
  denominacion_generica?: string | null
  rmu?: string | number
  regimen?: 'losep' | 'codigo_trabajo'
  nivel_complejidad?: string | null
  rol_puesto?: string | null
  activo?: boolean
}

// ── Permisos ─────────────────────────────────────
export type PermisoItem = {
  id:     number
  nombre: string
  modulo: string
  roles:  string[]
}

export type PermisoGrupo = {
  modulo:   string
  permisos: PermisoItem[]
}

export type UsuarioCreateData = {
  email:       string
  usuario_ti:  string
  roles:       string[]
  servidor_id?: number | null
  cedula?:     string | null
  permisos?:   string[]
}

// ── Documentos del servidor ──────────────────────
export type DocumentoServidor = {
  id:                number
  servidor_id:       number
  tipo_documento:    string
  nombre_archivo:    string
  tamanio_bytes?:    number
  mime_type?:        string
  fecha_vencimiento?: string | null
  descripcion?:      string | null
  estado?:           boolean
  subido_por?: {
    id:         number
    usuario_ti?: string
  } | null
  created_at?:    string
  url_descarga?:  string
}

export type TipoDocumentoServidor =
  | 'cedula'
  | 'papeleta_votacion'
  | 'pasaporte'
  | 'titulo_academico'
  | 'certificado'
  | 'contrato'
  | 'nombramiento'
  | 'resolucion'
  | 'declaracion'
  | 'otro'

// ── Discapacidades ───────────────────────────────
export type DiscapacidadDetalle = {
  id:              number
  servidor_id:     number
  tipo_discapacidad: string
  porcentaje:      number
  numero_carnet?:  string | null
  created_at?:     string
}

// ── Enfermedades catastróficas ───────────────────
export type EnfermedadDetalle = {
  id:                  number
  servidor_id:         number
  nombre_enfermedad:   string
  codigo_cie10?:       string | null
  fecha_diagnostico?:  string | null
  observaciones?:      string | null
  created_at?:         string
}


// ── Nómina ───────────────────────────────────────
export type EstadoNomina =
  'borrador' | 'en_proceso' | 'cerrada' | 'contabilizada' | 'pagada'

export type Nomina = {
  id:                number
  periodo:           string
  fecha_inicio:      string
  fecha_fin:         string
  estado:            EstadoNomina
  roles_pago?: {
    id:                number
    servidor_id:       number
    total_ingresos?:   number | string | null
    total_descuentos?: number | string | null
    total_neto?:       number | string | null
    servidor?: {
      cedula?:   string
      nombre?:   string
      apellido?: string
    }
  }[]
  total_ingresos?:   number | string | null
  total_descuentos?: number | string | null
  total_neto?:       number | string | null
  cerrado_por?:      number | null
  cerrado_en?:       string | null
  created_at?:       string
}

export type ConceptoNomina = {
  id:          number
  codigo:      string
  nombre:      string
  tipo:        'ingreso' | 'descuento'
  formula?:    string | null
  porcentaje?: number | null
  activo:      boolean
}

export type RolPago = {
  id:                  number
  nomina_id:           number
  servidor_id:         number
  total_ingresos?:     number | string | null
  total_descuentos?:   number | string | null
  total_neto?:         number | string | null
  enviado_por_correo?: boolean
  enviado_en?:         string | null
  servidor?:           ServidorConRelaciones
  nomina?:             Nomina & {
    detalles?: {
      id:          number
      servidor_id: number
      concepto?:   ConceptoNomina
      valor?:      number | string
    }[]
  }
}

export type DescuentoRecurrente = {
  id:                     number
  servidor_id:            number
  concepto_nomina_id:     number
  valor_cuota:            number | string
  numero_cuotas_total:    number
  numero_cuotas_pagadas:  number
  fecha_inicio:           string
  fecha_fin?:             string | null
  referencia_externa?:    string | null
  estado:                 'activo' | 'completado' | 'suspendido'
  observacion?:           string | null
  concepto?:              ConceptoNomina
  servidor?:              ServidorConRelaciones
}

// ── Asistencia ───────────────────────────────────
export type MarcacionBiometrica = {
  Fecha:                          string
  BADGENUMBER:                    string
  Nombre:                         string
  HoraEntradaProgramada:          string | null
  HoraAlmuerzoSalidaProgramada:   string | null
  HoraAlmuerzoRetornoProgramada:  string | null
  HoraSalidaProgramada:           string | null
  Entrada:                        string | null
  AlmuerzoSalida:                 string | null
  AlmuerzoRetorno:                string | null
  Salida:                         string | null
  TipoPermiso:                    string | null
  PermisoDesde:                   string | null
  PermisoHasta:                   string | null
}

export type EstadoPermiso =
  'pendiente' | 'activo' | 'validado_trabajo_social' | 'anulado'

export type TipoPermiso = 'personal' | 'oficial' | 'enfermedad' | 'calamidad'

export type PermisoServidor = {
  id:               number
  servidor_id:      number
  jefe_id?:         number | null
  creado_por?:      number | null
  tipo:             TipoPermiso
  fecha:            string
  hora_inicio:      string
  hora_fin:         string
  observacion?:     string | null
  estado:           EstadoPermiso
  folio?:           string | null
  vence_en?:        string | null
  confirmado_por?:  number | null
  confirmado_en?:   string | null
  validado_ts_por?: number | null
  validado_ts_en?:  string | null
  anulado_por?:     number | null
  anulado_en?:      string | null
  servidor?:        ServidorConRelaciones
  unidad_administrativa_id?: number | null
  unidad_administrativa?:    { id: number; nombre?: string } | null
  jefe?:            ServidorConRelaciones | null
  creadoPor?:       { id: number; name?: string } | null
}

export type MotivoVacacion =
  | 'vacaciones_anuales'
  | 'permiso_cargo_vacaciones'
  | 'licencia_sin_goce'
  | 'matrimonio'
  | 'capacitacion'
  | 'enfermedad'
  | 'maternidad'
  | 'paternidad'
  | 'estudios_sin_remuneracion'
  | 'calamidad_domestica'
  | 'licencia_con_goce'

export type EstadoVacacion =
  'pendiente' | 'aprobada' | 'rechazada' | 'gozada'

export type Vacacion = {
  id:               number
  servidor_id:      number
  jefe_id?:         number | null
  creado_por?:      number | null
  folio?:           string | null
  codigo_qr?:       string | null
  motivo:           MotivoVacacion
  fecha_inicio:     string
  fecha_fin:        string
  fecha_retorno?:   string | null
  fecha_emision?:   string | null
  dias_solicitados: number
  tipo_dias:        'habiles' | 'calendario'
  estado:           EstadoVacacion
  aprobado_por?:    number | null
  servidor?:        ServidorConRelaciones
  jefe?:            ServidorConRelaciones | null
  unidad_administrativa_id?: number | null
  unidad_administrativa?:    { id: number; nombre?: string } | null
  persona_reemplaza_id?: number | null
  periodo_vacacion_id?:  number | null
  persona_reemplaza?:    ServidorConRelaciones | null
  periodo_vacacion?:     PeriodoVacacion | null
}

// ── Períodos de vacaciones ───────────────────────
export type PeriodoVacacion = {
  id:                   number
  servidor_id:          number
  anio:                 number
  fecha_inicio_periodo: string
  fecha_fin_periodo:    string
  regimen:              'losep' | 'codigo_trabajo'
  anios_antiguedad:     number
  dias_generados:       number | string
  dias_utilizados:      number | string
  dias_saldo:           number | string
  saldo_acumulado:      number | string
  estado:               'abierto' | 'cerrado' | 'vencido'
  alerta_enviada:       boolean
  servidor?:            ServidorConRelaciones
  dias_vacaciones_aprobadas?: number | null
  dias_permisos_personales?:  number | null
}

export type ResumenPeriodos = {
  periodos:      PeriodoVacacion[]
  saldo_total:   number
  alerta_limite: boolean
  total_vacaciones_aprobadas?: number
  total_permisos_personales?:  number
}

export type ConsolidadoPermiso = {
  servidor_id:     number
  servidor_nombre: string
  cedula:          string
  unidad:          string
  total_permisos:  number
  total_minutos:   number
  tiempo_total:    string
  total_dias:      number
}

export type ConsolidadoPermisoResponse = {
  consolidado: ConsolidadoPermiso[]
  totales: {
    total_permisos: number
    total_minutos:  number
    total_dias:     number
  }
  filtros: {
    fecha_inicio: string
    fecha_fin:    string
    tipo:         string
  }
}

// -- Error de Axios tipado -------------------------
export type ApiError = {
  response?: {
    data?: {
      mensaje?:  string
      error?:    string
      errores?:  Record<string, string[]>
    }
    status?: number
  }
  message?: string
}

export function getApiErrorMessage(
  error: unknown,
  fallback = 'Operación fallida.'
): string {
  const e = error as ApiError
  const data = e?.response?.data

  // Si hay errores de validación específicos (422),
  // mostramos el primero de ellos en vez del mensaje
  // genérico "Los datos enviados no son válidos."
  if (data?.errores) {
    const primerCampo = Object.values(data.errores)[0]
    if (Array.isArray(primerCampo) && primerCampo.length > 0) {
      return primerCampo[0]
    }
  }

  return (
    data?.mensaje ??
    data?.error ??
    e?.message ??
    fallback
  )
}
