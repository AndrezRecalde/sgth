'use client'

import {
  Stack, TextInput, 
  Text, Alert, Select,
  Grid, Divider,
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { DatePickerInput } from '@mantine/dates'
import {
  IconInfoCircle,
} from '@tabler/icons-react'
import { useForm, Controller } from 'react-hook-form'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useInscribirPostulante } from '../hooks/useConvocatoria'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { CAMPOS_INSCRIPCION, esquemaInscripcion, type InscripcionFormData } from '../schemas/postulante.schema'
import { ESTADO_CIVIL_OPTIONS, GENERO_OPTIONS, TIPO_SANGRE_OPTIONS } from '../services/postulanteOptions'

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

type FormData = InscripcionFormData

export function InscribirPostulanteModal({
  opened, onClose, convocatoriaId, requierePuesto = false,
}: Props) {
  const contained = useContainedInput()
  const inscribir = useInscribirPostulante(convocatoriaId)

  const {
    control, register, handleSubmit,
    reset, setError,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(esquemaInscripcion(requierePuesto)),
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
      puesto_id: null,
      fecha_inscripcion: hoyIso(),
    },
  })

  const handleClose = () => {
    reset()
    onClose()
  }

  // Los 422 del backend —una cédula ya inscrita, un puesto inexistente— van
  // a su campo; antes solo salían en una notificación.
  const onSubmit = ({ puesto_id, fecha_inscripcion, ...datos }: FormData) =>
    inscribir.mutateAsync(requierePuesto ? { ...datos, puesto_id, fecha_inscripcion } : datos)
      .then(handleClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS_INSCRIPCION, 'No se pudo inscribir al candidato'))

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title="Inscribir candidato"
      size="xl"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Inscribir candidato"
      submitting={inscribir.isPending}
    >
      <Stack gap="md">
        <Alert
          color="ocean"
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
                <Controller name="puesto_id" control={control} render={({ field }) => (
                  <BuscarPuestoSelect
                    label="Puesto"
                    required
                    value={field.value}
                    onChange={(id) => field.onChange(id)}
                    error={errors.puesto_id?.message}
                  />
                )} />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller name="fecha_inscripcion" control={control} render={({ field }) => (
                  <DatePickerInput
                    label="Fecha de inscripción"
                    description="Define el año en que se contabiliza."
                    value={toDateValue(field.value)}
                    onChange={(v) => field.onChange(fromDateValueOrNull(v))}
                    valueFormat="DD/MM/YYYY"
                    {...contained}
                    error={errors.fecha_inscripcion?.message}
                  />
                )} />
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
                    value={toDateValue(field.value)}
                    onChange={(d) =>
                      field.onChange(fromDateValueOrNull(d))
                    }
                  />
                )}
              />
            </Grid.Col>
          </Grid>
        </Stack>
      </Stack>
    </FormModal>
  )
}
