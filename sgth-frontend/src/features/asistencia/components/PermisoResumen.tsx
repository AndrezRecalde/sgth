'use client'

import { Card } from '@mantine/core'
import { DetailList, StatusBadge, type DetailItem } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { motivoDeEstado } from '../utils/motivoDeEstado'
import { ESTADO_LABELS, TIPO_LABELS, TONO_ESTADO } from './permisos.constants'
import type { PermisoServidor } from '@/types/api'

/**
 * La ficha de un permiso, de solo lectura.
 *
 * Separada de la pantalla que la usa porque son dos cosas distintas: esto
 * muestra, y `PermisoPorFolio` decide qué se puede hacer con ello.
 */
export function PermisoResumen({ permiso }: { permiso: PermisoServidor }) {
  const estado = permiso.estado as string
  const servidor = permiso.servidor
  const motivo = motivoDeEstado(permiso)

  // La policy quita la clave entera cuando este usuario no puede leer el
  // motivo. Si llega en null es que nadie lo escribió: antes los dos casos
  // salían como «Reservada».
  const observacion = 'observacion' in permiso ? permiso.observacion : 'Reservada'

  const items: DetailItem[] = [
    {
      label: 'Estado',
      value: (
        <StatusBadge tone={TONO_ESTADO[estado] ?? 'neutral'}>
          {ESTADO_LABELS[estado] ?? estado}
        </StatusBadge>
      ),
    },
    {
      label: 'Tipo',
      value: <StatusBadge>{TIPO_LABELS[permiso.tipo as string] ?? permiso.tipo}</StatusBadge>,
    },
    ...(motivo ? [{ label: motivo.etiqueta, value: motivo.motivo, ancho: true }] : []),
    {
      label: 'Servidor',
      value: [servidor?.apellido, servidor?.nombre].filter(Boolean).join(' '),
    },
    { label: 'Cédula', value: servidor?.cedula },
    { label: 'Unidad', value: permiso.unidad_administrativa?.nombre },
    { label: 'Fecha', value: formatFecha(permiso.fecha) },
    {
      label: 'Horario',
      value: `${permiso.hora_inicio?.substring(0, 5)} — ${permiso.hora_fin?.substring(0, 5)}`,
    },
    ...(permiso.sirha7_aprobado_en
      ? [{
          label: 'Registrado en Sirha7',
          value: `${permiso.sirha7_leave_nombre} · ${formatFecha(permiso.sirha7_aprobado_en)}`,
        }]
      : []),
    { label: 'Observación', value: observacion, ancho: true },
  ]

  return (
    <Card withBorder radius="lg" padding="lg">
      <DetailList items={items} />
    </Card>
  )
}
