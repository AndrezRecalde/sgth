'use client'

import { Stack } from '@mantine/core'
import {
  IconDownload, IconFileText, IconPlayerPlay, IconUserCheck,
} from '@tabler/icons-react'
import { confirmar, StatusBadge, TableActions } from '@/components/ui'
import { dictamenHabilitaIncorporacion } from '../services/solicitudCertificacionService'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import {
  estado, fechaLimite, origen, paciente, tipoEvento, type Columna,
} from './solicitudes-certificacion.columns'

interface AccionesSso {
  descargando: boolean
  puedeConfirmarIncorporacion: boolean
  onIniciar: (id: number) => void
  onContinuar: (id: number) => void
  onDescargarFemo: (fichaFemoId: number, nombreArchivo: string) => void
  onConfirmarIncorporacion: (id: number) => void
}

/** Salud Ocupacional: opera sobre la solicitud. */
export function getSolicitudesSsoColumns(acciones: AccionesSso): Columna[] {
  return [
    tipoEvento,
    paciente,
    origen('Solicitado por', 150, true),
    fechaLimite,
    {
      ...estado,
      // El triaje va con el estado y no en su propia columna: solo importa
      // mientras la solicitud está abierta, y esa columna dejaba la tabla más
      // ancha que la pantalla, con el menú de acciones fuera de la vista.
      render: (s, i) => (
        <Stack gap={4}>
          {estado.render?.(s, i)}
          {(s.estado === 'pendiente' || s.estado === 'en_proceso') && (
            <StatusBadge size="xs" tone={s.constantes_vitales ? 'success' : 'warning'}>
              {s.constantes_vitales ? 'Triaje hecho' : 'Sin triaje'}
            </StatusBadge>
          )}
        </Stack>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => <TableActions actions={accionesDe(s, acciones)} />,
    },
  ]
}

function accionesDe(s: SolicitudCertificacion, a: AccionesSso) {
  const sinSignos = 'Pendiente signos vitales (Enfermería)'

  return [
    ...(s.estado === 'pendiente' ? [{
      label: s.constantes_vitales ? 'Iniciar y crear FEMO' : sinSignos,
      icon: <IconPlayerPlay size={14} />,
      disabled: !s.constantes_vitales,
      onClick: () => a.onIniciar(s.id),
    }] : []),
    ...(s.estado === 'en_proceso' ? [{
      label: s.constantes_vitales ? 'Continuar FEMO' : sinSignos,
      icon: <IconFileText size={14} />,
      disabled: !s.constantes_vitales,
      onClick: () => a.onContinuar(s.id),
    }] : []),
    ...(s.ficha_femo_id ? [{
      label: 'Descargar PDF de la ficha FEMO',
      icon: <IconDownload size={14} />,
      disabled: a.descargando,
      onClick: () => a.onDescargarFemo(
        s.ficha_femo_id!, `femo-${s.cedula_paciente}-${s.id}.pdf`,
      ),
    }] : []),
    ...(s.estado === 'completada' && !!s.postulante && !s.servidor &&
      a.puedeConfirmarIncorporacion &&
      dictamenHabilitaIncorporacion(s.dictamen) ? [{
      label: 'Confirmar incorporación',
      icon: <IconUserCheck size={14} />,
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
