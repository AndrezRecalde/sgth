import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { plural } from '@/lib/plural'
import { clavesConsumibles } from '../constants/claves'
import {
  consumiblesService,
  type AjusteData, type AperturaData, type EntregaData, type FiltrosConsumibles, type GuardarConsumibleData, type IngresoData,
} from '../services/consumiblesService'

export const useConsumibles = (filtros: FiltrosConsumibles = {}, enabled = true) =>
  useQuery({ queryKey: clavesConsumibles.lista(filtros), queryFn: () => consumiblesService.listar(filtros), enabled })

export const useConsumible = (id: number) =>
  useQuery({ queryKey: clavesConsumibles.uno(id), queryFn: () => consumiblesService.obtener(id), enabled: id > 0 })

export const useKardex = (id: number, desde: string | null, hasta: string | null) =>
  useQuery({ queryKey: clavesConsumibles.kardex(id, desde, hasta), queryFn: () => consumiblesService.kardex(id, desde, hasta), enabled: id > 0 })

export const useConsumiblesDelEquipo = (bienId: number) =>
  useQuery({ queryKey: clavesConsumibles.delEquipo(bienId), queryFn: () => consumiblesService.delEquipo(bienId), enabled: bienId > 0 })

/** «Tóner HP CE390A negro» → «toner-hp-ce390a-negro». */
const aNombreDeArchivo = (texto: string) =>
  // Sin las tildes: NFD las separa en marcas combinantes (de 0x300 a 0x36f) que se descartan.
  [...texto.normalize('NFD')].filter((c) => c.charCodeAt(0) < 0x300 || c.charCodeAt(0) > 0x36f).join('')
    .replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-|-$/g, '').toLowerCase()

/** El kárdex en Excel, con las mismas fechas que en pantalla (5.4d). */
export const useDescargaKardex = () =>
  useMutation({
    mutationFn: async ({ id, desde, hasta, descripcion }: { id: number; desde: string | null; hasta: string | null; descripcion: string }) =>
      guardarArchivo(await consumiblesService.kardexExcel(id, desde, hasta), `kardex-${aNombreDeArchivo(descripcion)}.xlsx`),
    onError: notificar.alFallar('No se pudo descargar el kárdex en Excel'),
  })

/** El catálogo: crear, editar y la compatibilidad. Todo cambia listados, detalle y fichas. */
export function useConsumibleMutations(id = 0) {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesConsumibles.todos })

  const crear = useMutation({
    mutationFn: (data: GuardarConsumibleData) => consumiblesService.crear(data),
    onSuccess: (c) => {
      notificar.exito('Consumible registrado', c?.descripcion)
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el consumible'),
  })
  const actualizar = useMutation({
    mutationFn: (data: GuardarConsumibleData) => consumiblesService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Consumible actualizado')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo actualizar el consumible'),
  })
  const agregarModelo = useMutation({
    mutationFn: ({ consumibleId, marcaId, modelo }: { consumibleId: number; marcaId: number; modelo: string }) =>
      consumiblesService.agregarModelo(consumibleId, marcaId, modelo),
    onSuccess: () => {
      notificar.exito('Modelo agregado a los compatibles')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo agregar el modelo compatible'),
  })
  const quitarModelo = useMutation({
    mutationFn: (modeloId: number) => consumiblesService.quitarModelo(id, modeloId),
    onSuccess: () => {
      notificar.exito('Modelo quitado de los compatibles')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo quitar el modelo compatible'),
  })

  return { crear, actualizar, agregarModelo, quitarModelo }
}

/** Los movimientos: cada uno cambia el saldo, el kárdex y la ficha del equipo. */
export function useMovimientoMutations(id = 0) {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesConsumibles.todos })

  const apertura = useMutation({
    mutationFn: (data: AperturaData) => consumiblesService.apertura(id, data),
    onSuccess: () => {
      notificar.exito('Apertura registrada')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la apertura'),
  })
  const ajuste = useMutation({
    mutationFn: (data: AjusteData) => consumiblesService.ajuste(id, data),
    onSuccess: () => {
      notificar.exito('Ajuste registrado')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el ajuste'),
  })
  const ingreso = useMutation({
    mutationFn: (data: IngresoData) => consumiblesService.ingreso(data),
    onSuccess: (hechos) => {
      notificar.exito('Ingreso registrado', plural(hechos.length, 'consumible', 'consumibles'))
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el ingreso'),
  })
  const entrega = useMutation({
    mutationFn: (data: EntregaData) => consumiblesService.entrega(data),
    onSuccess: (hechos) => {
      notificar.exito('Entrega registrada', plural(hechos.length, 'consumible', 'consumibles'))
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la entrega'),
  })
  const anular = useMutation({
    mutationFn: ({ movimientoId, motivo }: { movimientoId: number; motivo: string }) => consumiblesService.anular(movimientoId, motivo),
    onSuccess: () => {
      notificar.exito('Movimiento anulado', 'Deja de contar, pero sigue en el kárdex.')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo anular el movimiento'),
  })

  return { apertura, ajuste, ingreso, entrega, anular }
}
