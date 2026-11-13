import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import { brechasDePerfilService } from '../services/brechasDePerfilService'

/** Las brechas de perfil, de toda la institución o de una gestión con sus subprocesos (P7, PR 17). */
export function useBrechasDePerfil(unidadId: number | null) {
  return useQuery({
    queryKey: ['brechas-de-perfil', unidadId],
    queryFn: () => brechasDePerfilService.informe(unidadId),
    staleTime: 1000 * 60,
  })
}

/** El informe en Excel, para el plan de capacitación. */
export function useExcelBrechasDePerfil() {
  const [descargando, setDescargando] = useState(false)

  const descargar = async (unidadId: number | null) => {
    setDescargando(true)
    try {
      guardarArchivo(await brechasDePerfilService.excel(unidadId), 'brechas-de-perfil.xlsx')
    } catch (error: unknown) {
      notificar.error('No se pudo generar el Excel', getApiErrorMessage(error))
    } finally {
      setDescargando(false)
    }
  }

  return { descargar, descargando }
}
