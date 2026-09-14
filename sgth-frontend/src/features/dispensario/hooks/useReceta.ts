import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import React from 'react'
import { recetaService } from '../services/recetaService'
import { getApiErrorMessage } from '@/types/api'
import type { EmitirRecetaData } from '../services/recetaService'
import { notificar } from '@/components/ui'

export function useEmitirReceta(consultaId?: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: EmitirRecetaData) =>
      recetaService.emitir(data),
    onSuccess: (result) => {
      if (result?.alertas_alergias?.length) {
        result.alertas_alergias.forEach(alerta =>
          notificar.aviso('Alerta de alergia', alerta, { autoClose: false })
        )
      }
      notificar.exito('Receta emitida', 'La receta médica fue registrada correctamente.')
      qc.invalidateQueries({ queryKey: ['consultas'] })
      if (consultaId) {
        qc.invalidateQueries({
          queryKey: ['recetas', 'consulta', consultaId],
        })
      }
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useAccionesItem(consultaId: number) {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({
      queryKey: ['recetas', 'consulta', consultaId],
    })

  const onError = (error: unknown) =>
    notificar.error('Error', getApiErrorMessage(error))

  const actualizarItem = useMutation({
    mutationFn: ({ recetaId, itemId, data }: {
      recetaId: number
      itemId:   number
      data: {
        cantidad_prescrita: number
        dosis:       string
        frecuencia:  string
        duracion:    string
        observaciones?: string | null
      }
    }) => recetaService.actualizarItem(recetaId, itemId, data),
    onSuccess: () => {
      notificar.exito('Ítem actualizado', 'El medicamento fue actualizado.')
      invalidar()
    },
    onError,
  })

  const quitarItem = useMutation({
    mutationFn: ({ recetaId, itemId }: {
      recetaId: number; itemId: number
    }) => recetaService.quitarItem(recetaId, itemId),
    onSuccess: () => {
      notificar.exito('Ítem eliminado', 'El medicamento fue removido de la receta.')
      invalidar()
    },
    onError,
  })

  return { actualizarItem, quitarItem }
}

export function useRecetasFarmacia(params?: {
  fecha_desde?: string
  fecha_hasta?: string
  medico_id?:   number
  estado?:      string
  page?:        number
  per_page?:    number
}) {
  return useQuery({
    queryKey: ['recetas', 'farmacia', params],
    queryFn:  () => recetaService.listarFarmacia(params),
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
    // La página anterior se queda a la vista mientras llega la siguiente; sin
    // esto la tabla parpadea a vacío en cada salto de página.
    placeholderData: (anterior) => anterior,
  })
}

/**
 * Abre el impreso de la receta.
 *
 * Se abre en una pestaña en vez de descargarse, al contrario que el
 * certificado: lo que se hace con una receta es imprimirla ahí mismo para
 * dársela al paciente, y bajarla al disco añade un paso y deja el documento
 * clínico tirado en Descargas.
 */
export function useRecetaPdf() {
  const [abriendo, setAbriendo] = React.useState<number | null>(null)

  const abrir = async (id: number) => {
    setAbriendo(id)
    try {
      const blob = await recetaService.descargarPdf(id)
      const url  = URL.createObjectURL(blob)

      // La ventana se abre dentro del gesto del clic, si no el navegador la
      // toma por emergente y la bloquea.
      const ventana = window.open(url, '_blank')

      if (!ventana) {
        notificar.exito(
          'Permite las ventanas emergentes',
          'El navegador bloqueó la pestaña con la receta.',
        )
      }

      // Se revoca tarde: antes la pestaña recién abierta se quedaba sin nada
      // que mostrar.
      setTimeout(() => URL.revokeObjectURL(url), 60_000)
    } catch (error: unknown) {
      notificar.error('No se pudo generar la receta', getApiErrorMessage(error))
    } finally {
      setAbriendo(null)
    }
  }

  return { abrir, abriendo }
}

export function useAnularReceta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      recetaService.anular(id, motivo),
    onSuccess: () => {
      notificar.exito('Receta anulada', 'La receta ya no aparecerá pendiente de entrega.')
      // Anular no mueve stock: lo entregado ya salió y su egreso sigue en pie.
      qc.invalidateQueries({ queryKey: ['recetas'] })
      qc.invalidateQueries({ queryKey: ['consultas'] })
    },
    onError: (error: unknown) =>
      notificar.error('No se pudo anular', getApiErrorMessage(error)),
  })
}

export function useDespacharReceta() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: {
      id:   number
      data: import('../services/recetaService').DespacharRecetaData
    }) => recetaService.despachar(id, data),
    onSuccess: () => {
      notificar.exito(
        'Receta despachada',
        'Los medicamentos fueron despachados correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['recetas'] })
      // Despachar descuenta existencias: el listado de Farmacia, el kardex y
      // el contador de stock bajo del menú cuelgan de esta clave y quedaban
      // mostrando el stock de antes de la entrega.
      qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })
      // Y lo que se agotó al despachar ya no debe ofrecerse al recetar.
      qc.invalidateQueries({ queryKey: ['medicinas-buscar'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error al despachar', getApiErrorMessage(error)),
  })
}
