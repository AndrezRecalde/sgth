'use client'

import { useState } from 'react'
import { Stack, Grid, TextInput, Select, Textarea, NumberInput, Button, Group, Card, Text, Divider, Alert, Anchor } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import Link from 'next/link'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import {
  IconSpeakerphone, IconArrowLeft,
  IconInfoCircle, IconBolt,
} from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { ROUTES } from '@/config/routes'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCrearConvocatoria } from '@/features/seleccion/hooks/useConvocatoria'
import { BuscarPuestoSelect } from
  '@/features/estructura/components/BuscarPuestoSelect'
import { TIPO_CONVOCATORIA_OPTIONS } from
  '@/features/seleccion/services/convocatoriaService'
import type { CrearConvocatoriaData } from
  '@/features/seleccion/services/convocatoriaService'

const schema = z.object({
  puesto_id:    z.number({ error: 'Seleccione el puesto a convocar' }),
  titulo:       z.string().min(5, 'Mínimo 5 caracteres'),
  descripcion:  z.string().min(10, 'Mínimo 10 caracteres'),
  tipo:         z.enum(['interna', 'externa', 'mixta']),
  vacantes:     z.number().min(1, 'Mínimo 1 vacante'),
  // El `error` cubre el campo sin tocar (llega `undefined`, no cadena vacía):
  // sin él, Zod muestra su mensaje de tipo por defecto, en inglés.
  fecha_inicio: z.string({ error: 'Requerido' }).min(1, 'Requerido'),
  fecha_fin:    z.string({ error: 'Requerido' }).min(1, 'Requerido'),
}).superRefine((data, ctx) => {
  // El backend valida 'after:fecha_inicio' y responde 422 sin campo asociado;
  // comprobarlo aquí deja el error junto a la fecha de cierre.
  if (data.fecha_inicio && data.fecha_fin && data.fecha_fin <= data.fecha_inicio) {
    ctx.addIssue({
      code: 'custom', path: ['fecha_fin'],
      message: 'Debe ser posterior a la fecha de inicio',
    })
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

/**
 * Solo crea concursos de méritos y oposición. Las cuatro modalidades que no
 * pasan por concurso no abren una convocatoria por proceso: sus aspirantes se
 * inscriben en los contenedores permanentes de Reclutamiento Express.
 */
export default function NuevaConvocatoriaPage() {
  const router    = useRouter()
  const contained = useContainedInput()
  const crear     = useCrearConvocatoria()
  const [tipoSel, setTipoSel] = useState('externa')

  const {
    control, register, handleSubmit,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
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
      tipo_proceso: 'formal',
      fecha_inicio: values.fecha_inicio,
      fecha_fin:    values.fecha_fin,
    }

    crear.mutate(payload, {
      onSuccess: (conv) =>
        router.push(`${ROUTES.SGTH.CONVOCATORIAS}/${conv.id}`),
    })
  }

  return (
    <PageShell>
      <PageHeader
        title="Nueva convocatoria"
        description="Concurso de méritos y oposición para un puesto del organigrama"
      />

      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="md">
          <Alert
            color="grape"
            variant="light"
            icon={<IconBolt size={16} />}
          >
            <Text size="xs">
              Los nombramientos provisionales, los servicios ocasionales, los
              servicios profesionales y el Código del Trabajo no pasan por
              concurso: sus aspirantes se registran en{' '}
              <Anchor
                component={Link}
                href={ROUTES.SGTH.RECLUTAMIENTO_EXPRESS}
                size="xs"
                fw={600}
              >
                Reclutamiento Express
              </Anchor>.
            </Text>
          </Alert>

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
                            ? 'Interna — Solo servidores del GADPE'
                            : o.value === 'externa'
                              ? 'Externa — Público en general'
                              : 'Mixta — Interna y externa',
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
            </Stack>
          </Card>

          <Group justify="space-between">
            <Button
              variant="default"
              leftSection={<IconArrowLeft size={14} />}
              onClick={() => router.push(ROUTES.SGTH.CONVOCATORIAS)}
            >
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconSpeakerphone size={14} />}
              loading={crear.isPending}
            >
              Crear convocatoria
            </Button>
          </Group>
        </Stack>
      </form>
    </PageShell>
  )
}

import { PageHeader, PageShell } from '@/components/ui'
