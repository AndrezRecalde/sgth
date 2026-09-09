'use client'

import { Badge, Stack, Text } from '@mantine/core'
import {
  IconDownload, IconFileText, IconPlayerPlay, IconUserCheck,
} from '@tabler/icons-react'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import {
  DICTAMEN_LABELS,
  ESTADO_SOLICITUD_LABELS,
  TIPO_EVENTO_OPTIONS,
  TONO_DICTAMEN,
  TONO_ESTADO_SOLICITUD,
} from '../services/solicitudCertificacionService'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import type { DataTableColumn } from 'mantine-datatable'

/*
| Las columnas de las solicitudes de certificación médica.
|
| Dos pantallas las miran y no son la misma: Salud Ocupacional (`SsoView`)
| opera sobre ellas y añade los signos vitales y el menú de acciones;
| Certificaciones médicas es de solo lectura para Talento Humano y añade la
| unidad administrativa. Las cinco columnas que comparten viven aquí una vez.
|
| El archivo pasa de las 150 líneas que el reglamento marca para las columnas.
| La alternativa era duplicar esas cinco en dos archivos, que es peor: cuando
| cambie el formato de una fecha o una etiqueta habría que acordarse de tocar
| los dos.
*/

type Columna = DataTableColumn<SolicitudCertificacion>

interface AccionesSso {
  descargando: boolean
  puedeConfirmarIncorporacion: boolean
  onIniciar: (id: number) => void
  onContinuar: (id: number) => void
  onDescargarFemo: (fichaFemoId: number, nombreArchivo: string) => void
  onConfirmarIncorporacion: (id: number) => void
}

const etiquetaTipo = (tipo: string) =>
  TIPO_EVENTO_OPTIONS.find((o) => o.value === tipo)?.label ?? tipo

/** La unidad sale del servidor, o del puesto de la convocatoria si es candidato. */
const unidadDe = (s: SolicitudCertificacion) =>
  s.servidor?.unidad_administrativa?.nombre
    ?? s.convocatoria?.puesto?.unidad_administrativa?.nombre
    ?? null

const tipoEvento: Columna = {
  accessor: 'tipo_evento',
  title: 'Tipo de evaluación',
  width: 180,
  render: (s) => (
    <Badge size="sm" variant="light" color="blue">{etiquetaTipo(s.tipo_evento)}</Badge>
  ),
}

const paciente: Columna = {
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

const origen = (titulo: string, ancho: number, conConvocatoria: boolean): Columna => ({
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

const fechaLimite: Columna = {
  accessor: 'fecha_limite',
  title: 'Fecha límite',
  width: 110,
  render: (s) => {
    if (!s.fecha_limite) return <Text size="sm">—</Text>

    const fecha = new Date(s.fecha_limite)
    // Vencida y sin completar: es lo que hay que perseguir, y va en rojo.
    const urgente = fecha <= new Date() && s.estado !== 'completada'

    return (
      <Text size="sm" c={urgente ? 'red' : undefined} fw={urgente ? 600 : undefined}>
        {fecha.toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' })}
      </Text>
    )
  },
}

const estado: Columna = {
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
    </Stack>
  ),
}

/** Salud Ocupacional: opera sobre la solicitud. */
export function getSolicitudesSsoColumns(acciones: AccionesSso): Columna[] {
  return [
    tipoEvento,
    paciente,
    origen('Solicitado por', 160, true),
    fechaLimite,
    {
      accessor: 'signos_vitales',
      title: 'Signos vitales',
      width: 150,
      render: (s) => (
        <StatusBadge tone={s.constantes_vitales ? 'success' : 'warning'}>
          {s.constantes_vitales ? 'Tomados' : 'Pendiente'}
        </StatusBadge>
      ),
    },
    estado,
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => <TableActions actions={accionesDe(s, acciones)} />,
    },
  ]
}

/** Certificaciones médicas: Talento Humano solo mira. */
export function getCertificacionesColumns(): Columna[] {
  return [
    tipoEvento,
    paciente,
    {
      accessor: 'unidad',
      title: 'Unidad administrativa',
      width: 180,
      render: (s) => <Text size="sm">{unidadDe(s) ?? '—'}</Text>,
    },
    origen('Origen', 150, false),
    fechaLimite,
    estado,
  ]
}

function accionesDe(s: SolicitudCertificacion, a: AccionesSso) {
  const sinSignos = 'Pendiente signos vitales (Enfermería)'

  return [
    ...(s.estado === 'pendiente' ? [{
      label: s.constantes_vitales ? 'Iniciar y crear FEMO' : sinSignos,
      icon: <IconPlayerPlay size={14} />,
      color: 'blue',
      disabled: !s.constantes_vitales,
      onClick: () => a.onIniciar(s.id),
    }] : []),
    ...(s.estado === 'en_proceso' ? [{
      label: s.constantes_vitales ? 'Continuar FEMO' : sinSignos,
      icon: <IconFileText size={14} />,
      color: 'blue',
      disabled: !s.constantes_vitales,
      onClick: () => a.onContinuar(s.id),
    }] : []),
    ...(s.ficha_femo_id ? [{
      label: 'Descargar PDF de la ficha FEMO',
      icon: <IconDownload size={14} />,
      color: 'blue',
      disabled: a.descargando,
      onClick: () => a.onDescargarFemo(
        s.ficha_femo_id!, `femo-${s.cedula_paciente}-${s.id}.pdf`,
      ),
    }] : []),
    ...(s.estado === 'completada' && !!s.postulante && !s.servidor &&
      a.puedeConfirmarIncorporacion &&
      (s.dictamen === 'apto' || s.dictamen === 'apto_con_restricciones') ? [{
      label: 'Confirmar incorporación',
      icon: <IconUserCheck size={14} />,
      color: 'emerald',
      onClick: () => confirmar({
        title: 'Confirmar incorporación',
        message: (
          <>
            Se creará el expediente de <b>{s.nombres_paciente}</b> como servidor
            del GADPE. No se puede deshacer.
          </>
        ),
        confirmLabel: 'Confirmar incorporación',
        onConfirm: () => a.onConfirmarIncorporacion(s.id),
      }),
    }] : []),
  ]
}
