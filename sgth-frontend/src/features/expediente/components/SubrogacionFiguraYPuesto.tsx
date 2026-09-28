'use client'

import { Alert, Button, Input, Select, Stack, SegmentedControl } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from './BuscarServidorSelect'
import { TIPO_OPTIONS } from '../utils/subrogaciones'
import type { SubrogacionFormData } from '../schemas/subrogacion.schema'
import type { useFormularioSubrogacion } from '../hooks/useFormularioSubrogacion'

type Datos = ReturnType<typeof useFormularioSubrogacion>

/**
 * Qué figura es, quién la asume y sobre qué puesto.
 *
 * El titular no se elige salvo que el puesto tenga varias plazas ocupadas: sale
 * de quién ocupa el puesto, y pedirlo aparte permitía firmar un documento que
 * afirmaba un reemplazo que no ocurrió.
 */
export function SubrogacionFiguraYPuesto({
  form,
  datos,
}: {
  form: UseFormReturn<SubrogacionFormData>
  datos: Datos
}) {
  const contained = useContainedInput()
  const { control, formState: { errors } } = form

  const unidadOptions = datos.unidades.map((u) => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  const puestoOptions = datos.puestos.map((p) => ({
    value: String(p.id),
    label: p.cargo?.nombre ?? `Puesto ${p.id}`,
  }))

  const titularOptions = datos.ocupantes.map((o) => ({
    value: String(o.id),
    label: o.nombre ?? `Servidor ${o.id}`,
  }))

  return (
    <Stack gap="sm">
      {/* La etiqueta no es decorativa: sin ella, el control de figura era el
          único campo del formulario que no decía qué se está eligiendo.
          Va sin el patrón contained a propósito: ese coloca la etiqueta en
          posición absoluta dentro de la casilla del input, y un
          SegmentedControl no tiene casilla — la etiqueta acababa dibujada por
          debajo de los dos botones, presente en el DOM y sin verse. */}
      <Input.Wrapper label="Figura">
        <Controller
          name="tipo"
          control={control}
          render={({ field }) => (
            <SegmentedControl
              data={TIPO_OPTIONS}
              value={field.value}
              onChange={datos.elegirTipo}
              fullWidth
            />
          )}
        />
      </Input.Wrapper>

      <Controller
        name="servidor_subrogante_id"
        control={control}
        render={({ field }) => (
          <BuscarServidorSelect
            label={datos.tipo === 'encargo' ? 'Servidor encargado' : 'Servidor subrogante'}
            value={field.value}
            onChange={(id) => {
              field.onChange(id ?? undefined)
              if (!id) datos.limpiarSubrogante()
            }}
            onSelect={datos.elegirSubrogante}
            error={errors.servidor_subrogante_id?.message}
          />
        )}
      />

      <Controller
        name="unidad_administrativa_id"
        control={control}
        render={({ field }) => (
          <Select
            label="Unidad administrativa"
            placeholder="Seleccionar unidad"
            data={unidadOptions}
            searchable
            {...contained}
            value={field.value ? String(field.value) : null}
            onChange={(v) => {
              field.onChange(v ? Number(v) : undefined)
              form.resetField('puesto_subrogado_id')
            }}
            error={errors.unidad_administrativa_id?.message}
          />
        )}
      />

      <Controller
        name="puesto_subrogado_id"
        control={control}
        render={({ field }) => (
          <Select
            label="Puesto"
            placeholder={datos.unidadSelId ? 'Seleccionar puesto' : 'Seleccione primero la unidad'}
            data={puestoOptions}
            searchable
            disabled={!datos.unidadSelId}
            {...contained}
            value={field.value ? String(field.value) : null}
            onChange={(v) => datos.elegirPuesto(v ? Number(v) : undefined, field.onChange)}
            error={errors.puesto_subrogado_id?.message}
          />
        )}
      />

      {/* Un puesto con varias plazas tiene varios ocupantes, y entonces sí hay
          que decir a cuál se reemplaza: el formulario se quedaba con el primero
          de la lista sin avisar. */}
      {datos.hayQueElegirTitular && (
        <Controller
          name="servidor_subrogado_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Titular a subrogar"
              description={`${datos.ocupantes.length} personas ocupan este puesto`}
              placeholder="Seleccionar titular"
              data={titularOptions}
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : null)}
              error={errors.servidor_subrogado_id?.message}
            />
          )}
        />
      )}

      {datos.figuraEquivocada && (
        <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
          {datos.puestoVacante ? (
            <>
              <strong>{datos.nombrePuesto}</strong> está vacante: no hay titular a
              quien subrogar. La figura que corresponde es el encargo.
            </>
          ) : (
            <>
              <strong>{datos.nombrePuesto}</strong> lo ocupa {datos.ocupantes[0]?.nombre}:
              la figura que corresponde es la subrogación.
            </>
          )}
          <Button
            size="xs"
            variant="light"
            mt="xs"
            onClick={() => datos.elegirTipo(datos.puestoVacante ? 'encargo' : 'subrogacion')}
          >
            Cambiar a {datos.puestoVacante ? 'encargo' : 'subrogación'}
          </Button>
        </Alert>
      )}
    </Stack>
  )
}
