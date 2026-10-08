'use client'

import { Stack, Text } from '@mantine/core'
import { IconPrinter, IconX } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ESTADO_LABELS, TIPO_LABELS, TONO_ESTADO } from './permisos.constants'
import { diasParaVencer, duracion } from '../utils/horarioPermiso'
import { formatFecha } from '@/lib/fecha'
import { MotivoDeEstadoTexto } from './MotivoDeEstadoTexto'
import { EnSirha7Texto } from './EnSirha7Texto'
import type { DataTableColumn } from 'mantine-datatable'
import type { PermisoServidor } from '@/types/api'

interface Acciones {
  exportandoId: number | null
  onExportar:   (id: number) => void
  onAnular:     (p: PermisoServidor) => void
}

function PlazoRespaldo({ dias }: { dias: number }) {
  // De usted, como el resto de la aplicación: decía «Entrega el respaldo».
  const texto = dias <= 0
    ? 'Plazo del respaldo vencido'
    : dias === 1
      ? 'Entregue el respaldo hoy'
      : `Quedan ${dias} días para entregar el respaldo`

  return (
    <Text size="xs" c={SEMANTIC_COLOR[dias <= 1 ? 'danger' : 'warning']}>
      {texto}
    </Text>
  )
}

/**
 * Las columnas de «Mis permisos».
 *
 * Sin la columna del servidor —son todos del mismo— y con el motivo, que el
 * titular siempre puede leer. Se ofrece imprimir y, mientras está pendiente,
 * anular: el backend se lo permite al titular. Confirmar, rechazar o revertir
 * son trabajo de Recepción y de Talento Humano.
 */
export function getMisPermisosColumns(acciones: Acciones): DataTableColumn<PermisoServidor>[] {
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
      accessor: 'tipo',
      title: 'Tipo',
      // Lo mismo que en la tabla de Talento Humano: 147 px de etiqueta + 32.
      width: 185,
      render: ({ tipo }) => (
        <StatusBadge>
          {TIPO_LABELS[tipo] ?? tipo}
        </StatusBadge>
      ),
    },
    {
      accessor: 'fecha',
      title: 'Fecha',
      width: 110,
      render: ({ fecha }) => <Text size="sm">{fecha ? formatFecha(fecha) : '—'}</Text>,
    },
    {
      accessor: 'hora_inicio',
      title: 'Horario',
      width: 140,
      render: ({ hora_inicio, hora_fin }) => (
        <Stack gap={2}>
          <Text size="sm" ff="monospace">
            {hora_inicio.substring(0, 5)} — {hora_fin.substring(0, 5)}
          </Text>
          <StatusBadge size="xs">
            {duracion(hora_inicio, hora_fin)}
          </StatusBadge>
        </Stack>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 180,
      render: (p) => (
        <Stack gap={2}>
          <StatusBadge tone={TONO_ESTADO[p.estado] ?? 'neutral'}>
            {ESTADO_LABELS[p.estado] ?? p.estado}
          </StatusBadge>
          {/*
            Lo que más le importa al servidor de un pendiente: cuánto le queda
            para entregar el respaldo antes de que pase a falta injustificada.
            Va debajo del estado y no en una columna propia, que en los demás
            estados quedaba vacía y le quitaba al motivo el ancho que necesita.
          */}
          {p.estado === 'pendiente' && p.vence_en && (
            <PlazoRespaldo dias={diasParaVencer(p.vence_en)} />
          )}
          {/* Por qué se rechazó o anuló: sin esto, el servidor no sabía qué corregir. */}
          <MotivoDeEstadoTexto permiso={p} />
          <EnSirha7Texto permiso={p} />
        </Stack>
      ),
    },
    {
      accessor: 'observacion',
      title: 'Motivo',
      render: ({ observacion }) =>
        observacion ? (
          <Text size="sm" lineClamp={2} title={observacion}>{observacion}</Text>
        ) : (
          <Text size="sm" c="dimmed">—</Text>
        ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (p) => (
        <TableActions
          actions={[
            {
              label: acciones.exportandoId === p.id ? 'Exportando...' : 'Imprimir permiso',
              icon: <IconPrinter size={14} />,
              onClick: () => acciones.onExportar(p.id),
            },
            {
              // El servidor que ya no va a usar un permiso lo retira él mismo;
              // antes tenía que pedírselo a Talento Humano.
              label: 'Anular',
              icon: <IconX size={14} />,
              color: 'red',
              onClick: () => acciones.onAnular(p),
              hidden: p.estado !== 'pendiente',
            },
          ]}
        />
      ),
    },
  ]
}
