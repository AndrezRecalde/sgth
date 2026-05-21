'use client'

import { TextInput, Grid } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { UseFormReturnType } from '@mantine/form'
import type { ServidorFormData } from '../schemas/servidor.schema'

interface Props {
  form: UseFormReturnType<ServidorFormData>
}

export function ServidorFormContacto({ form }: Props) {
  const contained = useContainedInput()

  return (
    <Grid>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Teléfono personal"
          placeholder="0999999999"
          {...contained}
          {...form.getInputProps('telefono_personal')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Teléfono institucional"
          placeholder="072000000"
          {...contained}
          {...form.getInputProps('telefono_institucional')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Correo personal"
          placeholder="usuario@gmail.com"
          {...contained}
          {...form.getInputProps('correo_personal')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Correo institucional"
          placeholder="usuario@gad-esmeraldas.gob.ec"
          {...contained}
          {...form.getInputProps('correo_institucional')}
        />
      </Grid.Col>
      <Grid.Col span={12}>
        <TextInput
          label="Dirección domiciliaria"
          placeholder="Barrio, calle principal y número"
          {...contained}
          {...form.getInputProps('direccion')}
        />
      </Grid.Col>
    </Grid>
  )
}
