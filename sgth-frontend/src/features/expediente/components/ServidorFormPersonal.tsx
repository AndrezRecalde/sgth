'use client'

import {
  TextInput, Select, Grid, } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useFormContext } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import { ESTADO_CIVIL_OPTIONS, GENERO_OPTIONS, TIPO_SANGRE_OPTIONS } from '../constants/servidor'
import { ServidorFormOrigen } from './ServidorFormOrigen'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import { SectionHeading } from '@/components/ui'

/**
 * Los campos personales de la ficha. Se lee del contexto del formulario para
 * poder servir al alta, a la carga inicial y a la edición, que validan con
 * esquemas distintos sobre los mismos campos.
 */
export function ServidorFormPersonal() {
  const contained = useContainedInput()
  const form = useFormContext<ServidorBasicoFormData>()
  const { register, formState: { errors } } = form

  return (
    <Grid>
      <Grid.Col span={12}>
        <SectionHeading title="Identificación" mb="xs" />
      </Grid.Col>
      {/* La cédula va primero y sola en su fila. Es la llave de la ficha: el
          backend rechaza la que ya exista, y tenerla al final obligaba a
          escribir los cuatro nombres antes de enterarse de que la persona ya
          estaba registrada. Estaba además a `sm: 4` detrás de cuatro campos de
          `sm: 6`, así que quedaba huérfana a media anchura en mitad del bloque.

          Ocupa la fila entera, como el correo y la dirección en el paso de
          contacto: en este formulario, el campo que va solo en su fila la llena.
          Una columna de 4 dejaría que el primer nombre subiera a su derecha y
          partiría las parejas de nombre y apellido, y un tope de anchura la
          volvía a dejar a media fila entre campos que sí la llenan. */}
      <Grid.Col span={12}>
        <TextInput
          label="Cédula de identidad"
          placeholder="0000000000"
          maxLength={10}
          {...contained}
          {...register('cedula')}
          error={errors.cedula?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Primer nombre"
          placeholder="Ej: María"
          {...contained}
          {...register('nombre')}
          error={errors.nombre?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Segundo nombre"
          placeholder="Segundo nombre (opcional)"
          {...contained}
          {...register('segundo_nombre')}
          error={errors.segundo_nombre?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Primer apellido"
          placeholder="Ej: Cortez"
          {...contained}
          {...register('apellido')}
          error={errors.apellido?.message}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <TextInput
          label="Segundo apellido"
          placeholder="Segundo apellido (opcional)"
          {...contained}
          {...register('segundo_apellido')}
          error={errors.segundo_apellido?.message}
        />
      </Grid.Col>
      <Grid.Col span={12}>
        <SectionHeading title="Datos demográficos" mt="xs" mb="xs" />
      </Grid.Col>
      {/* Los cuatro a `sm: 6`. Género y estado civil iban a `sm: 4` y los dos
          de abajo a `sm: 6`: la primera fila quedaba con un hueco de un tercio
          a la derecha y las cajas de las dos filas no coincidían de ancho. */}
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="genero"
          control={form.control}
          render={({ field }) => (
            <Select
              label="Género"
              placeholder="Seleccionar"
              data={GENERO_OPTIONS}
              {...contained}
              value={field.value}
              onChange={field.onChange}
              error={errors.genero?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="estado_civil"
          control={form.control}
          render={({ field }) => (
            <Select
              label="Estado civil"
              placeholder="Seleccionar"
              data={ESTADO_CIVIL_OPTIONS}
              {...contained}
              value={field.value}
              onChange={field.onChange}
              error={errors.estado_civil?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="fecha_nacimiento"
          control={form.control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de nacimiento"
              placeholder="Seleccionar fecha"
              maxDate={new Date()}
              valueFormat="DD/MM/YYYY"
              {...contained}
              value={toDateValue(field.value)}
              onChange={(date) => field.onChange(fromDateValue(date))}
              error={errors.fecha_nacimiento?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="tipo_sangre"
          control={form.control}
          render={({ field }) => (
            <Select
              label="Tipo de sangre"
              placeholder="Seleccionar (opcional)"
              data={TIPO_SANGRE_OPTIONS}
              clearable
              {...contained}
              value={field.value ?? ''}
              onChange={field.onChange}
              error={errors.tipo_sangre?.message}
            />
          )}
        />
      </Grid.Col>

      <ServidorFormOrigen />
    </Grid>
  )
}
