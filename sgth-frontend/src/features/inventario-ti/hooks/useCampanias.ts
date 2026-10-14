import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { plural } from '@/lib/plural'
import { clavesInventario } from '../constants/claves'
import { campaniasService, type CampaniaData, type NuevaCampaniaData, type RealizarData } from '../services/campaniasService'

export const useCampanias = () =>
  useQuery({ queryKey: clavesInventario.campanias.todas, queryFn: campaniasService.listar })

export const useCampania = (id: number) =>
  useQuery({ queryKey: clavesInventario.campanias.una(id), queryFn: () => campaniasService.obtener(id), enabled: id > 0 })

/**
 * Los pasos de una campaña. Marcar lo hecho registra mantenimientos en las
 * fichas: se invalida todo el inventario, no solo la campaña.
 */
export function useCampaniaMutations(id = 0) {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesInventario.campanias.todas })

  const crear = useMutation({
    mutationFn: (data: NuevaCampaniaData) => campaniasService.crear(data),
    onSuccess: () => refrescar(),
    onError: notificar.alFallarSalvoCampos('No se pudo crear la campaña'),
  })
  const actualizar = useMutation({
    mutationFn: (data: CampaniaData) => campaniasService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Campaña actualizada')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo actualizar la campaña'),
  })
  const agregar = useMutation({
    mutationFn: (bienes: number[]) => campaniasService.agregar(id, bienes),
    onSuccess: (r) => {
      notificar.exito(r.agregados ? `${plural(r.agregados, 'equipo agregado', 'equipos agregados')}` : 'Sin cambios', r.agregados ? undefined : r.mensaje)
      refrescar()
    },
    onError: notificar.alFallar('No se pudieron agregar los equipos'),
  })
  const quitar = useMutation({
    mutationFn: (itemId: number) => campaniasService.quitar(id, itemId),
    onSuccess: () => {
      notificar.exito('Equipo quitado de la campaña')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo quitar el equipo'),
  })
  const realizar = useMutation({
    mutationFn: (data: RealizarData) => campaniasService.realizar(id, data),
    onSuccess: (r) => {
      notificar.exito(plural(r.hechos, 'equipo hecho', 'equipos hechos'), 'El mantenimiento quedó en la ficha de cada uno.')
      qc.invalidateQueries({ queryKey: clavesInventario.raiz })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el mantenimiento'),
  })
  const omitir = useMutation({
    mutationFn: ({ items, motivo }: { items: number[]; motivo: string }) => campaniasService.omitir(id, items, motivo),
    onSuccess: (r) => {
      notificar.exito(plural(r.omitidos, 'equipo omitido', 'equipos omitidos'))
      refrescar()
    },
    onError: notificar.alFallar('No se pudieron omitir los equipos'),
  })
  const cerrar = useMutation({
    mutationFn: () => campaniasService.cerrar(id),
    onSuccess: () => {
      notificar.exito('Campaña cerrada', 'Lo pendiente quedó como no realizado.')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo cerrar la campaña'),
  })

  return { crear, actualizar, agregar, quitar, realizar, omitir, cerrar }
}
