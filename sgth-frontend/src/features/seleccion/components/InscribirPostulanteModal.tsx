'use client'

import {
  Modal, Stack, TextInput, Button,
  Group, Text, Alert, Select,
  Grid, Divider,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import {
  IconCheck, IconInfoCircle,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useInscribirPostulante } from '../hooks/useConvocatoria'

interface Props {
  opened:         boolean
  onClose:        () => void
  convocatoriaId: number
  /**
   * En los contenedores express el puesto lo trae el aspirante, porque el
   * contenedor agrupa por modalidad y no por vacante. En un concurso formal
   * el puesto lo fija la convocatoria y enviarlo es un error de validación.
   */
  requierePuesto?: boolean
}

const GENERO_OPTIONS = [
  { value: 'masculino', label: 'Masculino' },
  { value: 'femenino',  label: 'Femenino'  },
  { value: 'otro',      label: 'Otro'      },
]

const ESTADO_CIVIL_OPTIONS = [
  { value: 'soltero',     label: 'Soltero/a'        },
  { value: 'casado',      label: 'Casado/a'          },
  { value: 'union_libre', label: 'Unión de hecho'    },
  { value: 'divorciado',  label: 'Divorciado/a'      },
  { value: 'viudo',       label: 'Viudo/a'           },
]

const TIPO_SANGRE_OPTIONS = [
  'A+','A-','B+','B-','AB+','AB-','O+','O-',
].map(v => ({ value: v, label: v }))

const schema = z.object({
  cedula:           z.string().min(8, 'Cédula inválida').max(20),
  nombres:          z.string().min(2, 'Ingrese el primer nombre'),
  segundo_nombre:   z.string().optional().nullable(),
  apellidos:        z.string().min(2, 'Ingrese el primer apellido'),
  segundo_apellido: z.string().optional().nullable(),
  correo:           z.email('Correo inválido'),
  telefono:         z.string().optional().nullable(),
  /**
   * Obligatorio: al incorporar al aspirante este valor se copia a su
   * expediente de servidor, donde el género es requerido. Además la ficha
   * FEMO lo usa para decidir qué bloque reproductivo del MSP mostrar.
   */
  genero:           z.enum(['masculino', 'femenino', 'otro'], {
    message: 'Seleccione el género',
  }),
  estado_civil:     z.string().optional().nullable(),
  fecha_nacimiento: z.string().optional().nullable(),
  tipo_sangre:      z.string().optional().nullable(),
})

type FormData = z.infer<typeof schema>

function fromDate(d: Date | string | null): string | null {
  if (!d) return null
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

export function InscribirPostulanteModal({
  opened, onClose, convocatoriaId, requierePuesto = false,
}: Props) {
  const contained = useContainedInput()
  const inscribir = useInscribirPostulante(convocatoriaId)

  const [puestoId, setPuestoId] = useState<number | null>(null)
  const [fechaInscripcion, setFechaInscripcion] = useState<Date | null>(new Date())
  const [errorPuesto, setErrorPuesto] = useState<string | null>(null)

  const {
    control, register, handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    // Sin `defaultValues` los campos arrancan no controlados y React avisa en
    // consola la primera vez que se escribe en cada uno. `genero` se deja sin
    // valor a propósito: es obligatorio y nadie debe elegirlo por el usuario.
    defaultValues: {
      cedula: '',
      nombres: '',
      segundo_nombre: '',
      apellidos: '',
      segundo_apellido: '',
      correo: '',
      telefono: '',
      estado_civil: null,
      fecha_nacimiento: null,
      tipo_sangre: null,
    },
  })

  const handleClose = () => {
    reset()
    setPuestoId(null)
    setFechaInscripcion(new Date())
    setErrorPuesto(null)
    onClose()
  }

  const onSubmit = (values: FormData) => {
    if (requierePuesto && !puestoId) {
      setErrorPuesto('Seleccione el puesto al que aspira.')
      return
    }

    setErrorPuesto(null)

    inscribir.mutate(
      requierePuesto
        ? {
          ...values,
          puesto_id: puestoId,
          fecha_inscripcion: fromDate(fechaInscripcion),
        }
        : values,
      { onSuccess: handleClose },
    )
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Inscribir candidato"
      size="xl"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="md">
          <Alert
            color="blue"
            variant="light"
            icon={<IconInfoCircle size={16} />}
          >
            <Text size="xs">
              Ingrese los datos del candidato tal como aparecen
              en su cédula de ciudadanía. Los datos demográficos
              se copiarán automáticamente al expediente si el
              candidato es seleccionado.
            </Text>
          </Alert>

          {requierePuesto && (
            <Stack gap="xs">
              <Text size="xs" fw={600} c="dimmed" tt="uppercase"
                style={{ letterSpacing: '0.05em' }}>
                Vacante a la que aspira
              </Text>
              <Grid>
                <Grid.Col span={{ base: 12, md: 8 }}>
                  <BuscarPuestoSelect
                    label="Puesto"
                    value={puestoId}
                    onChange={setPuestoId}
                    error={errorPuesto ?? undefined}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, md: 4 }}>
                  <DatePickerInput
                    label="Fecha de inscripción"
                    description="Define el año en que se contabiliza."
                    value={fechaInscripcion}
                    onChange={(v) => setFechaInscripcion(v as Date | null)}
                    valueFormat="DD/MM/YYYY"
                    {...contained}
                  />
                </Grid.Col>
              </Grid>
              <Divider />
            </Stack>
          )}

          <Stack gap="xs">
            <Text size="xs" fw={600} c="dimmed" tt="uppercase"
              style={{ letterSpacing: '0.05em' }}>
              Identificación
            </Text>
            <Grid>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <TextInput
                  label="Cédula de ciudadanía"
                  placeholder="0802704171"
                  required
                  {...contained}
                  {...register('cedula')}
                  error={errors.cedula?.message}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <TextInput
                  label="Correo electrónico"
                  placeholder="candidato@correo.com"
                  description="Para notificaciones del proceso"
                  required
                  {...contained}
                  {...register('correo')}
                  error={errors.correo?.message}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <TextInput
                  label="Teléfono"
                  placeholder="0991234567"
                  {...contained}
                  {...register('telefono')}
                />
              </Grid.Col>
            </Grid>
          </Stack>

          <Divider />

          <Stack gap="xs">
            <Text size="xs" fw={600} c="dimmed" tt="uppercase"
              style={{ letterSpacing: '0.05em' }}>
              Nombres y apellidos
            </Text>
            <Grid>
              <Grid.Col span={{ base: 12, md: 6 }}>
                <TextInput
                  label="Primer nombre"
                  placeholder="Primer nombre"
                  required
                  {...contained}
                  {...register('nombres')}
                  error={errors.nombres?.message}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 6 }}>
                <TextInput
                  label="Segundo nombre"
                  placeholder="Opcional"
                  {...contained}
                  {...register('segundo_nombre')}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 6 }}>
                <TextInput
                  label="Primer apellido"
                  placeholder="Primer apellido"
                  required
                  {...contained}
                  {...register('apellidos')}
                  error={errors.apellidos?.message}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 6 }}>
                <TextInput
                  label="Segundo apellido"
                  placeholder="Opcional"
                  {...contained}
                  {...register('segundo_apellido')}
                />
              </Grid.Col>
            </Grid>
          </Stack>

          <Divider />

          <Stack gap="xs">
            <Text size="xs" fw={600} c="dimmed" tt="uppercase"
              style={{ letterSpacing: '0.05em' }}>
              Datos demográficos
            </Text>
            <Grid>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller
                  name="genero"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Género"
                      data={GENERO_OPTIONS}
                      required
                      {...contained}
                      value={field.value ?? null}
                      onChange={(v) => field.onChange(v)}
                      error={errors.genero?.message}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller
                  name="estado_civil"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Estado civil"
                      data={ESTADO_CIVIL_OPTIONS}
                      clearable
                      {...contained}
                      value={field.value ?? null}
                      onChange={(v) => field.onChange(v)}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller
                  name="tipo_sangre"
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Tipo de sangre"
                      data={TIPO_SANGRE_OPTIONS}
                      clearable
                      {...contained}
                      value={field.value ?? null}
                      onChange={(v) => field.onChange(v)}
                    />
                  )}
                />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller
                  name="fecha_nacimiento"
                  control={control}
                  render={({ field }) => (
                    <DatePickerInput
                      label="Fecha de nacimiento"
                      valueFormat="DD/MM/YYYY"
                      clearable
                      maxDate={new Date()}
                      {...contained}
                      value={toDate(field.value)}
                      onChange={(d) =>
                        field.onChange(fromDate(d as Date | null))
                      }
                    />
                  )}
                />
              </Grid.Col>
            </Grid>
          </Stack>

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={inscribir.isPending}
            >
              Inscribir candidato
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
