import { Text } from '@mantine/core'
import { IconStar, IconStethoscope, IconUserCheck } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import { formatFecha } from '@/lib/fecha'
import { dictamenHabilitaIncorporacion } from '@/features/dispensario/services/solicitudCertificacionOptions'
import { ESTADO_POSTULANTE_OPTIONS, TONO_POSTULANTE } from '../services/convocatoriaService'
import { etiquetaDe } from '../constants/postulante'
import type { AspiranteExpress } from '../services/expressService'

export const nombreAspirante = (a: AspiranteExpress): string =>
  [a.apellidos, a.segundo_apellido, a.nombres, a.segundo_nombre].filter(Boolean).join(' ')

/**
 * Estados en los que el puntaje todavía decide algo. Espeja
 * `EstadoPostulante::admiteCalificacion()` del backend.
 */
const ESTADOS_CALIFICABLES = ['inscrito', 'en_evaluacion', 'aprobado', 'reprobado']

/** Con dictamen de aptitud: lo que exige el backend para incorporar. */
export const dictamenPermiteIncorporar = (a: AspiranteExpress) =>
  a.solicitud_certificacion?.estado === 'completada'
  && dictamenHabilitaIncorporacion(a.solicitud_certificacion.dictamen)

/**
 * Qué mostrar en la columna de estado.
 *
 * `ganador_potencial` cubre dos momentos muy distintos: despachado al
 * dispensario y esperando, o ya con dictamen y esperando a que Talento Humano
 * lo incorpore. Se deriva de la solicitud de certificación, que el listado ya
 * trae, en vez de inventar un estado nuevo en la base.
 */
function estadoVisible(a: AspiranteExpress): { etiqueta: string; tono: SemanticTone } {
  if (a.estado === 'ganador_potencial' && dictamenPermiteIncorporar(a)) {
    return { etiqueta: 'Apto — por incorporar', tono: 'success' }
  }

  // Desde el 2026-10-04 el no apto queda descalificado: su caso se cierra.
  if (a.estado === 'descalificado' && a.solicitud_certificacion?.dictamen === 'no_apto') {
    return { etiqueta: 'No apto', tono: 'danger' }
  }

  // Las etiquetas y los tonos del módulo: antes este cajón tenía su propia copia.
  return {
    etiqueta: etiquetaDe(ESTADO_POSTULANTE_OPTIONS, a.estado),
    tono: TONO_POSTULANTE[a.estado] ?? 'neutral',
  }
}

interface Acciones {
  califica:        boolean
  gestiona:        boolean
  puedeIncorporar: boolean
  tieneCriterios:  boolean
  onCalificar:     (a: AspiranteExpress) => void
  onEnviar:        (a: AspiranteExpress) => void
  onIncorporar:    (a: AspiranteExpress) => void
}

export function columnasAspirantesExpress(acc: Acciones): DataTableColumn<AspiranteExpress>[] {
  return [
    {
      accessor: 'aspirante',
      title: 'Aspirante',
      render: (a) => (
        <div>
          <Text size="sm" fw={500}>{nombreAspirante(a)}</Text>
          <Text size="xs" c="dimmed">{a.cedula}</Text>
        </div>
      ),
    },
    {
      accessor: 'puesto',
      title: 'Puesto al que aspira',
      render: (a) => (
        <div>
          <Text size="sm">{a.puesto?.cargo?.nombre ?? '—'}</Text>
          <Text size="xs" c="dimmed">{a.puesto?.unidad_administrativa?.nombre ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'fecha_inscripcion',
      title: 'Inscripción',
      width: 120,
      render: (a) => <Text size="sm">{formatFecha(a.fecha_inscripcion)}</Text>,
    },
    {
      accessor: 'evaluacion',
      title: 'Puntaje',
      width: 90,
      render: (a) => (
        <Text size="sm">
          {a.evaluacion?.puntaje_total != null ? Number(a.evaluacion.puntaje_total).toFixed(2) : '—'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 190,
      render: (a) => {
        const { etiqueta, tono } = estadoVisible(a)
        return <StatusBadge tone={tono}>{etiqueta}</StatusBadge>
      },
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (a) => (
        <TableActions
          actions={[
            {
              label: a.evaluacion ? 'Editar calificación' : 'Calificar',
              icon: <IconStar size={14} />,
              onClick: () => acc.onCalificar(a),
              // Despachado al dispensario, recalificar lo devolvería a
              // «aprobado»; el backend lo rechaza, así que ni se ofrece.
              hidden: !acc.califica || !ESTADOS_CALIFICABLES.includes(a.estado),
              // Sin criterios no hay nada que puntuar.
              disabled: !acc.tieneCriterios,
            },
            {
              label: 'Enviar al Dispensario',
              icon: <IconStethoscope size={14} />,
              onClick: () => acc.onEnviar(a),
              hidden: !acc.gestiona || a.estado !== 'aprobado',
            },
            {
              label: 'Confirmar incorporación',
              icon: <IconUserCheck size={14} />,
              onClick: () => acc.onIncorporar(a),
              hidden: !acc.puedeIncorporar || a.estado !== 'ganador_potencial',
              // Visible pero inerte mientras el dispensario no cierre el
              // dictamen: así se ve que el paso existe y qué falta para él.
              disabled: !dictamenPermiteIncorporar(a),
            },
          ]}
        />
      ),
    },
  ]
}
