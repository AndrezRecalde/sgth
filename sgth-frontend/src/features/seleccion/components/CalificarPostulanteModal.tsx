'use client'

import {
  Modal, Stack, Text, NumberInput,
  Textarea, Button, Group, Card,
  Badge, Progress, Divider, Alert,
  Radio, Checkbox, ScrollArea,
  Skeleton, ThemeIcon,
} from '@mantine/core'
import {
  IconCheck, IconTrophy, IconInfoCircle,
  IconList, IconHash, IconCheckbox,
} from '@tabler/icons-react'
import { useState, useEffect } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useCriterios,
  useCalificaciones,
  useGuardarCalificaciones,
} from '../hooks/useCriterio'
import type {
  CriterioEvaluacion,
  CalificacionItem,
} from '../services/criterioService'
import type { Postulante } from '../services/convocatoriaService'

interface Props {
  opened:         boolean
  onClose:        () => void
  postulante:     Postulante | null
  convocatoriaId: number
}

type EstadoCal = Record<number, {
  opcion_id?:      number | null
  opciones_ids?:   number[]
  valor_numerico?: number | null
  observacion?:    string | null
}>

const TIPO_ICONS: Record<string, React.ReactNode> = {
  radio:     <IconList size={14} />,
  checklist: <IconCheckbox size={14} />,
  numero:    <IconHash size={14} />,
}

function CriterioInput({
  criterio,
  estado,
  onChange,
}: {
  criterio: CriterioEvaluacion
  estado:   EstadoCal[number]
  onChange: (val: Partial<EstadoCal[number]>) => void
}) {
  const contained = useContainedInput()

  if (criterio.tipo_input === 'numero') {
    return (
      <NumberInput
        placeholder={`0 — ${criterio.puntaje_maximo} pts`}
        min={0}
        max={Number(criterio.puntaje_maximo)}
        decimalScale={2}
        size="sm"
        {...contained}
        value={estado.valor_numerico ?? undefined}
        onChange={(v) =>
          onChange({ valor_numerico: Number(v) || null })
        }
      />
    )
  }

  if (criterio.tipo_input === 'radio') {
    return (
      <Radio.Group
        value={String(estado.opcion_id ?? '')}
        onChange={(v) =>
          onChange({ opcion_id: v ? Number(v) : null })
        }
      >
        <Stack gap="xs">
          {criterio.opciones.map(op => (
            <Radio
              key={op.id}
              value={String(op.id)}
              label={
                <Group gap="xs">
                  <Text size="sm">{op.etiqueta}</Text>
                  <Badge size="xs" variant="light" color="blue">
                    {op.puntaje} pts
                  </Badge>
                </Group>
              }
              size="sm"
            />
          ))}
        </Stack>
      </Radio.Group>
    )
  }

  if (criterio.tipo_input === 'checklist') {
    const seleccionados = estado.opciones_ids ?? []
    return (
      <Stack gap="xs">
        {criterio.opciones.map(op => (
          <Checkbox
            key={op.id}
            checked={seleccionados.includes(op.id)}
            label={
              <Group gap="xs">
                <Text size="sm">{op.etiqueta}</Text>
                <Badge size="xs" variant="light" color="orange">
                  +{op.puntaje} pts
                </Badge>
              </Group>
            }
            size="sm"
            onChange={(e) => {
              const nuevos = e.currentTarget.checked
                ? [...seleccionados, op.id]
                : seleccionados.filter(id => id !== op.id)
              onChange({ opciones_ids: nuevos })
            }}
          />
        ))}
      </Stack>
    )
  }

  return null
}

function calcularPuntajeCriterio(
  criterio: CriterioEvaluacion,
  estado: EstadoCal[number]
): number {
  if (criterio.tipo_input === 'numero') {
    return Math.min(
      Number(estado.valor_numerico ?? 0),
      Number(criterio.puntaje_maximo)
    )
  }
  if (criterio.tipo_input === 'radio') {
    const op = criterio.opciones.find(
      o => o.id === estado.opcion_id
    )
    return Number(op?.puntaje ?? 0)
  }
  if (criterio.tipo_input === 'checklist') {
    const ids = estado.opciones_ids ?? []
    const sum = criterio.opciones
      .filter(o => ids.includes(o.id))
      .reduce((acc, o) => acc + Number(o.puntaje), 0)
    return Math.min(sum, Number(criterio.puntaje_maximo))
  }
  return 0
}

export function CalificarPostulanteModal({
  opened, onClose, postulante, convocatoriaId,
}: Props) {
  const [estados, setEstados] = useState<EstadoCal>({})

  const { data: criterios = [], isLoading: cargandoCriterios } =
    useCriterios(opened ? convocatoriaId : null)

  const { data: calPrevias, isLoading: cargandoCal } =
    useCalificaciones(
      opened ? convocatoriaId : null,
      opened ? postulante?.id ?? null : null
    )

  const guardar = useGuardarCalificaciones(
    convocatoriaId, postulante?.id ?? 0
  )

  useEffect(() => {
    if (!opened) return
    if (cargandoCriterios || cargandoCal) return
    if (criterios.length === 0) return

    const init: EstadoCal = {}
    criterios.forEach(c => {
      const prev = calPrevias?.calificaciones?.[c.id]
      if (prev) {
        if (c.tipo_input === 'numero') {
          init[c.id] = {
            valor_numerico: prev.valor_numerico
              ? Number(prev.valor_numerico)
              : null,
          }
        } else if (c.tipo_input === 'radio') {
          init[c.id] = {
            opcion_id: prev.opcion_id ?? null,
          }
        } else if (c.tipo_input === 'checklist') {
          const todasCalif = Object.values(
            calPrevias?.calificaciones ?? {}
          ).filter(
            (cal: { criterio_id: number; opcion_id?: number | null }) =>
              cal.criterio_id === c.id && cal.opcion_id != null
          )
          init[c.id] = {
            opciones_ids: todasCalif
              .map((cal: { opcion_id?: number | null }) =>
                cal.opcion_id as number
              )
              .filter(Boolean),
          }
        } else {
          init[c.id] = {}
        }
      } else {
        init[c.id] = {}
      }
    })
    setEstados(init)
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [
    opened,
    cargandoCriterios,
    cargandoCal,
    postulante?.id,
  ])

  if (!postulante) return null

  const nombreCompleto = [
    postulante.apellidos,
    postulante.segundo_apellido,
    postulante.nombres,
    postulante.segundo_nombre,
  ].filter(Boolean).join(' ')

  const meritos   = criterios.filter(c => c.seccion === 'meritos')
  const oposicion = criterios.filter(c => c.seccion === 'oposicion')

  const totalMeritos = meritos.reduce(
    (acc, c) => acc + calcularPuntajeCriterio(c, estados[c.id] ?? {}),
    0
  )
  const totalOposicion = oposicion.reduce(
    (acc, c) => acc + calcularPuntajeCriterio(c, estados[c.id] ?? {}),
    0
  )
  const total   = totalMeritos + totalOposicion
  const aprueba = total >= 70

  const handleGuardar = () => {
    const items: CalificacionItem[] = criterios.map(c => {
      const est = estados[c.id] ?? {}
      if (c.tipo_input === 'checklist') {
        const ids = est.opciones_ids ?? []
        return ids.length > 0
          ? ids.map(oid => ({
              criterio_id:    c.id,
              opcion_id:      oid,
              valor_numerico: null,
              observacion:    est.observacion ?? null,
            }))
          : [{ criterio_id: c.id, opcion_id: null,
               valor_numerico: null, observacion: null }]
      }
      return {
        criterio_id:    c.id,
        opcion_id:      est.opcion_id ?? null,
        valor_numerico: est.valor_numerico ?? null,
        observacion:    est.observacion ?? null,
      }
    }).flat()

    guardar.mutate(items, { onSuccess: onClose })
  }

  const isLoading = cargandoCriterios || cargandoCal

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Calificar candidato"
      size="xl"
      radius="xl"
    >
      <Stack gap="md">
        <Card withBorder radius="md" p="sm">
          <Group justify="space-between">
            <Stack gap={2}>
              <Text size="sm" fw={600}>{nombreCompleto}</Text>
              <Text size="xs" c="dimmed">
                {postulante.cedula} · {postulante.correo}
              </Text>
            </Stack>
            <Badge
              size="lg"
              color={aprueba ? 'emerald' : 'red'}
              variant="light"
            >
              {total.toFixed(2)} / 100 pts
            </Badge>
          </Group>
          <Progress
            value={total}
            color={aprueba ? 'emerald' : 'red'}
            size="sm"
            radius="xl"
            mt="xs"
          />
          <Text size="xs" c={aprueba ? 'emerald' : 'red'} mt={4}>
            {aprueba
              ? '✓ Aprueba (≥ 70 puntos)'
              : '✗ No aprueba (< 70 puntos)'}
          </Text>
        </Card>

        {criterios.length === 0 && !isLoading && (
          <Alert color="orange" variant="light"
            icon={<IconInfoCircle size={16} />}>
            <Text size="xs">
              Esta convocatoria no tiene criterios de evaluación
              configurados. Configure los criterios primero en
              el tab "Criterios".
            </Text>
          </Alert>
        )}

        {isLoading ? (
          <Stack gap="sm">
            <Skeleton height={80} radius="md" />
            <Skeleton height={80} radius="md" />
            <Skeleton height={80} radius="md" />
          </Stack>
        ) : (
          <ScrollArea h={450} offsetScrollbars>
            <Stack gap="md" pr="sm">
              {meritos.length > 0 && (
                <Stack gap="sm">
                  <Group gap="xs">
                    <Text size="sm" fw={700}>
                      📋 Méritos
                    </Text>
                    <Badge size="sm" variant="light" color="blue">
                      {totalMeritos.toFixed(2)} pts
                    </Badge>
                  </Group>
                  {meritos.map((c, i) => (
                    <Card key={c.id} withBorder radius="md" p="sm">
                      <Stack gap="sm">
                        <Group justify="space-between" wrap="nowrap">
                          <Group gap="xs">
                            <ThemeIcon
                              size="xs" color="blue" variant="light"
                            >
                              {TIPO_ICONS[c.tipo_input]}
                            </ThemeIcon>
                            <Text size="sm" fw={500}>
                              {i + 1}. {c.nombre}
                            </Text>
                          </Group>
                          <Badge size="xs" variant="light" color="blue">
                            Máx: {c.puntaje_maximo} pts
                          </Badge>
                        </Group>
                        {c.descripcion && (
                          <Text size="xs" c="dimmed">
                            {c.descripcion}
                          </Text>
                        )}
                        <CriterioInput
                          criterio={c}
                          estado={estados[c.id] ?? {}}
                          onChange={(val) =>
                            setEstados(prev => ({
                              ...prev,
                              [c.id]: { ...prev[c.id], ...val },
                            }))
                          }
                        />
                        <Text size="xs" c="blue" ta="right">
                          Puntaje: {calcularPuntajeCriterio(
                            c, estados[c.id] ?? {}
                          ).toFixed(2)} pts
                        </Text>
                      </Stack>
                    </Card>
                  ))}
                </Stack>
              )}

              {oposicion.length > 0 && (
                <>
                  <Divider />
                  <Stack gap="sm">
                    <Group gap="xs">
                      <Text size="sm" fw={700}>
                        🎯 Oposición
                      </Text>
                      <Badge size="sm" variant="light" color="orange">
                        {totalOposicion.toFixed(2)} pts
                      </Badge>
                    </Group>
                    {oposicion.map((c, i) => (
                      <Card key={c.id} withBorder radius="md" p="sm">
                        <Stack gap="sm">
                          <Group justify="space-between" wrap="nowrap">
                            <Group gap="xs">
                              <ThemeIcon
                                size="xs" color="orange" variant="light"
                              >
                                {TIPO_ICONS[c.tipo_input]}
                              </ThemeIcon>
                              <Text size="sm" fw={500}>
                                {i + 1}. {c.nombre}
                              </Text>
                            </Group>
                            <Badge size="xs" variant="light" color="orange">
                              Máx: {c.puntaje_maximo} pts
                            </Badge>
                          </Group>
                          {c.descripcion && (
                            <Text size="xs" c="dimmed">
                              {c.descripcion}
                            </Text>
                          )}
                          <CriterioInput
                            criterio={c}
                            estado={estados[c.id] ?? {}}
                            onChange={(val) =>
                              setEstados(prev => ({
                                ...prev,
                                [c.id]: { ...prev[c.id], ...val },
                              }))
                            }
                          />
                          <Text size="xs" c="orange" ta="right">
                            Puntaje: {calcularPuntajeCriterio(
                              c, estados[c.id] ?? {}
                            ).toFixed(2)} pts
                          </Text>
                        </Stack>
                      </Card>
                    ))}
                  </Stack>
                </>
              )}
            </Stack>
          </ScrollArea>
        )}

        <Group justify="space-between" mt="sm">
          <Text size="xs" c="dimmed">
            Méritos: {totalMeritos.toFixed(2)} +
            Oposición: {totalOposicion.toFixed(2)} =
            <Text span fw={700} c={aprueba ? 'emerald' : 'red'}>
              {' '}{total.toFixed(2)} pts
            </Text>
          </Text>
          <Group gap="xs">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={guardar.isPending}
              disabled={criterios.length === 0}
              onClick={handleGuardar}
            >
              Guardar calificación
            </Button>
          </Group>
        </Group>
      </Stack>
    </Modal>
  )
}
