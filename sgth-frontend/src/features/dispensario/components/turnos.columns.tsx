'use client'

import {
  TONO_TURNO, ESTADO_TURNO_LABELS, turnoCerrado,
} from '../constants/turnos'
import { Text, Group, Stack } from '@mantine/core'
import {
  IconUser, IconUsers, IconX, IconClipboardCheck,
  IconStethoscope, IconAlertTriangle,
} from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import { NIVEL_ALERTA } from '../constants/signosVitales'
import type { DataTableColumn } from 'mantine-datatable'
import type { AgendaMedica } from '../services/agendaService'
import { StatusBadge } from '@/components/ui'

interface ColumnActions {
  onCancelar?:    (id: number) => void
  onTomarTriaje?: (turno: AgendaMedica) => void
}

export function getTurnosColumns(
  actions: ColumnActions
): DataTableColumn<AgendaMedica>[] {
  return [
    {
      accessor: 'folio',
      title:    'Folio',
      width:    140,
      render: (turno) => (
        <Text size="sm" ff="monospace" fw={500}>
          {turno.folio ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'paciente',
      title:    'Paciente',
      render: (turno) => {
        const esServidor = !!turno.servidor_id
        const nombre = esServidor
          ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
          : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

        return (
          // Servidor y familiar son una categoría: ni mejor ni peor, así que
          // el icono va en el color del texto y no en emerald y ocean, que
          // significan «éxito» e «informativo» en el resto del sistema
          // (reglas 03 y 06). El `title` le pone nombre a la figura, que antes
          // no tenía ninguno para quien usa lector de pantalla.
          <Group gap="xs" wrap="nowrap">
            {esServidor
              ? <IconUser size={14} title="Servidor" />
              : <IconUsers size={14} title="Familiar" />}
            <Text size="sm">{nombre.trim() || '—'}</Text>
          </Group>
        )
      },
    },
    {
      accessor: 'tipo_atencion',
      title:    'Atención',
      width:    150,
      render: (turno) => (
        <StatusBadge>
          {turno.tipo_atencion === 'odontologia'
            ? 'Odontología' : 'Medicina General'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'medico',
      title:    'Médico asignado',
      render: (turno) => (
        <Group gap={6} wrap="nowrap">
          <IconStethoscope
            size={14}
            color="var(--mantine-color-dimmed)"
          />
          <Text size="sm" c="dimmed">
            {turno.medico?.nombre_completo
              ?? turno.medico?.usuario_ti ?? '—'}
          </Text>
        </Group>
      ),
    },
    {
      accessor: 'registrado_en',
      title:    'Fecha y hora',
      width:    130,
      render: (turno) => {
        if (!turno.registrado_en) {
          return <Text size="sm" c="dimmed">—</Text>
        }
        const fecha = new Date(turno.registrado_en)
        return (
          <Stack gap={0}>
            <Text size="sm" ff="monospace">
              {fecha.toLocaleTimeString('es-EC', {
                hour: '2-digit', minute: '2-digit',
              })}
            </Text>
            {/* Aquí NO va `formatFechaMes`: esa lee en UTC, que es lo correcto
                para un campo `date`, pero `registrado_en` es un instante y se
                pinta junto a su propia hora. Un turno de las 20:00 saldría con
                la fecha del día siguiente al lado de «08:00 p. m.». */}
            <Text size="xs" c="dimmed">
              {fecha.toLocaleDateString('es-EC', {
                day: '2-digit', month: 'short', year: 'numeric',
              })}
            </Text>
          </Stack>
        )
      },
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    150,
      render: (turno) => {
        // El nivel del triaje va junto al estado, no en columna propia: es lo
        // que hay que ver de un vistazo al recorrer la cola, y solo existe
        // cuando ya se tomaron los signos vitales.
        const nivel = turno.triaje?.nivel_alerta
        const destacar = nivel === 'critico' || nivel === 'atencion'

        return (
          <Stack gap={4}>
            <StatusBadge tone={TONO_TURNO[turno.estado] ?? 'neutral'}>
              {ESTADO_TURNO_LABELS[turno.estado] ?? turno.estado}
            </StatusBadge>
            {destacar && (
              <StatusBadge
                tone={nivel === 'critico' ? 'danger' : 'warning'}
                size="xs"
                variant="filled"
                leftSection={<IconAlertTriangle size={10} />}
              >
                {NIVEL_ALERTA[nivel].etiqueta}
              </StatusBadge>
            )}
          </Stack>
        )
      },
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (turno) => {
        const tieneTriaje = !!turno.triaje
        const yaNoEstaEnEspera = turno.estado !== 'en_espera'

        return (
          <TableActions actions={[
            // Rehacer el triaje se permite mientras el turno siga en el
            // dispensario: una lectura mal tecleada tenía que poder
            // corregirse, y antes la acción quedaba inhabilitada para siempre
            // en cuanto se registraba la primera toma. Cada toma se guarda
            // aparte, así que corregir no borra lo anterior.
            {
              label:    tieneTriaje ? 'Rehacer triaje' : 'Tomar triaje',
              icon:     <IconClipboardCheck size={14} />,
              onClick:  () => actions.onTomarTriaje?.(turno),
              disabled: turnoCerrado(turno.estado),
              hidden:   !actions.onTomarTriaje
                || !turno.requiere_triaje,
            },
            {
              label:    tieneTriaje
                ? 'No se puede cancelar (ya con triaje)'
                : 'Cancelar turno',
              icon:     <IconX size={14} />,
              color:    tieneTriaje ? undefined : 'red',
              onClick:  () => actions.onCancelar?.(turno.id),
              disabled: tieneTriaje || yaNoEstaEnEspera,
              hidden:   !actions.onCancelar,
            },
          ]} />
        )
      },
    },
  ]
}
