import api from "@/lib/axios";
import type { CargaFamiliarFormData } from '../schemas/cargaFamiliar.schema'
import type { DeclaracionFormData } from '../schemas/declaracion.schema'
import type { DiscapacidadFormData } from '../schemas/discapacidad.schema'
import type { EnfermedadFormData } from '../schemas/enfermedad.schema'
import type { HistorialAcademicoFormData } from '../schemas/historialAcademico.schema'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

/**
 * Campos editables mientras la acción de personal está en borrador. Excluye
 * 'tipo_movimiento' y 'subtipo_movimiento' a propósito: cambiar la naturaleza
 * del acto no es editarlo — el backend tampoco los acepta.
 */
/**
 * Payload de una transición. Los datos del vínculo solo aplican al pasar un
 * ingreso a 'registrada': se completan en el acto de aprobar, porque una
 * acción suscrita ya no se edita.
 */
export type TransicionarData = {
  estado: string
  dictamen_presupuestario_ref?: string | null
  numero_contrato?: string | null
  remuneracion_propuesta?: number | null
  partida_presupuestaria_id?: number | null
  puede_marcar?: boolean | null
  resolucion_numero?: string | null
  fecha_fin_propuesta?: string | null
}

export type ActualizarBorradorData = {
  descripcion?: string
  fecha_efectiva?: string
  fecha_inicio?: string | null
  fecha_fin?: string | null
  unidad_destino_id?: number | null
  puesto_destino_id?: number | null
  tipo_nombramiento_propuesto?: string | null
  remuneracion_propuesta?: number | null
  fecha_fin_propuesta?: string | null
  numero_contrato?: string | null
  partida_presupuestaria_id?: number | null
  puede_marcar?: boolean | null
  requiere_dictamen_medico?: boolean | null
  resolucion_numero?: string | null
  observacion?: string | null
  lugar_trabajo?: string | null
  caucionado?: boolean | null
  caucion_numero?: string | null
  caucion_fecha?: string | null
}
import type {
  ApiResponse,
  Servidor,
  ServidorParams,
  HistorialAcademicoServidor,
  CargaFamiliar,
  DeclaracionJuramentada,
  DiscapacidadServidor,
  EnfermedadCatastroficaServidor,
  DocumentoServidor,
  ServidorConRelaciones,
  DiscapacidadCargaFamiliar,
  EnfermedadCatastroficaCargaFamiliar,
  MovimientoPersonal,
} from "@/types/api";
import type { ServidorFormData } from "../schemas/servidor.schema";
import type { ServidorBasicoFormData } from "../schemas/servidorBasico.schema";

export const expedienteService = {
  // ── Servidores ──────────────────────────────────
  listar: (params?: ServidorParams) =>
    api
      .get<{
        exito: boolean;
        datos: ServidorConRelaciones[];
        meta: {
          pagina_actual: number;
          por_pagina: number;
          total: number;
          ultima_pagina: number;
        };
      }>("/expediente/servidores", { params })
      .then((r) => ({
        data: r.data.datos ?? [],
        total: r.data.meta?.total ?? 0,
        current_page: r.data.meta?.pagina_actual ?? 1,
      })),

  obtener: (id: number) =>
    api
      .get<ApiResponse<Servidor>>(`/expediente/servidores/${id}`)
      .then((r) => r.data.datos),

  crearBasico: (data: Partial<ServidorFormData>) =>
    api
      .post<ApiResponse<Servidor>>("/expediente/servidores/basico", data)
      .then((r) => r.data.datos),

  crear: (data: ServidorBasicoFormData) =>
    api
      .post<ApiResponse<Servidor>>("/expediente/servidores/basico", data)
      .then((r) => r.data.datos),

  editar: (
    id: number,
    data: Partial<ServidorBasicoFormData> | Partial<ServidorFormData>,
  ) =>
    api
      .put<ApiResponse<Servidor>>(`/expediente/servidores/${id}`, data)
      .then((r) => r.data.datos),

  exportarExcel: (params?: ServidorParams) =>
    api
      .get("/expediente/servidores-export/excel", {
        params, responseType: "blob",
      })
      .then((r) => r.data),

  exportarPdf: (params?: ServidorParams) =>
    api
      .get("/expediente/servidores-export/pdf", {
        params, responseType: "blob",
      })
      .then((r) => r.data),

  // ── Historial académico ─────────────────────────
  listarHistorialAcademico: (servidorId: number) =>
    api
      .get<
        ApiResponse<HistorialAcademicoServidor[]>
      >(`/expediente/servidores/${servidorId}/historial-academico`)
      .then((r) => r.data.datos ?? []),

  crearHistorialAcademico: (
    servidorId: number,
    data: FormData | HistorialAcademicoFormData,
  ) =>
    api
      .post<
        ApiResponse<HistorialAcademicoServidor>
      >(`/expediente/servidores/${servidorId}/historial-academico`, data)
      .then((r) => r.data.datos),

  editarHistorialAcademico: (
    servidorId: number,
    id: number,
    data: FormData | HistorialAcademicoFormData,
  ) =>
    api
      .put<
        ApiResponse<HistorialAcademicoServidor>
      >(`/expediente/servidores/${servidorId}/historial-academico/${id}`, data)
      .then((r) => r.data.datos),

  eliminarHistorialAcademico: (servidorId: number, id: number) =>
    api
      .delete<
        ApiResponse<void>
      >(`/expediente/servidores/${servidorId}/historial-academico/${id}`)
      .then((r) => r.data),

  // ── Cargas familiares ───────────────────────────
  listarCargasFamiliares: (servidorId: number) =>
    api
      .get<
        ApiResponse<CargaFamiliar[]>
      >(`/expediente/servidores/${servidorId}/cargas-familiares`)
      .then((r) => r.data.datos ?? []),

  crearCargaFamiliar: (servidorId: number, data: CargaFamiliarFormData) =>
    api
      .post<
        ApiResponse<CargaFamiliar>
      >(`/expediente/servidores/${servidorId}/cargas-familiares`, data)
      .then((r) => r.data.datos),

  editarCargaFamiliar: (
    servidorId: number,
    id: number,
    data: CargaFamiliarFormData,
  ) =>
    api
      .put<
        ApiResponse<CargaFamiliar>
      >(`/expediente/servidores/${servidorId}/cargas-familiares/${id}`, data)
      .then((r) => r.data.datos),

  eliminarCargaFamiliar: (servidorId: number, id: number) =>
    api
      .delete<
        ApiResponse<void>
      >(`/expediente/servidores/${servidorId}/cargas-familiares/${id}`)
      .then((r) => r.data),

  toggleEstadoCarga: (servidorId: number, id: number) =>
    api
      .post<ApiResponse<CargaFamiliar>>(
        `/expediente/servidores/${servidorId}/cargas-familiares/${id}/toggle-estado`
      )
      .then((r) => r.data.datos),

  // ── Declaraciones juramentadas ──────────────────
  listarDeclaraciones: (servidorId: number) =>
    api
      .get<
        ApiResponse<DeclaracionJuramentada[]>
      >(`/expediente/servidores/${servidorId}/declaraciones-juramentadas`)
      .then((r) => r.data.datos ?? []),

  crearDeclaracion: (servidorId: number, data: DeclaracionFormData) =>
    api
      .post<
        ApiResponse<DeclaracionJuramentada>
      >(`/expediente/servidores/${servidorId}/declaraciones-juramentadas`, data)
      .then((r) => r.data.datos),

  editarDeclaracion: (
    servidorId: number,
    id: number,
    data: DeclaracionFormData,
  ) =>
    api
      .put<
        ApiResponse<DeclaracionJuramentada>
      >(`/expediente/servidores/${servidorId}/declaraciones-juramentadas/${id}`, data)
      .then((r) => r.data.datos),

  eliminarDeclaracion: (servidorId: number, id: number) =>
    api
      .delete<
        ApiResponse<void>
      >(`/expediente/servidores/${servidorId}/declaraciones-juramentadas/${id}`)
      .then((r) => r.data),

  exportarDeclaraciones: (servidorId: number) =>
    api
      .get(
        `/expediente/servidores/${servidorId}/declaraciones-juramentadas/exportar`,
        { responseType: "blob" },
      )
      .then((r) => r.data),

  // ── Discapacidades ──────────────────────────────
  listarDiscapacidades: (servidorId: number) =>
    api
      .get<
        ApiResponse<DiscapacidadServidor[]>
      >(`/expediente/servidores/${servidorId}/discapacidades`)
      .then((r) => r.data.datos ?? []),

  crearDiscapacidad: (servidorId: number, data: DiscapacidadFormData) =>
    api
      .post<
        ApiResponse<DiscapacidadServidor>
      >(`/expediente/servidores/${servidorId}/discapacidades`, data)
      .then((r) => r.data.datos),

  editarDiscapacidad: (
    servidorId: number,
    id: number,
    data: DiscapacidadFormData,
  ) =>
    api
      .put<
        ApiResponse<DiscapacidadServidor>
      >(`/expediente/servidores/${servidorId}/discapacidades/${id}`, data)
      .then((r) => r.data.datos),

  eliminarDiscapacidad: (servidorId: number, id: number) =>
    api
      .delete<
        ApiResponse<void>
      >(`/expediente/servidores/${servidorId}/discapacidades/${id}`)
      .then((r) => r.data),

  // ── Enfermedades catastróficas ──────────────────
  listarEnfermedades: (servidorId: number) =>
    api
      .get<
        ApiResponse<EnfermedadCatastroficaServidor[]>
      >(`/expediente/servidores/${servidorId}/enfermedades`)
      .then((r) => r.data.datos ?? []),

  crearEnfermedad: (servidorId: number, data: EnfermedadFormData) =>
    api
      .post<
        ApiResponse<EnfermedadCatastroficaServidor>
      >(`/expediente/servidores/${servidorId}/enfermedades`, data)
      .then((r) => r.data.datos),

  editarEnfermedad: (
    servidorId: number,
    id: number,
    data: EnfermedadFormData,
  ) =>
    api
      .put<
        ApiResponse<EnfermedadCatastroficaServidor>
      >(`/expediente/servidores/${servidorId}/enfermedades/${id}`, data)
      .then((r) => r.data.datos),

  eliminarEnfermedad: (servidorId: number, id: number) =>
    api
      .delete<
        ApiResponse<void>
      >(`/expediente/servidores/${servidorId}/enfermedades/${id}`)
      .then((r) => r.data),

  // ── Documentos ──────────────────────────────────
  listarDocumentos: (servidorId: number) =>
    api
      .get<
        ApiResponse<DocumentoServidor[]>
      >(`/expediente/servidores/${servidorId}/documentos`)
      .then((r) => r.data.datos ?? []),

  subirDocumento: (servidorId: number, formData: FormData) =>
    api
      .post<
        ApiResponse<DocumentoServidor>
      >(`/expediente/servidores/${servidorId}/documentos`, formData, { headers: { "Content-Type": "multipart/form-data" } })
      .then((r) => r.data.datos),

  eliminarDocumento: (servidorId: number, documentoId: number) =>
    api
      .delete<
        ApiResponse<void>
      >(`/expediente/servidores/${servidorId}/documentos/${documentoId}`)
      .then((r) => r.data),

  descargarDocumento: (servidorId: number, documentoId: number) =>
    api
      .get(
        `/expediente/servidores/${servidorId}/documentos/${documentoId}/descargar`,
        { responseType: "blob" },
      )
      .then((r) => r.data),

  // ── Movimientos de personal / acciones de personal ──
  listarMovimientos: (servidorId: number) =>
    api
      .get<
        ApiResponse<MovimientoPersonal[]>
      >(`/expediente/servidores/${servidorId}/movimientos`)
      .then((r) => r.data.datos ?? []),

  crearMovimiento: (
    servidorId: number,
    data: MovimientoFormData,
  ) =>
    api
      .post<
        ApiResponse<MovimientoPersonal>
      >(`/expediente/servidores/${servidorId}/movimientos`, data)
      .then((r) => r.data.datos),

  /** Bandeja transversal: acciones de personal de todos los servidores. */
  listarBandejaMovimientos: (params?: {
    estado?: string
    tipo_movimiento?: string
    anio?: number
    per_page?: number
  }) =>
    api
      .get<
        ApiResponse<{ data: MovimientoPersonal[] }>
      >('/expediente/movimientos', { params })
      .then((r) => r.data.datos),

  /** Detalle completo de una acción, para el drawer de revisión. */
  obtenerMovimiento: (movimientoId: number) =>
    api
      .get<ApiResponse<MovimientoPersonal>>(`/expediente/movimientos/${movimientoId}`)
      .then((r) => r.data.datos),

  transicionarMovimiento: (
    movimientoId: number,
    data: TransicionarData,
  ) =>
    api
      .put<
        ApiResponse<MovimientoPersonal>
      >(`/expediente/movimientos/${movimientoId}/transicionar`, data)
      .then((r) => r.data.datos),

  /**
   * Edita una acción de personal que sigue en borrador. El backend rechaza
   * cualquier otro estado: una vez suscrita, el documento ya circuló.
   */
  actualizarBorradorMovimiento: (
    movimientoId: number,
    data: ActualizarBorradorData,
  ) =>
    api
      .put<
        ApiResponse<MovimientoPersonal>
      >(`/expediente/movimientos/${movimientoId}`, data)
      .then((r) => r.data.datos),

  descargarAccionPersonalPdf: (movimientoId: number) =>
    api
      .get(`/expediente/movimientos/${movimientoId}/accion-personal-pdf`, {
        responseType: "blob",
      })
      .then((r) => r.data),

  // ── Sub-condiciones de carga familiar ──────────
  crearDiscapacidadCarga: (cargaId: number, data: Omit<DiscapacidadFormData, 'numero_carnet_conadis'> & { numero_carnet_conadis?: string | null }) =>
    api
      .post<
        ApiResponse<DiscapacidadCargaFamiliar>
      >(`/expediente/cargas-familiares/${cargaId}/discapacidades`, data)
      .then((r) => r.data.datos),

  eliminarDiscapacidadCarga: (cargaId: number, id: number) =>
    api
      .delete<
        ApiResponse<void>
      >(`/expediente/cargas-familiares/${cargaId}/discapacidades/${id}`)
      .then((r) => r.data),

  crearEnfermedadCarga: (cargaId: number, data: Omit<EnfermedadFormData, 'codigo_cie10'> & { codigo_cie10?: string | null }) =>
    api
      .post<
        ApiResponse<EnfermedadCatastroficaCargaFamiliar>
      >(`/expediente/cargas-familiares/${cargaId}/enfermedades`, data)
      .then((r) => r.data.datos),

  eliminarEnfermedadCarga: (cargaId: number, id: number) =>
    api
      .delete<
        ApiResponse<void>
      >(`/expediente/cargas-familiares/${cargaId}/enfermedades/${id}`)
      .then((r) => r.data),
};
