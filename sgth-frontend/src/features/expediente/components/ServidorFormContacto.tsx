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
          label="Teléfono celular"
          placeholder="0999999999"
          {...contained}
          {...form.getInputProps('telefono_celular')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Teléfono convencional"
          placeholder="072000000"
          {...contained}
          {...form.getInputProps('telefono_convencional')}
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
          {...form.getInputProps('direccion_domicilio')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Número papeleta de votación"
          placeholder="Opcional"
          {...contained}
          {...form.getInputProps('numero_papeleta_votacion')}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Número de pasaporte"
          placeholder="Opcional"
          {...contained}
          {...form.getInputProps('pasaporte_numero')}
        />
      </Grid.Col>
    </Grid>
  )
}
