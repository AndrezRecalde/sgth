'use client'

import { useState } from 'react'
import {
  Stack, Grid, TextInput, Select,
  Textarea, NumberInput, Button,
  Group, Card, Text, Divider,
  Alert, SegmentedControl,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import {
  IconSpeakerphone, IconArrowLeft,
  IconInfoCircle, IconBolt,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { PageHeader } from '@/components/ui/PageHeader'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useCrearConvocatoria, usePublicarConvocatoria,
} from '@/features/seleccion/hooks/useConvocatoria'
import { useAplicarPlantilla, usePlantillas } from
  '@/features/seleccion/hooks/usePlantilla'
import { BuscarPuestoSelect } from
  '@/features/estructura/components/BuscarPuestoSelect'
import {
  TIPO_CONVOCATORIA_OPTIONS,
  TIPO_NOMBRAMIENTO_PREVISTO_OPTIONS,
} from '@/features/seleccion/services/convocatoriaService'
import type { CrearConvocatoriaData } from
  '@/features/seleccion/services/convocatoriaService'

const schema = z.object({
  tipo_proceso: z.enum(['formal', 'express']),
  puesto_id:    z.number({ error: 'Seleccione el puesto a convocar' }),
  titulo:       z.string().min(5, 'Mínimo 5 caracteres'),
  descripcion:  z.string().min(10, 'Mínimo 10 caracteres'),
  tipo:         z.enum(['interna', 'externa', 'mixta']),
  vacantes:     z.number().min(1, 'Mínimo 1 vacante'),
  fecha_inicio: z.string().optional(),
  fecha_fin:    z.string().optional(),
  tipo_nombramiento_previsto: z.string().optional(),
  plantilla_id: z.number().optional(),
}).superRefine((data, ctx) => {
  if (data.tipo_proceso === 'formal') {
    if (!data.fecha_inicio) {
      ctx.addIssue({ code: 'custom', path: ['fecha_inicio'], message: 'Requerido' })
    }
    if (!data.fecha_fin) {
      ctx.addIssue({ code: 'custom', path: ['fecha_fin'], message: 'Requerido' })
    }
  } else {
    if (!data.tipo_nombramiento_previsto) {
      ctx.addIssue({
        code: 'custom', path: ['tipo_nombramiento_previsto'],
        message: 'Seleccione el tipo de nombramiento previsto',
      })
    }
    if (!data.plantilla_id) {
      ctx.addIssue({
        code: 'custom', path: ['plantilla_id'],
        message: 'Seleccione una plantilla de evaluación',
      })
    }
  }
})

type FormData = z.infer<typeof schema>

const TIPO_DESCRIPTIONS: Record<string, string> = {
  interna: 'Solo pueden postular servidores activos del GADPE',
  externa: 'Abierta al público en general',
  mixta:   'Abierta a servidores del GADPE y al público en general',
}

function fromDate(d: Date | string | null): string {
  if (!d) return ''
  if (typeof d === 'string') return d.slice(0, 10)
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

function toDate(s: string | null | undefined): Date | null {
  if (!s) return null
  const [y, m, d] = s.split('-').map(Number)
  return new Date(y, m - 1, d)
}

export default function NuevaConvocatoriaPage() {
  const router    = useRouter()
  const contained = useContainedInput()
  const crear     = useCrearConvocatoria()
  const publicar  = usePublicarConvocatoria()
  const aplicarPlantilla = useAplicarPlantilla()
  const { data: plantillas = [] } = usePlantillas()
  const [tipoSel, setTipoSel] = useState('externa')
  const [tipoProceso, setTipoProceso] = useState<'formal' | 'express'>('formal')

  const {
    control, register, handleSubmit, setValue,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      tipo_proceso: 'formal',
      tipo:     'externa',
      vacantes: 1,
    },
  })

  const onSubmit = (values: FormData) => {
    const payload: CrearConvocatoriaData = {
      puesto_id:    values.puesto_id,
      titulo:       values.titulo,
      descripcion:  values.descripcion,
      tipo:         values.tipo,
      vacantes:     values.vacantes,
      tipo_proceso: values.tipo_proceso,
      ...(values.tipo_proceso === 'formal'
        ? { fecha_inicio: values.fecha_inicio, fecha_fin: values.fecha_fin }
        : { tipo_nombramiento_previsto: values.tipo_nombramiento_previsto }),
    }

    crear.mutate(payload, {
      onSuccess: (conv) => {
        if (values.tipo_proceso !== 'express') {
          router.push(`/sgth/reclutamiento/convocatorias/${conv.id}`)
          return
        }

        // Express encadena 3 pasos: crear() -> aplicar plantilla ->
        // publicar() -> navegar con el modal de inscripción abierto.
        // aplicarPlantilla ya muestra sus propios toasts de éxito/error
        // (useAplicarPlantilla, compartido con el flujo formal).
        aplicarPlantilla.mutate(
          { plantillaId: values.plantilla_id!, convocatoriaId: conv.id },
          {
            onSuccess: () => {
              publicar.mutate(conv.id, {
                onSuccess: () =>
                  router.push(`/sgth/reclutamiento/convocatorias/${conv.id}?inscribir=1`),
                // publicar() ya muestra su propio toast de error. La
                // convocatoria existe en 'borrador' con criterios ya
                // aplicados — navegamos sin abrir el modal, dejando
                // visible el botón manual "Publicar convocatoria".
                onError: () =>
                  router.push(`/sgth/reclutamiento/convocatorias/${conv.id}`),
              })
            },
            // Si falla aplicar la plantilla, NO se intenta publicar —
            // la guarda del backend lo rechazaría igual sin criterios.
            // La convocatoria sigue en 'borrador': navegamos para que
            // el tab de Criterios (editable) permita reintentar a mano.
            onError: () =>
              router.push(`/sgth/reclutamiento/convocatorias/${conv.id}`),
          }
        )
      },
    })
  }

  return (
    <Stack gap="md">
      <PageHeader
        title="Nueva convocatoria"
        subtitle="Crear proceso de selección de personal"
        icon={<IconSpeakerphone size={24} />}
      />

      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="md">
          <Card withBorder radius="lg" p="lg">
            <Stack gap="sm">
              <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                style={{ letterSpacing: '0.05em' }}>
                Tipo de proceso
              </Text>
              <Controller
                name="tipo_proceso"
                control={control}
                render={({ field }) => (
                  <SegmentedControl
                    fullWidth
                    value={field.value}
                    onChange={(v) => {
                      field.onChange(v)
                      setTipoProceso(v as 'formal' | 'express')
                    }}
                    data={[
                      { label: 'Concurso formal', value: 'formal' },
                      { label: '⚡ Reclutamiento Express', value: 'express' },
                    ]}
                  />
                )}
              />
              <Text size="xs" c="dimmed">
                {tipoProceso === 'formal'
                  ? 'Concurso de méritos y oposición con plazo público de inscripción.'
                  : 'Proceso abreviado para incorporación inmediata, sin plazo público de inscripción.'}
              </Text>
            </Stack>
          </Card>

          <Card withBorder radius="lg" p="lg">
            <Stack gap="md">
              <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                style={{ letterSpacing: '0.05em' }}>
                Puesto a convocar
              </Text>

              <Controller
                name="puesto_id"
                control={control}
                render={({ field }) => (
                  <BuscarPuestoSelect
                    label="Puesto del organigrama"
                    description="Busque el puesto por nombre del cargo o unidad administrativa"
                    required
                    value={field.value ?? null}
                    onChange={(id) => field.onChange(id)}
                    error={errors.puesto_id?.message}
                  />
                )}
              />

              <TextInput
                label="Título de la convocatoria"
                placeholder="Ej: Concurso de méritos y oposición — Técnico en Sistemas SP3"
                description="Nombre oficial del proceso que aparecerá en la convocatoria pública"
                required
                {...contained}
                {...register('titulo')}
                error={errors.titulo?.message}
              />

              <Textarea
                label="Descripción del proceso"
                placeholder="Describa el proceso, los requisitos generales y el perfil del cargo"
                required
                autosize
                minRows={3}
                {...contained}
                {...register('descripcion')}
                error={errors.descripcion?.message}
              />
            </Stack>
          </Card>

          <Card withBorder radius="lg" p="lg">
            <Stack gap="md">
              <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                style={{ letterSpacing: '0.05em' }}>
                Configuración del proceso
              </Text>

              <Grid>
                <Grid.Col span={{ base: 12, md: 6 }}>
                  <Controller
                    name="tipo"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Modalidad de la convocatoria"
                        description="Define quiénes pueden postular"
                        data={TIPO_CONVOCATORIA_OPTIONS.map(o => ({
                          ...o,
                          label: o.value === 'interna'
                            ? '🏛️ Interna — Solo servidores del GADPE'
                            : o.value === 'externa'
                              ? '🌐 Externa — Público en general'
                              : '🔀 Mixta — Interna y externa',
                        }))}
                        required
                        {...contained}
                        value={field.value}
                        onChange={(v) => {
                          field.onChange(v ?? 'externa')
                          setTipoSel(v ?? 'externa')
                        }}
                        error={errors.tipo?.message}
                      />
                    )}
                  />
                  {tipoSel && (
                    <Text size="xs" c="dimmed" mt={4}>
                      {TIPO_DESCRIPTIONS[tipoSel]}
                    </Text>
                  )}
                </Grid.Col>

                <Grid.Col span={{ base: 12, md: 6 }}>
                  <Controller
                    name="vacantes"
                    control={control}
                    render={({ field }) => (
                      <NumberInput
                        label="Número de vacantes"
                        description="Cuántas personas se incorporarán en este proceso"
                        min={1}
                        max={50}
                        required
                        {...contained}
                        value={field.value}
                        onChange={(v) =>
                          field.onChange(Number(v) || 1)
                        }
                        error={errors.vacantes?.message}
                      />
                    )}
                  />
                </Grid.Col>
              </Grid>

              {tipoProceso === 'express' && (
                <>
                  <Controller
                    name="tipo_nombramiento_previsto"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Tipo de nombramiento previsto"
                        description="Tipo de vínculo que tendrá quien gane este proceso express"
                        data={TIPO_NOMBRAMIENTO_PREVISTO_OPTIONS}
                        required
                        {...contained}
                        value={field.value ?? null}
                        onChange={(v) => field.onChange(v ?? undefined)}
                        error={errors.tipo_nombramiento_previsto?.message}
                      />
                    )}
                  />

                  <Controller
                    name="plantilla_id"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Plantilla de evaluación"
                        description="Los criterios de esta plantilla se aplicarán automáticamente a la convocatoria"
                        placeholder="Seleccione una plantilla"
                        data={plantillas.filter(p => p.activa).map(p => ({
                          value: String(p.id),
                          label: `${p.nombre} (${p.criterios_count ?? 0} criterios)`,
                        }))}
                        required
                        {...contained}
                        value={field.value ? String(field.value) : null}
                        onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                        error={errors.plantilla_id?.message}
                      />
                    )}
                  />
                </>
              )}

              {tipoProceso === 'formal' ? (
                <>
                  <Divider label="Período del proceso" labelPosition="left" />

                  <Alert
                    color="blue"
                    variant="light"
                    icon={<IconInfoCircle size={16} />}
                  >
                    <Text size="xs">
                      Las fechas definen el período oficial de la convocatoria.
                      La inscripción de candidatos estará disponible mientras
                      la convocatoria esté en estado <strong>Publicada</strong>.
                    </Text>
                  </Alert>

                  <Grid>
                    <Grid.Col span={{ base: 12, md: 6 }}>
                      <Controller
                        name="fecha_inicio"
                        control={control}
                        render={({ field }) => (
                          <DatePickerInput
                            label="Fecha de inicio"
                            description="Inicio oficial del proceso de selección"
                            valueFormat="DD/MM/YYYY"
                            required
                            {...contained}
                            value={toDate(field.value)}
                            onChange={(d) =>
                              field.onChange(fromDate(d as Date | null))
                            }
                            error={errors.fecha_inicio?.message}
                          />
                        )}
                      />
                    </Grid.Col>
                    <Grid.Col span={{ base: 12, md: 6 }}>
                      <Controller
                        name="fecha_fin"
                        control={control}
                        render={({ field }) => (
                          <DatePickerInput
                            label="Fecha de cierre"
                            description="Fecha límite de inscripción de candidatos"
                            valueFormat="DD/MM/YYYY"
                            required
                            {...contained}
                            value={toDate(field.value)}
                            onChange={(d) =>
                              field.onChange(fromDate(d as Date | null))
                            }
                            error={errors.fecha_fin?.message}
                          />
                        )}
                      />
                    </Grid.Col>
                  </Grid>
                </>
              ) : (
                <Alert
                  color="grape"
                  variant="light"
                  icon={<IconBolt size={16} />}
                >
                  <Text size="xs">
                    Los procesos express no tienen plazo público de
                    inscripción — las fechas se completan automáticamente
                    con la fecha actual al crear la convocatoria.
                  </Text>
                </Alert>
              )}
            </Stack>
          </Card>

          <Group justify="space-between">
            <Button
              variant="default"
              leftSection={<IconArrowLeft size={14} />}
              onClick={() =>
                router.push('/sgth/reclutamiento/convocatorias')
              }
            >
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconSpeakerphone size={14} />}
              loading={crear.isPending || aplicarPlantilla.isPending || publicar.isPending}
            >
              Crear convocatoria
            </Button>
          </Group>
        </Stack>
      </form>
    </Stack>
  )
}
