'use client'

import { IconBan, IconCheck, IconEye, IconFileText, IconPackageExport, IconPackageImport, IconX } from '@tabler/icons-react'
import { MotivoModal, confirmar, type TableAction } from '@/components/ui'
import { usePrestamoTicMutations } from '../hooks/usePrestamosTic'
import type { PrestamoEquipo } from '../services/tipos'
import { EntregarPrestamoModal, RecibirPrestamoModal } from './EntregaRecepcionModal'

/** Lo que se hace sobre un préstamo desde la bandeja o su detalle. */
export type AccionPrestamo = { tipo: 'rechazar' | 'cancelar' | 'entregar' | 'recibir'; prestamo: PrestamoEquipo } | null

interface Opciones {
  /** `gestionar-prestamos`; sin él (el auditor), solo se consulta. */
  puedeGestionar: boolean
  abrir: (accion: NonNullable<AccionPrestamo>) => void
  aprobar: (p: PrestamoEquipo) => void
  acta: (p: PrestamoEquipo) => void
  /** En la bandeja; en el detalle ya se está viendo. */
  verDetalle?: (p: PrestamoEquipo) => void
}

/** Las acciones de un préstamo según su estado y quién lo mira. */
export function accionesPrestamo(p: PrestamoEquipo, { puedeGestionar, abrir, aprobar, acta, verDetalle }: Opciones): TableAction[] {
  const g = puedeGestionar
  return [
    { label: 'Ver detalle', icon: <IconEye size={14} />, hidden: !verDetalle, onClick: () => verDetalle?.(p) },
    { label: 'Aprobar', icon: <IconCheck size={14} />, hidden: !g || p.estado !== 'solicitado', onClick: () => aprobar(p) },
    { label: 'Rechazar', icon: <IconX size={14} />, color: 'red', hidden: !g || p.estado !== 'solicitado', onClick: () => abrir({ tipo: 'rechazar', prestamo: p }) },
    { label: 'Entregar', icon: <IconPackageExport size={14} />, hidden: !g || p.estado !== 'reservado', onClick: () => abrir({ tipo: 'entregar', prestamo: p }) },
    { label: 'Recibir', icon: <IconPackageImport size={14} />, hidden: !g || p.estado !== 'en_curso', onClick: () => abrir({ tipo: 'recibir', prestamo: p }) },
    { label: 'Acta', icon: <IconFileText size={14} />, hidden: !p.entregado_en, onClick: () => acta(p) },
    { label: 'Cancelar', icon: <IconBan size={14} />, color: 'red', hidden: !g || !p.cancelable, onClick: () => abrir({ tipo: 'cancelar', prestamo: p }) },
  ]
}

/** Aprobar se confirma; ocupa el horario de los equipos. */
export function useAprobarConConfirmacion() {
  const { aprobar } = usePrestamoTicMutations()
  return (p: PrestamoEquipo) => confirmar({
    title: `Aprobar ${p.folio}`,
    message: <>El horario queda reservado para <b>{p.servidor?.nombre}</b>. Las demás solicitudes de esos equipos a esa hora ya no se podrán aprobar.</>,
    confirmLabel: 'Aprobar',
    onConfirm: () => aprobar.mutate(p.id),
  })
}

/** Los modales de la acción elegida: uno abierto a la vez. */
export function PrestamoModalesTic({ accion, onClose }: { accion: AccionPrestamo; onClose: () => void }) {
  const { rechazar, cancelar } = usePrestamoTicMutations()

  if (!accion) return null
  const { tipo, prestamo } = accion
  if (tipo === 'entregar') return <EntregarPrestamoModal prestamo={prestamo} onClose={onClose} />
  if (tipo === 'recibir') return <RecibirPrestamoModal prestamo={prestamo} onClose={onClose} />

  const rechazo = tipo === 'rechazar'
  const mutacion = rechazo ? rechazar : cancelar
  return (
    <MotivoModal
      opened
      onClose={onClose}
      title={`${rechazo ? 'Rechazar' : 'Cancelar'} ${prestamo.folio}`}
      descripcion={rechazo
        ? <>El servidor <b>{prestamo.servidor?.nombre}</b> verá el motivo en «Mis préstamos».</>
        : <>El horario queda libre. El servidor <b>{prestamo.servidor?.nombre}</b> verá el motivo.</>}
      confirmLabel={rechazo ? 'Rechazar' : 'Cancelar el préstamo'}
      destructiva
      cargando={mutacion.isPending}
      onConfirm={(motivo) => mutacion.mutate({ id: prestamo.id, motivo }, { onSuccess: onClose })}
    />
  )
}
