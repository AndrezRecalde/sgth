'use client'

import { useState } from 'react'
import {
  Alert, Button, Drawer, Group, Select, Stack, Text,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconAlertTriangle, IconSettings, IconStar, IconStethoscope, IconUserCheck,
} from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { SgthTable, StatusBadge, TableActions, confirmar } from '@/components/ui'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAspirantesExpress } from '../hooks/useExpress'
import { useCriterios } from '../hooks/useCriterio'
import { useEnviarAlDispensario } from '../hooks/useConvocatoria'
import { useConfirmarIncorporacion } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { useAuth } from '@/hooks/useAuth'
import { CalificarPostulanteModal } from './CalificarPostulanteModal'
import { SeleccionarPlantillaModal } from './SeleccionarPlantillaModal'
import type { SemanticTone } from '@/config/design.tokens'
import type {
  AspiranteExpress, FiltroAnios, TarjetaExpress,
} from '../services/expressService'

const ESTADO_LABELS: Record<string, string> = {
  inscrito:          'Inscrito',
  en_evaluacion:     'En evaluación',
  aprobado:          'Aprobado',
  reprobado:         'Reprobado',
  descalificado:     'Descalificado',
  seleccionado:      'Seleccionado',
  ganador_potencial: 'En evaluación médica',
  no_seleccionado:   'No seleccionado',
  lista_espera:      'Lista de espera',
  incorporado:       'Incorporado',
}

/** El estado se pinta por lo que significa, no por un color elegido a mano. */
const ESTADO_TONO: Record<string, SemanticTone> = {
  inscrito:          'neutral',
  en_evaluacion:     'info',
  aprobado:          'success',
  reprobado:         'danger',
  descalificado:     'danger',
  seleccionado:      'success',
  ganador_potencial: 'info',
  no_seleccionado:   'neutral',
  lista_espera:      'warning',
  incorporado:       'success',
}

function formatFecha(f?: string | null): string {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

function nombreCompleto(a: AspiranteExpress): string {
  return [a.apellidos, a.segundo_apellido, a.nombres, a.segundo_nombre]
    .filter(Boolean)
    .join(' ')
}

/**
 * Estados en los que el puntaje todavía decide algo. Espeja
 * `EstadoPostulante::admiteCalificacion()` del backend.
 */
const ESTADOS_CALIFICABLES = ['inscrito', 'en_evaluacion', 'aprobado', 'reprobado']

/** Dictámenes que habilitan la incorporación. Los mismos que exige el backend. */
const DICTAMENES_APTOS = ['apto', 'apto_con_restricciones']

/**
 * Qué mostrar en la columna de estado.
 *
 * `ganador_potencial` cubre dos momentos muy distintos: despachado al
 * dispensario y esperando, o ya con dictamen y esperando a que Talento Humano
 * lo incorpore. La etiqueta fija «En evaluación médica» mentía en el segundo
 * caso — el médico ya había respondido.
 *
 * En vez de inventar un estado nuevo en la base, se deriva de la solicitud de
 * certificación, que el listado ya trae.
 */
function estadoVisible(a: AspiranteExpress): { etiqueta: string; tono: SemanticTone } {
  const s = a.solicitud_certificacion

  if (a.estado === 'ganador_potencial' && s?.estado === 'completada') {
    if (DICTAMENES_APTOS.includes(s.dictamen ?? '')) {
      return { etiqueta: 'Apto — por incorporar', tono: 'success' }
    }
    return { etiqueta: 'No apto', tono: 'danger' }
  }

  return {
    etiqueta: ESTADO_LABELS[a.estado] ?? a.estado,
    tono: ESTADO_TONO[a.estado] ?? 'neutral',
  }
}

interface Props {
  opened: boolean
  onClose: () => void
  contenedor: TarjetaExpress | null
  filtro: FiltroAnios
  estado: string | null
  onEstadoChange: (estado: string | null) => void
}

export function AspirantesExpressDrawer({
  opened, onClose, contenedor, filtro, estado, onEstadoChange,
}: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput('sm')

  const [aspiranteSel, setAspiranteSel] = useState<AspiranteExpress | null>(null)
  const [calAbierto, cal] = useDisclosure(false)
  const [plantillaAbierta, plantilla] = useDisclosure(false)

  const convocatoriaId = contenedor?.convocatoria_id ?? null

  const { data, isLoading } = useAspirantesExpress(
    opened ? convocatoriaId : null,
    { ...filtro, ...(estado ? { estado } : {}) },
  )

  // El contenedor necesita criterios de evaluación antes de poder calificar a
  // nadie. Se comparten por modalidad, así que se configuran una sola vez.
  const { data: criterios = [] } = useCriterios(opened ? convocatoriaId : null)
  const tieneCriterios = criterios.length > 0

  const enviarAlDispensario = useEnviarAlDispensario(convocatoriaId ?? 0)
  const confirmarIncorporacion = useConfirmarIncorporacion()

  // Crear el expediente de un servidor es competencia de Talento Humano; el
  // backend lo exige igual y responde 403 sin este permiso.
  const { hasPermiso } = useAuth()
  const puedeIncorporar = hasPermiso('gestionar-onboarding')

  const aspirantes = data?.data ?? []

  const abrirCalificacion = (a: AspiranteExpress) => {
    setAspiranteSel(a)
    cal.open()
  }

  const despachar = (a: AspiranteExpress) =>
    confirmar({
      title: 'Enviar al Dispensario Médico',
      message: (
        <>
          Se solicitará la ficha ocupacional de <b>{nombreCompleto(a)}</b>. La
          incorporación se genera recién cuando el dispensario emita un dictamen
          de aptitud.
        </>
      ),
      confirmLabel: 'Enviar',
      onConfirm: () => enviarAlDispensario.mutate(a.id),
    })

  /**
   * ¿Ya se puede incorporar? Hace falta que el dispensario haya cerrado la
   * solicitud con un dictamen de aptitud. El backend exige lo mismo; aquí solo
   * se decide si la acción se ofrece.
   */
  const dictamenPermiteIncorporar = (a: AspiranteExpress) => {
    const s = a.solicitud_certificacion
    return (
      s?.estado === 'completada' &&
      (s.dictamen === 'apto' || s.dictamen === 'apto_con_restricciones')
    )
  }

  const incorporar = (a: AspiranteExpress) =>
    confirmar({
      title: 'Confirmar incorporación',
      message: (
        <>
          Se creará el expediente de servidor de <b>{nombreCompleto(a)}</b> con
          su acción de ingreso y su proceso de inducción. No se puede deshacer.
        </>
      ),
      confirmLabel: 'Incorporar',
      onConfirm: () =>
        confirmarIncorporacion.mutate(a.solicitud_certificacion!.id),
    })

  const columns: DataTableColumn<AspiranteExpress>[] = [
    {
      accessor: 'aspirante',
      title: 'Aspirante',
      render: (a) => (
        <div>
          <Text size="sm" fw={500}>{nombreCompleto(a)}</Text>
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
          <Text size="xs" c="dimmed">
            {a.puesto?.unidad_administrativa?.nombre ?? '—'}
          </Text>
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
          {a.evaluacion?.puntaje_total != null
            ? Number(a.evaluacion.puntaje_total).toFixed(2)
            : '—'}
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
              onClick: () => abrirCalificacion(a),
              // Una vez despachado al dispensario, el puntaje ya cumplió su
              // función. Recalificar recalcula el estado y lo devolvería a
              // «aprobado», borrando el despacho y el dictamen; el backend lo
              // rechaza, así que aquí ni se ofrece.
              hidden: !ESTADOS_CALIFICABLES.includes(a.estado),
              // Sin criterios no hay nada que puntuar; se habilita al
              // configurarlos desde la cabecera del cajón.
              disabled: !tieneCriterios,
            },
            {
              label: 'Enviar al Dispensario',
              icon: <IconStethoscope size={14} />,
              onClick: () => despachar(a),
              // Solo tras aprobar: es lo que exige el backend, y adelantarlo
              // devolvería un error en vez de explicar el orden del trámite.
              hidden: a.estado !== 'aprobado',
            },
            {
              // Último paso del reclutamiento. Estaba solo en la pantalla del
              // dispensario, así que desde aquí el trámite parecía terminar en
              // «En evaluación médica» sin más opciones que recalificar.
              label: 'Confirmar incorporación',
              icon: <IconUserCheck size={14} />,
              color: 'emerald',
              onClick: () => incorporar(a),
              hidden: a.estado !== 'ganador_potencial',
              // Visible pero inerte mientras el dispensario no cierre el
              // dictamen: así se ve que el paso existe y qué falta para él.
              disabled: !dictamenPermiteIncorporar(a) || !puedeIncorporar,
            },
          ]}
        />
      ),
    },
  ]

  return (
    <>
      <Drawer
        opened={opened}
        onClose={onClose}
        position="right"
        size={isMobile ? '100%' : 'xl'}
        title={contenedor?.titulo ?? 'Aspirantes'}
      >
        <Stack gap="md">
          {convocatoriaId && !tieneCriterios && (
            <Alert
              color="amber"
              variant="light"
              radius="lg"
              icon={<IconAlertTriangle size={18} />}
              title="Falta configurar la evaluación"
            >
              <Text size="sm" mb="sm">
                Esta modalidad todavía no tiene criterios de evaluación, así que
                no se puede calificar a nadie. Aplica una plantilla una sola vez
                y servirá para todos sus aspirantes.
              </Text>
              <Button
                variant="light"
                size="xs"
                leftSection={<IconSettings size={14} />}
                onClick={plantilla.open}
              >
                Configurar criterios
              </Button>
            </Alert>
          )}

          <Group justify="space-between" align="flex-end">
            <Group gap="sm" align="flex-end">
              <Select
                label="Filtrar por estado"
                placeholder="Todos"
                data={Object.keys(ESTADO_LABELS).map((e) => ({
                  value: e, label: ESTADO_LABELS[e],
                }))}
                value={estado}
                onChange={onEstadoChange}
                clearable
                {...contained}
                style={{ minWidth: 220 }}
              />
              <Text size="sm" c="dimmed" pb={10}>
                {data?.total ?? aspirantes.length} aspirante(s)
              </Text>
            </Group>

            {tieneCriterios && (
              <Button
                variant="subtle"
                size="xs"
                leftSection={<IconSettings size={14} />}
                onClick={plantilla.open}
              >
                Criterios de evaluación
              </Button>
            )}
          </Group>

          <SgthTable
            records={aspirantes}
            columns={columns}
            fetching={isLoading}
            minHeight={200}
          />
        </Stack>
      </Drawer>

      {convocatoriaId && (
        <>
          <CalificarPostulanteModal
            opened={calAbierto}
            onClose={cal.close}
            postulante={aspiranteSel}
            convocatoriaId={convocatoriaId}
          />

          <SeleccionarPlantillaModal
            opened={plantillaAbierta}
            onClose={plantilla.close}
            convocatoriaId={convocatoriaId}
            tieneCriterios={tieneCriterios}
          />
        </>
      )}
    </>
  )
}
