'use client'

import { Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import {
  DICTAMEN_LABELS,
  ESTADO_SOLICITUD_LABELS,
  etiquetaTipoEvento,
  TONO_DICTAMEN,
  TONO_ESTADO_SOLICITUD,
} from '../services/solicitudCertificacionService'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import type { DataTableColumn } from 'mantine-datatable'
import { formatFechaMes, hoyIso } from '@/lib/fecha'

/*
| Las columnas que comparten las dos tablas de solicitudes de certificación
| médica. Salud Ocupacional (`solicitudes-sso.columns.tsx`) opera sobre ellas
| y añade los signos vitales y el menú de acciones; Certificaciones médicas
| (`certificaciones.columns.tsx`) es de solo lectura para Talento Humano y
| añade la unidad administrativa.
|
| Hasta el 2026-10-04 las tres cosas vivían en un archivo de 274 líneas, por
| encima de las 150 de la guía, para no duplicar estas cinco. Separadas así no
| hace falta ni lo uno ni lo otro.
*/

export type Columna = DataTableColumn<SolicitudCertificacion>

/** La unidad sale del servidor, o del puesto de la convocatoria si es candidato. */
export const unidadDe = (s: SolicitudCertificacion) =>
  s.servidor?.unidad_administrativa?.nombre
    ?? s.convocatoria?.puesto?.unidad_administrativa?.nombre
    ?? null

export const tipoEvento: Columna = {
  accessor: 'tipo_evento',
  title: 'Tipo de evaluación',
  // Las etiquetas son de una palabra («Ingreso», «Periódica»…): con 180 y
  // «Ingreso / Pre-ocupacional» la insignia se salía y el resto de anchos
  // fijos aplastaban la columna del servidor.
  width: 130,
  render: (s) => (
    <StatusBadge>{etiquetaTipoEvento(s.tipo_evento)}</StatusBadge>
  ),
}

export const paciente: Columna = {
  accessor: 'paciente',
  title: 'Servidor / Candidato',
  render: (s) => (
    <Stack gap={0}>
      <Text size="sm" fw={500}>{s.nombres_paciente}</Text>
      <Text size="xs" c="dimmed" ff="monospace">{s.cedula_paciente}</Text>
      {s.puesto_solicitado && <Text size="xs" c="dimmed">{s.puesto_solicitado}</Text>}
    </Stack>
  ),
}

export const origen = (titulo: string, ancho: number, conConvocatoria: boolean): Columna => ({
  accessor: 'origen',
  title: titulo,
  width: ancho,
  render: (s) => (
    <Stack gap={0}>
      <Text size="xs" c="dimmed" tt="capitalize">
        {s.origen === 'reclutamiento' ? 'Reclutamiento'
          : s.origen === 'expediente' ? 'Expediente'
          : 'Automático'}
      </Text>
      {conConvocatoria && s.convocatoria && (
        <Text size="xs" ff="monospace" c="dimmed">{s.convocatoria.codigo}</Text>
      )}
      {s.solicitado_por?.servidor && (
        <Text size="xs" c="dimmed">
          {s.solicitado_por.servidor.nombre} {s.solicitado_por.servidor.apellido}
        </Text>
      )}
    </Stack>
  ),
})

export const fechaLimite: Columna = {
  accessor: 'fecha_limite',
  title: 'Fecha límite',
  width: 110,
  render: (s) => {
    if (!s.fecha_limite) return <Text size="sm">—</Text>

    // Vencida y aún abierta: es lo que hay que perseguir, y va en rojo. Se
    // compara la fecha (aaaa-mm-dd) con la de hoy en Ecuador: con `new Date()`
    // salía vencida desde las 19:00 del día anterior, y también se pintaban
    // las canceladas.
    const abierta = s.estado === 'pendiente' || s.estado === 'en_proceso'
    const urgente = abierta && s.fecha_limite.slice(0, 10) < hoyIso()

    return (
      <Text size="sm" c={urgente ? 'red' : undefined} fw={urgente ? 600 : undefined}>
        {formatFechaMes(s.fecha_limite)}
      </Text>
    )
  },
}

export const estado: Columna = {
  accessor: 'estado',
  title: 'Estado',
  width: 160,
  render: (s) => (
    <Stack gap={4}>
      <StatusBadge tone={TONO_ESTADO_SOLICITUD[s.estado] ?? 'neutral'}>
        {ESTADO_SOLICITUD_LABELS[s.estado] ?? s.estado}
      </StatusBadge>
      {s.dictamen && (
        <StatusBadge size="xs" tone={TONO_DICTAMEN[s.dictamen] ?? 'neutral'}>
          {DICTAMEN_LABELS[s.dictamen] ?? s.dictamen}
        </StatusBadge>
      )}
      {/* «Cancelada» sin el motivo no dice nada: quien evalúa ve desaparecer
          una solicitud de su bandeja y no sabe por qué. */}
      {s.motivo_cancelacion && (
        <Text size="xs" c="dimmed" lineClamp={2}>{s.motivo_cancelacion}</Text>
      )}
    </Stack>
  ),
}
