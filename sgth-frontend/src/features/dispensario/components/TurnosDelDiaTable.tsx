'use client'

import { TONO_TURNO } from '../constants/turnos'
import { confirmar, StatusBadge } from '@/components/ui'
import {
  Stack, Group, Text, Button,
  ActionIcon, 
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import {
  IconStethoscope, IconUserOff,
  IconRefresh, IconEye, IconX,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useTurnosDelDia, useAccionesTurno } from '../hooks/useAgenda'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import type { AgendaMedica, EstadoAgenda } from '../services/agendaService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  onAtender:     (turno: AgendaMedica) => void
  onVerConsulta: (turno: AgendaMedica) => void
}

const ETIQUETA_ESTADO: Record<EstadoAgenda, string> = {
  en_espera:     'En espera',
  en_sala:       'En sala',
  en_consulta:   'En consulta',
  atendido:      'Atendido',
  no_presentado: 'No se presentó',
  cancelado:     'Cancelado',
}

function getNombrePaciente(turno: AgendaMedica): string {
  if (turno.servidor) {
    return `${turno.servidor.nombre} ${turno.servidor.apellido}`
  }
  if (turno.carga_familiar) {
    return `${turno.carga_familiar.nombres} ${turno.carga_familiar.apellidos}`
  }
  return '—'
}

function fromDate(d: Date | string | null): string | undefined {
  if (!d) return undefined
  if (typeof d === 'string') return d.slice(0, 10)
  if (!(d instanceof Date) || isNaN(d.getTime())) return undefined
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

export function TurnosDelDiaTable({ onAtender, onVerConsulta }: Props) {
  const [rango, setRango] = useState<[Date | null, Date | null]>([null, null])
  const [filtroActivo, setFiltroActivo] =
    useState<{ fecha_desde?: string; fecha_hasta?: string } | undefined>(undefined)

  const params = filtroActivo ?? undefined
  const { data: turnos = [], isLoading } = useTurnosDelDia(params)
  const { noPresentado, reactivar } = useAccionesTurno()

  const atendidos = turnos.filter(t => t.estado === 'atendido').length
  const enEspera  = turnos.filter(t =>
    ['en_espera', 'en_sala', 'en_consulta'].includes(t.estado)
  ).length

  const handleFiltrar = () => {
    const [inicio, fin] = rango
    const desde = fromDate(inicio as Date | string | null)
    if (desde) {
      setFiltroActivo({
        fecha_desde: desde,
        fecha_hasta: fromDate((fin ?? inicio) as Date | string | null)
          ?? desde,
      })
    }
  }

  const handleLimpiar = () => {
    setRango([null, null])
    setFiltroActivo(undefined)
  }

  const columns: DataTableColumn<AgendaMedica>[] = [
    {
      accessor: 'fecha',
      title:    'Fecha',
      width:    110,
      render: (t) => (
        <Text size="sm">
          {new Date(t.fecha).toLocaleDateString('es-EC', {
            day: '2-digit', month: 'short', year: 'numeric',
          })}
        </Text>
      ),
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
          <Text size="sm" fw={500}>
            {getNombrePaciente(t)}
          </Text>
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
          {t.tipo_atencion === 'medicina_general'
            ? 'General' : 'Odontología'}
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
      render: (t) => {
        return (
          <StatusBadge tone={TONO_TURNO[t.estado] ?? 'neutral'}>
            {ETIQUETA_ESTADO[t.estado] ?? t.estado}
          </StatusBadge>
        )
      },
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
              onClick: () => onAtender(t),
            }] : []),
            ...((esAtendido || enConsulta) ? [{
              label:   'Ver consulta',
              icon:    <IconEye size={14} />,
              onClick: () => onVerConsulta(t),
            }] : []),
            ...(esPendiente ? [{
              label:   'No se presentó',
              icon:    <IconUserOff size={14} />,
              onClick: () => confirmar({
                title:   'Marcar como no presentado',
                message: 'El turno quedará marcado como no presentado. Podrás reactivarlo después.',
                confirmLabel: 'Marcar',
                onConfirm: () => noPresentado.mutate(t.id),
              }),
            }] : []),
            ...(esNoPresentado ? [{
              label:   'Reactivar turno',
              icon:    <IconRefresh size={14} />,
              onClick: () => confirmar({
                title:   'Reactivar turno',
                message: 'El turno volverá a la cola de atención del día.',
                confirmLabel: 'Reactivar',
                onConfirm: () => reactivar.mutate(t.id),
              }),
            }] : []),
          ]} />
        )
      },
    },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="wrap" gap="sm">
        <Group gap="xs">
          <DatePickerInput
            type="range"
            placeholder="Filtrar por rango de fechas"
            valueFormat="DD/MM/YYYY"
            clearable
            value={rango}
            onChange={(v) => setRango(v as [Date | null, Date | null])}
            style={{ width: 280 }}
          />
          <Button
            variant="light"
            onClick={handleFiltrar}
            disabled={!rango[0]}
          >
            Filtrar
          </Button>
          {filtroActivo && (
            <ActionIcon
              variant="subtle"
              onClick={handleLimpiar}
            >
              <IconX size={14} />
            </ActionIcon>
          )}
        </Group>

        <Group gap="xs">
          <StatusBadge tone="success">
            {atendidos} atendido{atendidos !== 1 ? 's' : ''}
          </StatusBadge>
          <StatusBadge tone="warning">
            {enEspera} en espera
          </StatusBadge>
        </Group>
      </Group>

      {turnos.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconStethoscope}
          title="Sin turnos"
          description={filtroActivo
            ? "No hay turnos en el rango seleccionado."
            : "No tienes pacientes asignados para hoy."}
        />
      ) : (
        <SgthTable
          records={turnos}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}
    </Stack>
  )
}
