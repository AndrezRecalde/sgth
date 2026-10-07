'use client'

import { Stack, Text } from '@mantine/core'
import {
  IconArrowBackUp, IconCheck, IconPrinter, IconShieldCheck, IconX,
} from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import {
  ESTADOS_CONFIRMADOS, ESTADO_LABELS, TIPO_LABELS, TIPOS_TRABAJO_SOCIAL, TONO_ESTADO,
} from './permisos.constants'
import type { DataTableColumn } from 'mantine-datatable'
import type { PermisoServidor } from '@/types/api'
import type { AccionesPermiso } from '../hooks/useAccionesPermiso'
import { diasParaVencer, duracion } from '../utils/horarioPermiso'
import { formatFecha } from '@/lib/fecha'
import { MotivoDeEstadoTexto } from './MotivoDeEstadoTexto'

interface ColumnActions {
  exportandoId: number | null
  /** Qué acciones le corresponden al usuario: la misma regla que la policy. */
  puede:        AccionesPermiso
  onExportar:   (id: number) => void
  onConfirmar:  (folio: string) => void
  onValidarTs:  (id: number) => void
  onAnular:     (p: PermisoServidor) => void
  onRechazar:   (p: PermisoServidor) => void
  onRevertir:   (p: PermisoServidor) => void
}

export function getPermisosColumns(
  actions: ColumnActions
): DataTableColumn<PermisoServidor>[] {
  return [
    {
      accessor: 'folio',
      title: 'Folio',
      width: 145,
      render: ({ folio }) => (
        <Text size="sm" ff="monospace" fw={500}>{folio ?? '—'}</Text>
      ),
    },
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (p) => {
        const s = p.servidor
        if (!s) return <Text size="sm" c="dimmed">—</Text>

        return <Text size="sm">{[s.apellido, s.nombre].filter(Boolean).join(' ')}</Text>
      },
    },
    {
      accessor: 'tipo',
      title: 'Tipo',
      // «CALAMIDAD DOMÉSTICA» mide unos 170 px y la celda lleva 32 de
      // relleno. Con las etiquetas cortas de antes («CALAMIDAD») bastaban 130.
      width: 210,
      render: ({ tipo }) => (
        <StatusBadge>
          {TIPO_LABELS[tipo as string] ?? tipo}
        </StatusBadge>
      ),
    },
    {
      accessor: 'fecha',
      title: 'Fecha',
      width: 110,
      render: ({ fecha }) => (
        <Text size="sm">
          {fecha
            ? formatFecha(fecha)
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'hora_inicio',
      title: 'Horario / Tiempo',
      width: 140,
      render: ({ hora_inicio, hora_fin }) => {
        if (!hora_inicio || !hora_fin) return <Text size="sm" c="dimmed">—</Text>

        return (
          <Stack gap={2}>
            <Text size="sm" ff="monospace">
              {hora_inicio.substring(0, 5)} — {hora_fin.substring(0, 5)}
            </Text>
            <StatusBadge size="xs">
              {duracion(hora_inicio, hora_fin)}
            </StatusBadge>
          </Stack>
        )
      },
    },
    {
      // El plazo de 72 horas laborables no se veía en ningún lado: nadie sabía
      // a cuánto estaba un permiso de convertirse en falta injustificada.
      accessor: 'vence_en',
      title: 'Vence',
      width: 110,
      render: ({ estado, vence_en }) => {
        if (estado !== 'pendiente' || !vence_en) {
          return <Text size="sm" c="dimmed">—</Text>
        }

        const dias = diasParaVencer(vence_en)

        return (
          <StatusBadge tone={dias <= 1 ? 'danger' : 'warning'}>
            {dias <= 0 ? 'Vencido' : dias === 1 ? 'Hoy' : `${dias} días`}
          </StatusBadge>
        )
      },
    },
    {
      accessor: 'estado',
      title: 'Estado',
      // «FALTA INJUSTIFICADA» mide 133 px: con 140 de celda y 32 de relleno
      // desbordaba 25.
      width: 180,
      render: (p) => (
        <Stack gap={2}>
          <StatusBadge tone={TONO_ESTADO[p.estado as string] ?? 'neutral'}>
            {ESTADO_LABELS[p.estado as string] ?? p.estado}
          </StatusBadge>
          <MotivoDeEstadoTexto permiso={p} />
        </Stack>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (p) => {
        const estado = p.estado as string
        const pendiente = estado === 'pendiente'

        return (
          <TableActions
            actions={[
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
                label: 'Validar Trabajo Social',
                icon: <IconShieldCheck size={14} />,
                onClick: () => actions.onValidarTs(p.id),
                hidden:
                  !actions.puede.validarTs ||
                  estado !== 'activo' ||
                  !TIPOS_TRABAJO_SOCIAL.includes(p.tipo as string),
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
            ]}
          />
        )
      },
    },
  ]
}
