'use client'

import { Text } from '@mantine/core'
import { IconBan, IconFileCertificate } from '@tabler/icons-react'
import { TableActions } from '@/components/ui'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import {
  estado, fechaLimite, origen, paciente, tipoEvento, unidadDe, type Columna,
} from './solicitudes-certificacion.columns'

interface AccionesCertificaciones {
  /** `false` oculta la cancelación: sin `solicitar-certificacion-medica`. */
  puedeCancelar: boolean
  /** `null` cuando no hay ninguna descarga en curso. */
  descargandoId: number | null
  onCancelar: (solicitud: SolicitudCertificacion) => void
  onDescargarCertificado: (solicitud: SolicitudCertificacion) => void
}

/** Certificaciones médicas: el seguimiento de Talento Humano. */
export function getCertificacionesColumns(
  acciones: AccionesCertificaciones,
): Columna[] {
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
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => (
        <TableActions
          actions={[
            {
              label: 'Certificado de aptitud',
              icon: <IconFileCertificate size={14} />,
              // Sin ficha no hay acto médico firmado y el API responde 422.
              hidden: !s.ficha_salud_ocupacional,
              disabled: acciones.descargandoId !== null,
              onClick: () => acciones.onDescargarCertificado(s),
            },
            {
              label: 'Cancelar solicitud',
              icon: <IconBan size={14} />,
              color: 'red',
              // Iniciada ya hay un FEMO en curso, y completada ya tiene
              // dictamen: solo se retira lo que nadie ha tocado.
              hidden: !acciones.puedeCancelar || s.estado !== 'pendiente',
              onClick: () => acciones.onCancelar(s),
            },
          ]}
        />
      ),
    },
  ]
}
