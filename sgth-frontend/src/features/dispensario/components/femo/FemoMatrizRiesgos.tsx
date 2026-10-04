'use client'

import {
  Accordion, ActionIcon, Alert, Checkbox, Group, Skeleton, Stack, Text, Textarea,
} from '@mantine/core'
import { IconAlertTriangle, IconTrash } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePuestoActividades } from '@/features/estructura/hooks/usePuestoActividad'
import { useCatalogoRiesgos } from '../../hooks/useCatalogoRiesgos'
import type { ActividadRiesgoForm, FactorRiesgoForm } from '../../schemas/femoEvaluacion.schema'
import { FemoSeccion } from './FemoSeccion'
import { FemoCategoriaRiesgo } from './FemoCategoriaRiesgo'
import { confirmar, StatusBadge } from '@/components/ui'

interface Props {
  puestoId:            number | null
  actividadesRiesgo:   ActividadRiesgoForm[]
  factoresRiesgo:      FactorRiesgoForm[]
  onActividadesChange: (data: ActividadRiesgoForm[]) => void
  onFactoresChange:    (data: FactorRiesgoForm[]) => void
}

export function FemoMatrizRiesgos({
  puestoId, actividadesRiesgo, factoresRiesgo,
  onActividadesChange, onFactoresChange,
}: Props) {
  const contained = useContainedInput('sm')
  const { data: puestoActividades = [] } = usePuestoActividades(puestoId)
  const { data: catalogo, isLoading: cargandoCatalogo } = useCatalogoRiesgos()

  // Quitar una actividad borra también todos sus factores marcados: se
  // confirma antes, porque deshacerlo es volver a marcarlos uno por uno.
  const removerActividad = (index: number) => confirmar({
    title: 'Quitar actividad',
    message: <>Se quitará <b>{actividadesRiesgo[index].actividad}</b> y todos sus factores de riesgo marcados.</>,
    confirmLabel: 'Quitar',
    destructiva: true,
    onConfirm: () => quitarActividad(index),
  })

  const quitarActividad = (index: number) => {
    onActividadesChange(actividadesRiesgo.filter((_, i) => i !== index))
    onFactoresChange(
      factoresRiesgo
        .filter(f => f.actividad_index !== index)
        .map(f => (f.actividad_index != null && f.actividad_index > index)
          ? { ...f, actividad_index: f.actividad_index - 1 }
          : f)
    )
  }

  const togglePuestoActividad = (pa: { id: number; descripcion: string }) => {
    const idx = actividadesRiesgo.findIndex(a => a.puesto_actividad_id === pa.id)
    if (idx >= 0) {
      removerActividad(idx)
    } else {
      onActividadesChange([
        ...actividadesRiesgo,
        {
          puesto_actividad_id: pa.id,
          actividad: pa.descripcion,
          medida_preventiva: null,
          orden: actividadesRiesgo.length + 1,
        },
      ])
    }
  }

  const setMedidaActividad = (index: number, medida: string) => {
    onActividadesChange(
      actividadesRiesgo.map((a, i) => i === index ? { ...a, medida_preventiva: medida } : a)
    )
  }

  /** ¿Es este el factor `factor` de la categoría, en la actividad `index`? */
  const esEste = (index: number, categoria: string, factor: string) => (f: FactorRiesgoForm) =>
    f.actividad_index === index && f.categoria === categoria && f.factor === factor

  const toggleFactor = (index: number, categoria: string, factor: string) => {
    const existe = factoresRiesgo.find(esEste(index, categoria, factor))
    onFactoresChange(existe
      ? factoresRiesgo.filter(f => f !== existe)
      : [...factoresRiesgo, { categoria, factor, presente: true, medida_preventiva: null, actividad_index: index }])
  }

  const setFactorMedida = (index: number, categoria: string, factor: string, medida: string) =>
    onFactoresChange(factoresRiesgo.map(f =>
      esEste(index, categoria, factor)(f) ? { ...f, medida_preventiva: medida } : f))

  return (
    <FemoSeccion
      letra="G"
      titulo="Factores de riesgo del trabajo actual"
      descripcion="Se marcan por cada actividad importante de la jornada laboral"
    >
      {puestoActividades.length > 0 ? (
        <Group gap="sm" wrap="wrap">
          {puestoActividades.map((pa) => (
            <Checkbox
              key={pa.id}
              label={pa.descripcion}
              size="sm"
              checked={actividadesRiesgo.some(a => a.puesto_actividad_id === pa.id)}
              onChange={() => togglePuestoActividad(pa)}
            />
          ))}
        </Group>
      ) : (
        <Alert
          color="amber"
          variant="light"
          radius="lg"
          icon={<IconAlertTriangle size={18} />}
          title="El puesto no tiene actividades configuradas"
        >
          Los riesgos se evalúan por actividad, así que primero hay que
          registrarlas. Solicite a Talento Humano que las cargue en
          Estructura › Puestos.
        </Alert>
      )}

      {actividadesRiesgo.length === 0 ? (
        <Text size="sm" c="dimmed">
          Ninguna actividad seleccionada para evaluar riesgos.
        </Text>
      ) : cargandoCatalogo || !catalogo ? (
        <Stack gap="xs">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} height={52} radius="md" />
          ))}
        </Stack>
      ) : (
        <Accordion multiple variant="separated" radius="md">
          {actividadesRiesgo.map((act, index) => {
            const factoresActividad = factoresRiesgo.filter(f => f.actividad_index === index)

            return (
              <Accordion.Item key={index} value={String(index)}>
                <Group wrap="nowrap" gap={0}>
                  <Accordion.Control flex={1}>
                    <Group justify="space-between" pr="sm">
                      <Text size="sm" fw={500}>{act.actividad}</Text>
                      {factoresActividad.length > 0 && (
                        <StatusBadge>
                          {factoresActividad.length} riesgo
                          {factoresActividad.length !== 1 ? 's' : ''}
                        </StatusBadge>
                      )}
                    </Group>
                  </Accordion.Control>
                  <ActionIcon
                    color="red"
                    mr="sm"
                    onClick={() => removerActividad(index)}
                    aria-label={`Quitar la actividad ${act.actividad}`}
                  >
                    <IconTrash size={15} />
                  </ActionIcon>
                </Group>

                <Accordion.Panel>
                  <Stack gap="md">
                    <Textarea
                      label="Medidas preventivas para esta actividad"
                      autosize
                      minRows={2}
                      {...contained}
                      value={act.medida_preventiva ?? ''}
                      onChange={(e) => setMedidaActividad(index, e.currentTarget.value)}
                    />

                    {Object.entries(catalogo).map(([clave, cat]) => (
                      <FemoCategoriaRiesgo
                        key={clave}
                        categoria={cat}
                        seleccionados={factoresActividad.filter(f => f.categoria === clave)}
                        onToggle={(factor) => toggleFactor(index, clave, factor)}
                        onDetalle={(factor, detalle) => setFactorMedida(index, clave, factor, detalle)}
                      />
                    ))}
                  </Stack>
                </Accordion.Panel>
              </Accordion.Item>
            )
          })}
        </Accordion>
      )}
    </FemoSeccion>
  )
}
