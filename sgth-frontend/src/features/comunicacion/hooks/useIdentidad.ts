import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { clavesComunicacion } from '../constants/claves'
import { identidadService } from '../services/identidadService'
import type { DatosInstitucionales, VarianteIdentidad } from '../services/tiposIdentidad'
import { aDataUrl } from '@/lib/dataUrl'

/** Cada variante con su versión vigente, la que se ve hoy y las programadas; y los datos. */
export function useEstadoIdentidad() {
  return useQuery({
    queryKey: clavesComunicacion.identidad.estado,
    queryFn: identidadService.estado,
  })
}

export function useVersionesIdentidad(variante: VarianteIdentidad | null) {
  return useQuery({
    queryKey: clavesComunicacion.identidad.versiones(variante ?? 'isotipo'),
    queryFn: () => identidadService.versiones(variante ?? 'isotipo'),
    enabled: variante !== null,
  })
}

/** El archivo de una versión, listo para un `src`. Un archivo no cambia nunca. */
export function useArchivoVersion(versionId: number | null | undefined) {
  return useQuery({
    queryKey: clavesComunicacion.identidad.archivo(versionId ?? 0),
    queryFn: async () => aDataUrl(await identidadService.archivo(versionId ?? 0)),
    enabled: !!versionId,
    staleTime: Infinity,
    gcTime: 1000 * 60 * 30,
  })
}

/**
 * Subir, volver a una versión, anular y los datos. Todo cambia lo que ven el
 * sidebar y el login, así que se invalida también lo público.
 */
export function useMutacionesIdentidad() {
  const qc = useQueryClient()
  const refrescar = () => {
    qc.invalidateQueries({ queryKey: clavesComunicacion.identidad.todas })
    qc.invalidateQueries({ queryKey: clavesComunicacion.identidad.publica })
  }
  const avisoDeFecha = (desde: string) => (
    desde > new Date().toISOString().slice(0, 10) ? `Rige desde el ${formatFecha(desde)}.` : 'Ya es la vigente.'
  )

  const subir = useMutation({
    mutationFn: ({ variante, ...datos }: { variante: VarianteIdentidad; archivo: File; vigente_desde?: string | null; nota?: string | null }) =>
      identidadService.subir(variante, datos),
    onSuccess: ({ datos }) => {
      refrescar()
      notificar.exito(`${datos.variante_etiqueta} guardado`, avisoDeFecha(datos.vigente_desde))
    },
    // Los errores de campo los pone el formulario; el resto, aquí.
    onError: notificar.alFallarSalvoCampos('No se pudo guardar el logo'),
  })

  const reutilizar = useMutation({
    mutationFn: ({ versionId, vigenteDesde }: { versionId: number; vigenteDesde?: string | null }) =>
      identidadService.reutilizar(versionId, vigenteDesde),
    onSuccess: ({ datos }) => {
      refrescar()
      notificar.exito('Se volvió a esa versión', avisoDeFecha(datos.vigente_desde))
    },
    onError: notificar.alFallar('No se pudo volver a esa versión'),
  })

  const anular = useMutation({
    mutationFn: (versionId: number) => identidadService.anular(versionId),
    onSuccess: () => {
      refrescar()
      notificar.exito('Versión programada anulada', 'No llegará a regir.')
    },
    onError: notificar.alFallar('No se pudo anular la versión'),
  })

  const actualizarDatos = useMutation({
    mutationFn: (datos: DatosInstitucionales) => identidadService.actualizarDatos(datos),
    onSuccess: () => {
      refrescar()
      notificar.exito('Datos de la institución guardados', 'Salen así en las pantallas y en los documentos.')
    },
    onError: notificar.alFallarSalvoCampos('No se pudieron guardar los datos de la institución'),
  })

  return { subir, reutilizar, anular, actualizarDatos }
}
