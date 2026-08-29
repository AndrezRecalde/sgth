'use client'

import {
  Accordion, ActionIcon, Alert, Badge, Card, Checkbox, Group,
  Skeleton, Stack, Text, Textarea, TextInput,
} from '@mantine/core'
import { IconAlertTriangle, IconTrash } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePuestoActividades } from '@/features/estructura/hooks/usePuestoActividad'
import { useCatalogoRiesgos } from '../../hooks/useCatalogoRiesgos'
import type { ActividadRiesgoForm, FactorRiesgoForm } from '../../schemas/femo.schema'
import { FemoSeccion } from './FemoSeccion'
import classes from './FemoMatrizRiesgos.module.css'

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

  const getFactor = (index: number, categoria: string, factor: string) =>
    factoresRiesgo.find(
      f => f.actividad_index === index && f.categoria === categoria && f.factor === factor
    )

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

  const setFactorMedida = (
    index: number, categoria: string, factor: string, medida: string,
  ) => {
    onFactoresChange(
      factoresRiesgo.map(f =>
        f.actividad_index === index && f.categoria === categoria && f.factor === factor
          ? { ...f, medida_preventiva: medida }
          : f
      )
    )
  }

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
                  <Accordion.Control style={{ flex: 1 }}>
                    <Group justify="space-between" pr="sm">
                      <Text size="sm" fw={500}>{act.actividad}</Text>
                      {factoresActividad.length > 0 && (
                        <Badge size="sm" variant="light" radius="sm">
                          {factoresActividad.length} riesgo
                          {factoresActividad.length !== 1 ? 's' : ''}
                        </Badge>
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

                    {Object.entries(catalogo).map(([clave, cat]) => {
                      const seleccionados = factoresActividad.filter(f => f.categoria === clave)

                      return (
                        <Card key={clave} withBorder radius="md" padding="sm">
                          <Group gap="xs" mb="sm">
                            <Text size="sm" fw={600}>{cat.etiqueta}</Text>
                            {seleccionados.length > 0 && (
                              <Badge size="xs" variant="light" radius="sm">
                                {seleccionados.length}
                              </Badge>
                            )}
                          </Group>

                          <Stack gap="sm">
                            {cat.grupos.map((grupo, g) => (
                              <div key={grupo.subcategoria ?? g}>
                                {/* Solo «De seguridad» se subdivide; en el resto
                                    los factores cuelgan directo de la categoría. */}
                                {grupo.etiqueta && (
                                  <Text component="div" className={classes.subcategoria}>
                                    {grupo.etiqueta}
                                  </Text>
                                )}
                                <div className={classes.factores}>
                                  {grupo.factores.map((factor) => (
                                    <Checkbox
                                      key={factor}
                                      label={factor}
                                      size="sm"
                                      checked={!!getFactor(index, clave, factor)}
                                      onChange={() => toggleFactor(index, clave, factor)}
                                    />
                                  ))}
                                </div>
                              </div>
                            ))}
                          </Stack>

                          {seleccionados.length > 0 && (
                            <Stack gap="xs" mt="md">
                              {seleccionados.map((f) => (
                                <TextInput
                                  key={f.factor}
                                  label={`Detalle — ${f.factor}`}
                                  placeholder="Opcional. Obligatorio si marcó «Otros»."
                                  {...contained}
                                  value={f.medida_preventiva ?? ''}
                                  onChange={(e) =>
                                    setFactorMedida(index, clave, f.factor, e.currentTarget.value)
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
    </FemoSeccion>
  )
}
