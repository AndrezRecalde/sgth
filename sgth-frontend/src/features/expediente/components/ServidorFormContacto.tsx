'use client'

import { TextInput, Grid, Text } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useFormContext } from 'react-hook-form'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import { SectionHeading } from '@/components/ui'

/**
 * Contacto y documentos adicionales. Lee del contexto, como el de datos
 * personales. `sinTitulo` cuando ya va dentro de una tarjeta titulada
 * «Contacto»: el modal de edición lo mostraba dos veces, los dos como h3.
 */
export function ServidorFormContacto({ sinTitulo = false }: { sinTitulo?: boolean }) {
  const contained = useContainedInput()
  const { register, formState: { errors } } = useFormContext<ServidorBasicoFormData>()

  return (
    <Grid>
      {!sinTitulo && (
        <Grid.Col span={12}>
          <SectionHeading title="Contacto" mb="xs" />
        </Grid.Col>
      )}
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
      {/* A lo ancho, como la dirección: un correo no cabe en media fila, y
          emparejarlo dejaba un hueco a su derecha. */}
      <Grid.Col span={12}>
        <TextInput
          label="Correo personal"
          placeholder="usuario@gmail.com"
          {...contained}
          {...register('correo_personal')}
          error={errors.correo_personal?.message}
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
        <SectionHeading title="Documentos adicionales" mt="xs" mb="xs" />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Número papeleta de votación (opcional)"
          placeholder="Ej: 007-0001"
          {...contained}
          {...register('numero_papeleta_votacion')}
          error={errors.numero_papeleta_votacion?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Número de pasaporte (opcional)"
          placeholder="Ej: A1234567"
          {...contained}
          {...register('pasaporte_numero')}
          error={errors.pasaporte_numero?.message}
        />
      </Grid.Col>

      {/* En su propio bloque, y no suelto entre los teléfonos, por dos motivos.
          Uno: no es dato de contacto ni un documento de la persona, es la
          credencial con la que un profesional firma. Dos: es el único campo con
          `description`, y en el patrón contained la descripción va ENCIMA del
          input —lo dice el comentario de inputs.contained.module.css—, así que
          empujaba su caja hacia abajo y dejaba de cuadrar con el campo de al
          lado. Solo en su fila, no tiene con quién descuadrar.

          El título dice para quién es: quien registra a un chofer lee «Solo
          para personal de salud» y se salta el bloque entero. */}
      <Grid.Col span={12}>
        <SectionHeading title="Solo para personal de salud" mt="xs" mb={4} />
      </Grid.Col>
      {/* La explicación, a lo ancho y fuera del campo. Como `description` del
          input se quedaba encogida en media columna y se leía a tres líneas, y
          además era lo que descuadraba la fila. */}
      <Grid.Col span={12}>
        <Text size="xs" c="dimmed" mb={4}>
          Número del registro ACESS de médicos, odontólogos y enfermeras. Se
          imprime en las fichas médicas ocupacionales que la persona firme.
        </Text>
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Código médico (opcional)"
          placeholder="Ej: 0123456789"
          {...contained}
          {...register('codigo_medico')}
          error={errors.codigo_medico?.message}
        />
      </Grid.Col>
    </Grid>
  )
}
