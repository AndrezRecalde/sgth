import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import { descripcionPuestoService, type DescripcionPuestoDatos } from '../services/descripcionPuestoService'

const clave = (puestoId: number | null) => ['puesto-descripcion', puestoId]

export function useDescripcionPuesto(puestoId: number | null) {
  return useQuery({
    queryKey: clave(puestoId),
    queryFn: () => descripcionPuestoService.obtener(puestoId!),
    enabled: !!puestoId,
    staleTime: 1000 * 60,
  })
}

export function useGuardarDescripcion(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (datos: DescripcionPuestoDatos) => descripcionPuestoService.guardar(puestoId, datos),
    onSuccess: (guardada) => {
      notificar.exito('Descripción guardada', 'La descripción del puesto quedó actualizada.')
      qc.setQueryData(clave(puestoId), guardada)
    },
    // El formulario pone en su campo el error de validación; el resto se notifica.
    onError: notificar.alFallarSalvoCampos('No se pudo guardar la descripción'),
  })
}

/** Abre la ficha del puesto en PDF; si el navegador bloquea la pestaña, la descarga. */
export function useFichaDelPuestoPdf() {
  const [abriendo, setAbriendo] = useState(false)

  const abrir = async (puestoId: number, nombre: string) => {
    setAbriendo(true)
    try {
      const blob = await descripcionPuestoService.pdf(puestoId)
      if (!abrirOGuardarArchivo(blob, `ficha-${nombre}.pdf`)) {
        notificar.aviso('La ficha se descargó', 'El navegador bloqueó la pestaña; la encontrará en Descargas.')
      }
    } catch (error: unknown) {
      notificar.error('No se pudo generar la ficha', getApiErrorMessage(error))
    } finally {
      setAbriendo(false)
    }
  }

  return { abrir, abriendo }
}
