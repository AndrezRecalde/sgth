'use client'

import { Stack, Text } from '@mantine/core'
import {
  IconEye, IconRefresh, IconStethoscope, IconUserOff,
} from '@tabler/icons-react'
import { confirmar, StatusBadge, TableActions } from '@/components/ui'
import { ESTADO_TURNO_LABELS, TONO_TURNO } from '../constants/turnos'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { AgendaMedica } from '../services/agendaService'

interface Acciones {
  onAtender:      (turno: AgendaMedica) => void
  onVerConsulta:  (turno: AgendaMedica) => void
  onNoPresentado: (id: number) => void
  onReactivar:    (id: number) => void
}

function nombrePaciente(turno: AgendaMedica): string {
  if (turno.servidor) {
    return `${turno.servidor.nombre} ${turno.servidor.apellido}`
  }
  if (turno.carga_familiar) {
    return `${turno.carga_familiar.nombres} ${turno.carga_familiar.apellidos}`
  }
  return '—'
}

export function getConsultasTurnosColumns(
  acciones: Acciones
): DataTableColumn<AgendaMedica>[] {
  return [
    {
      accessor: 'fecha',
      title:    'Fecha',
      width:    110,
      render: (t) => <Text size="sm">{formatFecha(t.fecha)}</Text>,
    },
    {
      accessor: 'folio',
      title:    'Folio',
      width:    140,
      render: (t) => (
        <Text size="sm" ff="monospace">{t.folio ?? '—'}</Text>
      ),
    },
    {
      accessor: 'paciente',
      title:    'Paciente',
      render: (t) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{nombrePaciente(t)}</Text>
          <Text size="xs" c="dimmed">
            {t.servidor_id ? 'Servidor' : 'Familiar'}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: 'tipo_atencion',
      title:    'Tipo',
      width:    130,
      render: (t) => (
        <StatusBadge>
          {t.tipo_atencion === 'medicina_general' ? 'General' : 'Odontología'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'triaje',
      title:    'Triaje',
      width:    90,
      render: (t) => (
        <Text
          size="xs"
          c={t.triaje ? 'emerald' : t.requiere_triaje ? 'amber' : 'dimmed'}
        >
          {t.triaje ? 'Listo' : t.requiere_triaje ? 'Pendiente' : '—'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    130,
      render: (t) => (
        <StatusBadge tone={TONO_TURNO[t.estado] ?? 'neutral'}>
          {ESTADO_TURNO_LABELS[t.estado] ?? t.estado}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (t) => {
        const esPendiente    = ['en_espera', 'en_sala'].includes(t.estado)
        const enConsulta     = t.estado === 'en_consulta'
        const esAtendido     = t.estado === 'atendido'
        const esNoPresentado = t.estado === 'no_presentado'
        const tieneTriaje    = !!t.triaje
        const puedeAtender   = (esPendiente || enConsulta) &&
          (tieneTriaje || !t.requiere_triaje)

        return (
          <TableActions actions={[
            ...(puedeAtender ? [{
              label:   enConsulta ? 'Continuar consulta' : 'Atender',
              icon:    <IconStethoscope size={14} />,
              onClick: () => acciones.onAtender(t),
            }] : []),
            ...((esAtendido || enConsulta) ? [{
              label:   'Ver consulta',
              icon:    <IconEye size={14} />,
              onClick: () => acciones.onVerConsulta(t),
            }] : []),
            ...(esPendiente ? [{
              label:   'No se presentó',
              icon:    <IconUserOff size={14} />,
              onClick: () => confirmar({
                title:   'Marcar como no presentado',
                message: 'El turno quedará marcado como no presentado. Podrás reactivarlo después.',
                confirmLabel: 'Marcar',
                onConfirm: () => acciones.onNoPresentado(t.id),
              }),
            }] : []),
            ...(esNoPresentado ? [{
              label:   'Reactivar turno',
              icon:    <IconRefresh size={14} />,
              onClick: () => confirmar({
                title:   'Reactivar turno',
                message: 'El turno volverá a la cola de atención del día.',
                confirmLabel: 'Reactivar',
                onConfirm: () => acciones.onReactivar(t.id),
              }),
            }] : []),
          ]} />
        )
      },
    },
  ]
}
