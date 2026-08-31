'use client'

import {
  Modal, Stack, TextInput, 
  NumberInput, Textarea, Button,
  Group, Text, ActionIcon, Card,
  SegmentedControl, Divider,
} from '@mantine/core'
import { IconPlus, IconTrash, IconCheck } from '@tabler/icons-react'
import { useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCrearCriterio } from '../hooks/useCriterio'
import type { SeccionCriterio, TipoInput } from '../services/criterioService'

interface Props {
  opened:         boolean
  onClose:        () => void
  convocatoriaId: number
  seccionInicial: SeccionCriterio
}

const schema = z.object({
  nombre:         z.string().min(3, 'Mínimo 3 caracteres'),
  descripcion:    z.string().optional().nullable(),
  puntaje_maximo: z.number().min(0.5, 'Mínimo 0.5 puntos'),
  tipo_input:     z.enum(['radio', 'numero', 'checklist']),
})

type FormData = z.infer<typeof schema>

interface OpcionTemp {
  etiqueta: string
  puntaje:  number
}

const TIPO_LABELS: Record<TipoInput, string> = {
  radio:     'Opción única',
  checklist: 'Selección múltiple',
  numero:    'Valor numérico',
}

const TIPO_DESCRIPTIONS: Record<TipoInput, string> = {
  radio:     'El evaluador selecciona UNA opción (ej: nivel de instrucción)',
  checklist: 'El evaluador puede seleccionar VARIAS opciones acumulables',
  numero:    'El evaluador ingresa un número directamente (ej: puntaje de prueba)',
}

export function AgregarCriterioModal({
  opened, onClose, convocatoriaId, seccionInicial,
}: Props) {
  const contained = useContainedInput()
  const crear     = useCrearCriterio(convocatoriaId)
  const [seccion, setSeccion] =
    useState<SeccionCriterio>(seccionInicial)
  const [opciones, setOpciones] = useState<OpcionTemp[]>([
    { etiqueta: '', puntaje: 0 },
  ])

  const {
    control, register, handleSubmit, watch, reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      tipo_input:     'radio',
      puntaje_maximo: 10,
    },
  })

  const tipoInput = watch('tipo_input') as TipoInput

  const handleClose = () => {
    reset()
    setOpciones([{ etiqueta: '', puntaje: 0 }])
    setSeccion(seccionInicial)
    onClose()
  }

  const agregarOpcion = () =>
    setOpciones(prev => [...prev, { etiqueta: '', puntaje: 0 }])

  const eliminarOpcion = (i: number) =>
    setOpciones(prev => prev.filter((_, idx) => idx !== i))

  const actualizarOpcion = (
    i: number,
    campo: keyof OpcionTemp,
    valor: string | number
  ) =>
    setOpciones(prev =>
      prev.map((op, idx) =>
        idx === i ? { ...op, [campo]: valor } : op
      )
    )

  const onSubmit = (values: FormData) => {
    const data = {
      ...values,
      seccion,
      opciones: tipoInput !== 'numero'
        ? opciones.filter(o => o.etiqueta.trim())
        : undefined,
    }
    crear.mutate(data, { onSuccess: handleClose })
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Agregar criterio de evaluación"
      size="lg"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="md">
          <Stack gap="xs">
            <Text size="xs" fw={600} c="dimmed" tt="uppercase"
              style={{ letterSpacing: '0.05em' }}>
              Sección
            </Text>
            <SegmentedControl
              value={seccion}
              onChange={(v) => setSeccion(v as SeccionCriterio)}
              data={[
                { label: 'Méritos (hoja de vida)', value: 'meritos' },
                { label: 'Oposición (evaluación directa)', value: 'oposicion' },
              ]}
              fullWidth
            />
          </Stack>

          <TextInput
            label="Nombre del criterio"
            placeholder="Ej: Instrucción formal, Prueba técnica"
            required
            {...contained}
            {...register('nombre')}
            error={errors.nombre?.message}
          />

          <Textarea
            label="Descripción"
            placeholder="Descripción del criterio y cómo se evalúa"
            autosize
            minRows={2}
            {...contained}
            {...register('descripcion')}
          />

          <Divider label="Configuración del criterio" />

          <Controller
            name="tipo_input"
            control={control}
            render={({ field }) => (
              <Stack gap="xs">
                <Text size="sm" fw={500}>
                  Tipo de evaluación
                </Text>
                <SegmentedControl
                  value={field.value}
                  onChange={field.onChange}
                  data={Object.entries(TIPO_LABELS).map(
                    ([value, label]) => ({ value, label })
                  )}
                  fullWidth
                />
                <Text size="xs" c="dimmed">
                  {TIPO_DESCRIPTIONS[field.value as TipoInput]}
                </Text>
              </Stack>
            )}
          />

          <Controller
            name="puntaje_maximo"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Puntaje máximo"
                description="Máximo de puntos que puede obtener en este criterio"
                min={0.5}
                max={100}
                decimalScale={2}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(Number(v) || 0)}
                error={errors.puntaje_maximo?.message}
              />
            )}
          />

          {tipoInput !== 'numero' && (
            <Stack gap="xs">
              <Group justify="space-between">
                <Text size="sm" fw={500}>
                  Opciones de calificación
                </Text>
                <Button
                  size="compact-xs"
                  variant="subtle"
                  leftSection={<IconPlus size={12} />}
                  onClick={agregarOpcion}
                >
                  Agregar opción
                </Button>
              </Group>

              {opciones.map((op, i) => (
                <Card key={i} withBorder radius="md" p="sm">
                  <Group gap="sm" wrap="nowrap">
                    <TextInput
                      placeholder="Ej: Título de 4to nivel"
                      style={{ flex: 1 }}
                      size="sm"
                      {...contained}
                      value={op.etiqueta}
                      onChange={(e) =>
                        actualizarOpcion(i, 'etiqueta', e.currentTarget.value)
                      }
                    />
                    <NumberInput
                      placeholder="Pts"
                      style={{ width: 80 }}
                      size="sm"
                      min={0}
                      decimalScale={2}
                      {...contained}
                      value={op.puntaje}
                      onChange={(v) =>
                        actualizarOpcion(i, 'puntaje', Number(v) || 0)
                      }
                    />
                    <ActionIcon
                      size="sm"
                      color="red"
                      variant="subtle"
                      onClick={() => eliminarOpcion(i)}
                      disabled={opciones.length === 1}
                    >
                      <IconTrash size={13} />
                    </ActionIcon>
                  </Group>
                </Card>
              ))}
            </Stack>
          )}

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={crear.isPending}
            >
              Agregar criterio
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
