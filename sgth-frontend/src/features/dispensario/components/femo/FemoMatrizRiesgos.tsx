'use client'

import {
  Stack, Text, Group, Checkbox, TextInput,
  Card, Badge, Accordion, Textarea, ActionIcon,
} from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePuestoActividades } from '@/features/estructura/hooks/usePuestoActividad'
import { CATEGORIAS_RIESGO } from '../../services/femoOptions'
import type { ActividadRiesgoForm, FactorRiesgoForm } from '../../schemas/femo.schema'

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
  const contained = useContainedInput()
  const { data: puestoActividades = [] } = usePuestoActividades(puestoId)

  const removerActividad = (index: number) => {
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
        { puesto_actividad_id: pa.id, actividad: pa.descripcion, medida_preventiva: null, orden: actividadesRiesgo.length + 1 },
      ])
    }
  }

  const setMedidaActividad = (index: number, medida: string) => {
    onActividadesChange(
      actividadesRiesgo.map((a, i) => i === index ? { ...a, medida_preventiva: medida } : a)
    )
  }

  const getFactor = (index: number, categoria: string, factor: string) =>
    factoresRiesgo.find(f => f.actividad_index === index && f.categoria === categoria && f.factor === factor)

  const toggleFactor = (index: number, categoria: string, factor: string) => {
    const existe = getFactor(index, categoria, factor)
    if (existe) {
      onFactoresChange(factoresRiesgo.filter(f => f !== existe))
    } else {
      onFactoresChange([
        ...factoresRiesgo,
        { categoria, factor, presente: true, medida_preventiva: null, actividad_index: index },
      ])
    }
  }

  const setFactorMedida = (index: number, categoria: string, factor: string, medida: string) => {
    onFactoresChange(
      factoresRiesgo.map(f =>
        f.actividad_index === index && f.categoria === categoria && f.factor === factor
          ? { ...f, medida_preventiva: medida }
          : f
      )
    )
  }

  return (
    <Stack gap="xs">
      <Text size="xs" fw={600} c="dimmed" tt="uppercase" style={{ letterSpacing: '0.05em' }}>
        D. Factores de riesgo laboral (por actividad del puesto)
      </Text>

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
        <Text size="sm" c="orange">
          Este puesto no tiene actividades configuradas. Solicite a Talento Humano
          que las registre en el módulo de Estructura antes de continuar.
        </Text>
      )}

      {actividadesRiesgo.length === 0 ? (
        <Text size="sm" c="dimmed">
          Ninguna actividad seleccionada para evaluar riesgos.
        </Text>
      ) : (
        <Accordion multiple variant="separated" radius="md">
          {actividadesRiesgo.map((act, index) => {
            const factoresActividad = factoresRiesgo.filter(f => f.actividad_index === index)
            return (
              <Accordion.Item key={index} value={String(index)}>
                <Group wrap="nowrap" gap={0}>
                  <Accordion.Control style={{ flex: 1 }}>
                    <Group justify="space-between" pr="sm">
                      <Text size="sm" fw={500}>{act.actividad}</Text>
                      {factoresActividad.length > 0 && (
                        <Badge size="xs" color="orange" variant="light">
                          {factoresActividad.length} riesgo{factoresActividad.length !== 1 ? 's' : ''}
                        </Badge>
                      )}
                    </Group>
                  </Accordion.Control>
                  <ActionIcon
                    size="sm" color="red" variant="subtle" mr="sm"
                    onClick={() => removerActividad(index)}
                  >
                    <IconTrash size={13} />
                  </ActionIcon>
                </Group>
                <Accordion.Panel>
                  <Stack gap="sm">
                    <Textarea
                      label="Medida preventiva para esta actividad"
                      autosize
                      minRows={2}
                      {...contained}
                      value={act.medida_preventiva ?? ''}
                      onChange={(e) => setMedidaActividad(index, e.currentTarget.value)}
                    />
                    {CATEGORIAS_RIESGO.map((cat) => {
                      const seleccionados = factoresActividad.filter(f => f.categoria === cat.key)
                      return (
                        <Card key={cat.key} withBorder radius="md" p="sm">
                          <Group gap="xs" mb="xs">
                            <Badge size="sm" variant="light" color={cat.color}>
                              {cat.label}
                            </Badge>
                            {seleccionados.length > 0 && (
                              <Text size="xs" c="dimmed">
                                {seleccionados.length} seleccionado{seleccionados.length !== 1 ? 's' : ''}
                              </Text>
                            )}
                          </Group>
                          <Group gap="sm" wrap="wrap">
                            {cat.factores.map((factor) => (
                              <Checkbox
                                key={factor}
                                label={factor}
                                size="sm"
                                checked={!!getFactor(index, cat.key, factor)}
                                onChange={() => toggleFactor(index, cat.key, factor)}
                              />
                            ))}
                          </Group>
                          {seleccionados.length > 0 && (
                            <Stack gap="xs" mt="sm">
                              {seleccionados.map((f) => (
                                <TextInput
                                  key={f.factor}
                                  size="xs"
                                  label={`Detalle — ${f.factor} (opcional)`}
                                  {...contained}
                                  value={f.medida_preventiva ?? ''}
                                  onChange={(e) =>
                                    setFactorMedida(index, cat.key, f.factor, e.currentTarget.value)
                                  }
                                />
                              ))}
                            </Stack>
                          )}
                        </Card>
                      )
                    })}
                  </Stack>
                </Accordion.Panel>
              </Accordion.Item>
            )
          })}
        </Accordion>
      )}
    </Stack>
  )
}
