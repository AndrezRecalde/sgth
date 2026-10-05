'use client'

import { Alert, Grid, Stack, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { useInscribirPostulante } from '../hooks/useConvocatoria'
import { CAMPOS_INSCRIPCION, esquemaInscripcion, type InscripcionFormData } from '../schemas/postulante.schema'
import { DatosPostulanteCampos } from './inscripcion/DatosPostulanteCampos'

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

// Sin género a propósito: es obligatorio y nadie debe elegirlo por el usuario.
const vacio = (): DefaultValues<InscripcionFormData> => ({
  cedula: '', nombres: '', segundo_nombre: '', apellidos: '', segundo_apellido: '',
  correo: '', telefono: '', estado_civil: null, fecha_nacimiento: null, tipo_sangre: null,
  puesto_id: null, fecha_inscripcion: hoyIso(),
})

/** Inscribir a un candidato en una convocatoria formal o en un contenedor express. */
export function InscribirPostulanteModal({ opened, onClose, convocatoriaId, requierePuesto = false }: Props) {
  const contained = useContainedInput()
  const inscribir = useInscribirPostulante(convocatoriaId)

  const { control, register, handleSubmit, reset, setError, formState: { errors } } = useForm<InscripcionFormData>({
    resolver: zodResolver(esquemaInscripcion(requierePuesto)),
    defaultValues: vacio(),
  })

  const cerrar = () => { reset(vacio()); onClose() }

  // Los 422 del backend —una cédula ya inscrita, un puesto inexistente— van
  // a su campo; antes solo salían en una notificación.
  const enviar = ({ puesto_id, fecha_inscripcion, ...datos }: InscripcionFormData) =>
    inscribir.mutateAsync(requierePuesto ? { ...datos, puesto_id, fecha_inscripcion } : datos)
      .then(cerrar)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS_INSCRIPCION, 'No se pudo inscribir al candidato'))

  return (
    <FormModal opened={opened} onClose={cerrar} title="Inscribir candidato" size="xl"
      onSubmit={handleSubmit(enviar)} submitLabel="Inscribir candidato" submitting={inscribir.isPending}>
      <Stack gap="md">
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Ingrese los datos del candidato tal como aparecen en su cédula de
            ciudadanía. Los datos demográficos se copiarán al expediente si el
            candidato es seleccionado.
          </Text>
        </Alert>

        {requierePuesto && (
          <>
            <SectionHeading title="Vacante a la que aspira" />
            <Grid>
              <Grid.Col span={{ base: 12, md: 8 }}>
                <Controller name="puesto_id" control={control} render={({ field }) => (
                  <BuscarPuestoSelect label="Puesto" required value={field.value}
                    onChange={(id) => field.onChange(id)} error={errors.puesto_id?.message} />
                )} />
              </Grid.Col>
              <Grid.Col span={{ base: 12, md: 4 }}>
                <Controller name="fecha_inscripcion" control={control} render={({ field }) => (
                  <DatePickerInput label="Fecha de inscripción" description="Define el año en que se contabiliza."
                    valueFormat="DD/MM/YYYY" {...contained} value={toDateValue(field.value)}
                    onChange={(v) => field.onChange(fromDateValueOrNull(v))} error={errors.fecha_inscripcion?.message} />
                )} />
              </Grid.Col>
            </Grid>
          </>
        )}

        <DatosPostulanteCampos control={control} register={register} errors={errors} />
      </Stack>
    </FormModal>
  )
}
