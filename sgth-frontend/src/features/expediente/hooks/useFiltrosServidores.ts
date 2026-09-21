import { useCallback, useState } from 'react'
import type { EstadoContrato, ServidorParams, TipoNombramiento } from '@/types/api'

/**
 * En qué situación está la persona. Sustituye a tres filtros que se pisaban
 * entre sí —«Estado del servidor», «Vínculo laboral» y «Estado contrato»—: la
 * pregunta real es una sola y cada respuesta se traduce a su parámetro.
 */
export type Situacion = 'en_funciones' | 'sin_vinculo' | 'inactivos'

const SITUACION_A_PARAMS: Record<Situacion, ServidorParams> = {
  en_funciones: { en_funciones: true },
  sin_vinculo:  { pendiente_vinculacion: true },
  inactivos:    { estado: false },
}

export const SITUACION_OPTIONS = [
  { value: 'en_funciones', label: 'En funciones' },
  { value: 'sin_vinculo',  label: 'Sin vínculo registrado' },
  { value: 'inactivos',    label: 'Inactivos' },
]

/**
 * Filtros y página del listado de servidores.
 *
 * Cada setter vuelve a la página 1. Antes solo lo hacía el de vínculo: desde
 * la página 4, una búsqueda con tres resultados pedía la página 4 de esos
 * tres, venía vacía y la pantalla decía «No hay servidores registrados».
 */
export function useFiltrosServidores() {
  const [page, setPage] = useState(1)
  const [search, setSearchState] = useState('')
  const [situacion, setSituacion] = useState<Situacion | null>(null)
  const [contratoEstado, setContratoEstado] = useState<EstadoContrato | null>(null)
  const [unidadId, setUnidadId] = useState<number | null>(null)
  const [tipoNombramiento, setTipoNombramiento] = useState<TipoNombramiento | null>(null)
  const [anioIngreso, setAnioIngreso] = useState<number | null>(null)

  // Estable: la barra de filtros la llama desde un efecto con el texto ya
  // retrasado, y una función nueva en cada render lo volvería a disparar.
  const setSearch = useCallback((v: string) => {
    setSearchState(v)
    setPage(1)
  }, [])

  const conPaginaUno = <T,>(set: (v: T) => void) => (v: T) => {
    set(v)
    setPage(1)
  }

  const filtros: ServidorParams = {
    search: search || undefined,
    ...(situacion ? SITUACION_A_PARAMS[situacion] : {}),
    contrato_estado: contratoEstado ?? undefined,
    unidad_administrativa_id: unidadId ?? undefined,
    tipo_nombramiento: tipoNombramiento ?? undefined,
    anio_ingreso: anioIngreso ?? undefined,
  }

  /** Hay algún filtro puesto: un listado vacío es «sin resultados», no «sin servidores». */
  const hayFiltros = Object.values(filtros).some((v) => v !== undefined)

  /** Cuántos filtros secundarios hay puestos, para el botón que los despliega. */
  const filtrosSecundarios =
    [contratoEstado, unidadId, tipoNombramiento, anioIngreso]
      .filter((v) => v !== null).length

  return {
    page,
    setPage,
    filtros,
    hayFiltros,
    filtrosSecundarios,
    situacion,
    setSearch,
    setSituacion: conPaginaUno(setSituacion),
    setContratoEstado: conPaginaUno((v: string | null) => setContratoEstado(v as EstadoContrato | null)),
    setUnidadId: conPaginaUno(setUnidadId),
    setTipoNombramiento: conPaginaUno((v: string | null) => setTipoNombramiento(v as TipoNombramiento | null)),
    setAnioIngreso: conPaginaUno(setAnioIngreso),
  }
}
