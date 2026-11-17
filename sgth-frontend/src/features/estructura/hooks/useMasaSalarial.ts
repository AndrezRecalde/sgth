import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import type { MasaSalarialParams } from '@/types/api'
import { masaSalarialService } from '../services/masaSalarialService'

/**
 * La masa salarial a una fecha de corte. Cambia cuando se registra un acto o
 * un contrato, no cada minuto. Al cambiar un filtro se queda lo anterior en
 * pantalla mientras llega lo nuevo, para que la página no salte.
 */
export function useMasaSalarial(params: MasaSalarialParams) {
  return useQuery({
    queryKey: ['masa-salarial', params],
    queryFn: () => masaSalarialService.resumen(params),
    staleTime: 1000 * 60 * 5,
    placeholderData: keepPreviousData,
  })
}

/** El Excel con los mismos filtros que la pantalla, con la fecha de corte en el nombre. */
export function useDescargarMasaSalarial() {
  const [descargando, setDescargando] = useState(false)

  const descargar = async (params: MasaSalarialParams) => {
    setDescargando(true)
    try {
      guardarArchivo(await masaSalarialService.excel(params), `masa_salarial_${params.corte}.xlsx`)
    } catch (e) {
      notificar.error('No se pudo descargar el Excel de la masa salarial', getApiErrorMessage(e))
    } finally {
      setDescargando(false)
    }
  }

  return { descargar, descargando }
}
