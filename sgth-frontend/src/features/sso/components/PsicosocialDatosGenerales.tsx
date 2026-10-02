'use client'

import { Select, Stack, Text, Title } from '@mantine/core'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type {
  CampoDatoGeneral, CuestionarioPsicosocialFormData,
} from '../schemas/cuestionarioPsicosocial.schema'

interface Props {
  /** `datos_generales_opciones` tal como lo manda el servidor. */
  opciones: Record<string, Record<string, string>>
  control: Control<CuestionarioPsicosocialFormData>
  errors: FieldErrors<CuestionarioPsicosocialFormData>
}

/** Los seis campos, en el orden del cuestionario oficial (ítems D a I). */
const CAMPOS: { campo: CampoDatoGeneral; etiqueta: string }[] = [
  { campo: 'area_trabajo', etiqueta: 'Área de trabajo' },
  { campo: 'nivel_instruccion', etiqueta: 'Nivel de instrucción' },
  { campo: 'antiguedad', etiqueta: 'Antigüedad en la institución' },
  { campo: 'rango_edad', etiqueta: 'Rango de edad' },
  { campo: 'autoidentificacion_etnica', etiqueta: 'Auto-identificación étnica' },
  { campo: 'genero', etiqueta: 'Género' },
]

/**
 * Datos generales (ítems D a I del cuestionario oficial): sociodemográficos,
 * agregados y opcionales.
 *
 * Son opcionales a propósito y así se dice: el instrumento es anónimo por
 * mandato de la guía del MDT y nadie está obligado a declarar su género ni su
 * auto-identificación étnica para poder responder.
 */
export function PsicosocialDatosGenerales({ opciones, control, errors }: Props) {
  const contained = useContainedInput()

  return (
    <Stack gap="sm">
      <Title order={4}>Datos generales</Title>
      <Text size="sm" c="dimmed">
        Estos datos son opcionales, agregados y no permiten identificarle.
      </Text>

      {CAMPOS.map(({ campo, etiqueta }) => (
        <Controller
          key={campo}
          name={campo}
          control={control}
          render={({ field }) => (
            <Select
              label={etiqueta}
              data={Object.entries(opciones[campo] ?? {}).map(([value, label]) => ({ value, label }))}
              clearable
              {...contained}
              value={field.value}
              onChange={field.onChange}
              error={errors[campo]?.message}
            />
          )}
        />
      ))}
    </Stack>
  )
}
