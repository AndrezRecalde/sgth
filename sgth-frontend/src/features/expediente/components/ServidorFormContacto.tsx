'use client'

import { TextInput, Grid, Divider } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { UseFormReturn } from 'react-hook-form'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'

interface Props {
  form: UseFormReturn<ServidorBasicoFormData>
}

export function ServidorFormContacto({ form }: Props) {
  const contained = useContainedInput()
  const { register, formState: { errors } } = form

  return (
    <Grid>
      <Grid.Col span={12}>
        <Divider label="Contacto" labelPosition="left" mb="xs" />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Teléfono celular"
          placeholder="0999999999"
          {...contained}
          {...register('telefono_celular')}
          error={errors.telefono_celular?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Teléfono convencional"
          placeholder="072000000"
          {...contained}
          {...register('telefono_convencional')}
          error={errors.telefono_convencional?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Correo personal"
          placeholder="usuario@gmail.com"
          {...contained}
          {...register('correo_personal')}
          error={errors.correo_personal?.message}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Código médico"
          placeholder="Registro ACESS — solo personal de salud"
          description="Se imprime en la sección O de las fichas FEMO que firme"
          {...contained}
          {...register('codigo_medico')}
          error={errors.codigo_medico?.message}
        />
      </Grid.Col>

      <Grid.Col span={12}>
        <TextInput
          label="Dirección domiciliaria"
          placeholder="Barrio, calle principal y número"
          {...contained}
          {...register('direccion_domicilio')}
          error={errors.direccion_domicilio?.message}
        />
      </Grid.Col>

      <Grid.Col span={12}>
        <Divider label="Documentos adicionales" labelPosition="left" mb="xs" mt="xs" />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Número papeleta de votación"
          placeholder="Opcional"
          {...contained}
          {...register('numero_papeleta_votacion')}
          error={errors.numero_papeleta_votacion?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Número de pasaporte"
          placeholder="Opcional"
          {...contained}
          {...register('pasaporte_numero')}
          error={errors.pasaporte_numero?.message}
        />
      </Grid.Col>
    </Grid>
  )
}
