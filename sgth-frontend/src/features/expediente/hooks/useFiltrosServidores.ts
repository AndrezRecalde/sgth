import { useCallback, useState } from 'react'
import type { EstadoContrato, ServidorParams, TipoNombramiento } from '@/types/api'

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
  const [contratoEstado, setContratoEstado] = useState<EstadoContrato | null>(null)
  const [enFunciones, setEnFunciones] = useState<boolean | null>(null)
  const [unidadId, setUnidadId] = useState<number | null>(null)
  const [tipoNombramiento, setTipoNombramiento] = useState<TipoNombramiento | null>(null)
  const [anioIngreso, setAnioIngreso] = useState<number | null>(null)
  const [pendienteVinculacion, setPendienteVinculacion] = useState<boolean | null>(null)

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
    contrato_estado: contratoEstado ?? undefined,
    en_funciones: enFunciones ?? undefined,
    unidad_administrativa_id: unidadId ?? undefined,
    tipo_nombramiento: tipoNombramiento ?? undefined,
    anio_ingreso: anioIngreso ?? undefined,
    pendiente_vinculacion: pendienteVinculacion ?? undefined,
  }

  /** Hay algún filtro puesto: un listado vacío es «sin resultados», no «sin servidores». */
  const hayFiltros = Object.values(filtros).some((v) => v !== undefined)

  return {
    page,
    setPage,
    filtros,
    hayFiltros,
    pendienteVinculacion,
    setSearch,
    setContratoEstado: conPaginaUno((v: string | null) => setContratoEstado(v as EstadoContrato | null)),
    setEnFunciones: conPaginaUno(setEnFunciones),
    setUnidadId: conPaginaUno(setUnidadId),
    setTipoNombramiento: conPaginaUno((v: string | null) => setTipoNombramiento(v as TipoNombramiento | null)),
    setAnioIngreso: conPaginaUno(setAnioIngreso),
    setPendienteVinculacion: conPaginaUno(setPendienteVinculacion),
  }
}
