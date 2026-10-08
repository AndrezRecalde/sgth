'use client'

import {
  IconArrowBackUp, IconCheck, IconFingerprint, IconPrinter, IconShieldCheck, IconX,
} from '@tabler/icons-react'
import type { TableAction } from '@/components/ui'
import { ESTADOS_CONFIRMADOS, TIPOS_TRABAJO_SOCIAL } from './permisos.constants'
import type { PermisoServidor } from '@/types/api'
import type { AccionesPermiso } from '../hooks/useAccionesPermiso'

/*
| Las acciones de cada fila de la tabla de permisos de Talento Humano.
|
| Aparte de las columnas, que con «Aprobar en Sirha7» pasaban del límite de
| tamaño. Cada acción se ofrece solo a quien el backend se la permite (ver
| `useAccionesPermiso`).
*/

export interface ColumnActions {
  exportandoId: number | null
  /** Qué acciones le corresponden al usuario: la misma regla que la policy. */
  puede:        AccionesPermiso
  onExportar:   (id: number) => void
  onConfirmar:  (folio: string) => void
  onValidarTs:  (id: number) => void
  onAnular:     (p: PermisoServidor) => void
  onRechazar:   (p: PermisoServidor) => void
  onRevertir:   (p: PermisoServidor) => void
  onAprobarSirha7: (p: PermisoServidor) => void
}

export function accionesDelPermiso(p: PermisoServidor, actions: ColumnActions): TableAction[] {
  const estado = p.estado as string
  const pendiente = estado === 'pendiente'

  return [
    {
      label: actions.exportandoId === p.id ? 'Exportando...' : 'Imprimir permiso',
      icon: <IconPrinter size={14} />,
      onClick: () => actions.onExportar(p.id),
    },
    {
      label: 'Confirmar recepción',
      icon: <IconCheck size={14} />,
      onClick: () => p.folio && actions.onConfirmar(p.folio),
      hidden: !pendiente || !actions.puede.confirmar,
    },
    {
      label: 'Rechazar documento',
      icon: <IconX size={14} />,
      color: 'red',
      onClick: () => actions.onRechazar(p),
      hidden: !pendiente || !actions.puede.rechazar,
    },
    {
      // Desde la fecha de corte validar también registra en Sirha7:
      // lo hace la acción de abajo, en un solo paso.
      label: 'Validar Trabajo Social',
      icon: <IconShieldCheck size={14} />,
      onClick: () => actions.onValidarTs(p.id),
      hidden:
        !actions.puede.validarTs ||
        estado !== 'activo' ||
        !!p.pendiente_sirha7 ||
        !TIPOS_TRABAJO_SOCIAL.includes(p.tipo as string),
    },
    {
      label: TIPOS_TRABAJO_SOCIAL.includes(p.tipo as string)
        ? 'Validar y aprobar en Sirha7'
        : 'Aprobar en Sirha7',
      icon: <IconFingerprint size={14} />,
      onClick: () => actions.onAprobarSirha7(p),
      hidden: !actions.puede.aprobarSirha7(p),
    },
    {
      label: 'Revertir confirmación',
      icon: <IconArrowBackUp size={14} />,
      onClick: () => actions.onRevertir(p),
      hidden: !actions.puede.revertir || !ESTADOS_CONFIRMADOS.includes(estado),
    },
    {
      // Pide el motivo en el mismo modal que rechazar y revertir: el
      // backend lo exige y lo guarda.
      label: 'Anular',
      icon: <IconX size={14} />,
      color: 'red',
      onClick: () => actions.onAnular(p),
      hidden: !pendiente || !actions.puede.anular(p),
    },
  ]
}
