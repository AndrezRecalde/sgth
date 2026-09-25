'use client'

import { useState } from 'react'
import {
  Alert, Button, Checkbox, Group, NumberInput, Select, Stack, Text, Textarea,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { ModalFooter, SectionHeading } from '@/components/ui'
import { useFieldArray, useForm, useWatch, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconAlertTriangle, IconInfoCircle, IconPlus } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useEppEntregaMutations, useKitEppServidor } from '../hooks/useEppEntregas'
import { useEquiposProteccion } from '../hooks/useEquiposProteccion'
import {
  entregaKitEppSchema, type EntregaKitEppFormData,
} from '../schemas/entregaKitEpp.schema'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import type { PuestoEpp } from '../services/tipos'

const KIT_EPP_VACIO: PuestoEpp[] = []

const VALORES_INICIALES: EntregaKitEppFormData = {
  servidor_id: 0,
  fecha_entrega: '',
  observaciones: '',
  equipos: [],
}

interface Props {
  onListo: () => void
  onCancelar: () => void
}

/**
 * Entregar de una vez todo el EPP que el puesto del servidor requiere.
 *
 * Estaba escrito a mano con cinco `useState` y un objeto indexado por equipo,
 * fuera del estándar de la regla 07. No validaba nada: se podía mandar una
 * fecha futura, que el backend rechaza, y el 422 salía como notificación sin
 * señalar el campo. Ahora es React Hook Form con Zod, como los demás
 * formularios del sistema, y los errores del servidor caen en su campo.
 */
export function EntregaKitEppForm({ onListo, onCancelar }: Props) {
  const contained = useContainedInput()
  const { registrarKit } = useEppEntregaMutations()

  const { data: equiposData, error: errorEquipos } = useEquiposProteccion({ estado: true })
  const equipoOptions = (equiposData?.data ?? []).map(e => ({
    value: String(e.id), label: `${e.codigo} — ${e.nombre}`,
  }))

  const {
    control, handleSubmit, register, setError,
    formState: { errors },
  } = useForm<EntregaKitEppFormData>({
    resolver: zodResolver(entregaKitEppSchema) as Resolver<EntregaKitEppFormData>,
    defaultValues: VALORES_INICIALES,
  })

  const { fields, replace, append } = useFieldArray({ control, name: 'equipos' })
  // `useWatch` y no `watch()`: el segundo devuelve una función que el
  // compilador de React no puede memoizar, y ESLint lo rechaza.
  const servidorId = useWatch({ control, name: 'servidor_id' })
  const equipos = useWatch({ control, name: 'equipos' })

  const [equipoExtra, setEquipoExtra] = useState<string | null>(null)

  const {
    data: kitEquipos = KIT_EPP_VACIO, isLoading: kitLoading, error: errorKit,
  } = useKitEppServidor(servidorId || null)

  // Cuando llega el kit de otro servidor se rehace la lista de casillas. Se
  // ajusta durante el render y no en un efecto, siguiendo el patrón de React
  // para derivar estado de un cambio de datos:
  // https://react.dev/learn/you-might-not-need-an-effect
  const [kitSincronizado, setKitSincronizado] = useState(kitEquipos)
  if (kitEquipos !== kitSincronizado) {
    setKitSincronizado(kitEquipos)
    replace(kitEquipos.map(item => ({
      equipo_proteccion_id: item.equipo_proteccion_id,
      nombre: item.equipo_proteccion?.nombre ?? `Equipo ${item.equipo_proteccion_id}`,
      cantidad: item.cantidad_requerida,
      incluido: true,
    })))
  }

  const agregarEquipoExtra = () => {
    if (!equipoExtra) return
    const id = Number(equipoExtra)
    if (equipos.some(e => e.equipo_proteccion_id === id)) return
    append({
      equipo_proteccion_id: id,
      nombre: equipoOptions.find(o => o.value === equipoExtra)?.label ?? `Equipo ${id}`,
      cantidad: 1,
      incluido: true,
    })
    setEquipoExtra(null)
  }

  const equiposDisponibles = equipoOptions.filter(
    o => !equipos.some(e => e.equipo_proteccion_id === Number(o.value)),
  )

  const marcados = equipos.filter(e => e.incluido)

  const guardar = (valores: EntregaKitEppFormData) => {
    registrarKit.mutateAsync({
      servidor_id: valores.servidor_id,
      fecha_entrega: valores.fecha_entrega,
      observaciones: valores.observaciones || undefined,
      equipos: valores.equipos
        .filter(e => e.incluido)
        .map(e => ({ equipo_proteccion_id: e.equipo_proteccion_id, cantidad: e.cantidad })),
    })
      .then(onListo)
      .catch((error) => {
        // Un 422 del backend al campo que lo provoca. Los de la lista llegan
        // como `equipos.0.cantidad`: se traducen al índice del formulario.
        const campos = erroresDeCampo(error)
        if (!campos) return // el hook ya lo notificó
        for (const [campo, mensaje] of Object.entries(campos)) {
          const fila = campo.match(/^equipos\.(\d+)\.(\w+)$/)
          if (fila) {
            setError(`equipos.${Number(fila[1])}.${fila[2]}` as 'equipos.0.cantidad', { message: mensaje })
            continue
          }
          setError(campo as keyof EntregaKitEppFormData, { message: mensaje })
        }
      })
  }

  return (
    <form onSubmit={handleSubmit(guardar)} noValidate>
      <Stack gap="sm">
        <Controller
          name="servidor_id"
          control={control}
          render={({ field }) => (
            <BuscarServidorSelect
              label="Servidor"
              required
              value={field.value || null}
              onChange={(id) => field.onChange(id ?? 0)}
              error={errors.servidor_id?.message}
            />
          )}
        />

        {!!servidorId && kitLoading && (
          <Text size="sm" c="dimmed">Cargando kit del puesto…</Text>
        )}

        {!!servidorId && !kitLoading && errorKit && (
          <Alert icon={<IconAlertTriangle size={16} />} color="red" variant="light" title="No se pudo cargar el kit del puesto">
            No quiere decir que el puesto no tenga EPP definido: no se pudo consultar.
            Vuelva a elegir el servidor o agregue los equipos manualmente abajo.
          </Alert>
        )}

        {!!servidorId && !kitLoading && !errorKit && kitEquipos.length === 0 && (
          <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
            Este puesto no tiene equipos de protección definidos en su catálogo.
            Puede agregar equipos manualmente abajo.
          </Alert>
        )}

        {!!servidorId && fields.length > 0 && (
          <Stack gap={6}>
            <Text size="sm" fw={600}>Equipos a entregar</Text>
            {fields.map((campo, indice) => (
              <Group key={campo.id} wrap="nowrap" align="center">
                <Controller
                  name={`equipos.${indice}.incluido`}
                  control={control}
                  render={({ field }) => (
                    <Checkbox
                      checked={field.value}
                      onChange={(e) => field.onChange(e.currentTarget.checked)}
                      label={equipos[indice]?.nombre ?? campo.nombre}
                      style={{ flex: 1 }}
                    />
                  )}
                />
                <Controller
                  name={`equipos.${indice}.cantidad`}
                  control={control}
                  render={({ field }) => (
                    <NumberInput
                      aria-label={`Cantidad de ${equipos[indice]?.nombre ?? campo.nombre}`}
                      min={1}
                      w={80}
                      disabled={!equipos[indice]?.incluido}
                      value={field.value}
                      onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
                      error={errors.equipos?.[indice]?.cantidad?.message}
                    />
                  )}
                />
              </Group>
            ))}
          </Stack>
        )}

        {/* El mensaje de «marque al menos uno» cuelga del array, no de una fila. */}
        {errors.equipos?.root?.message && (
          <Text size="sm" c="red">{errors.equipos.root.message}</Text>
        )}
        {typeof errors.equipos?.message === 'string' && (
          <Text size="sm" c="red">{errors.equipos.message}</Text>
        )}

        {!!servidorId && (
          <>
            <SectionHeading title="Agregar un equipo que no está en el kit" />
            {/* El equipo, en su propia fila: su etiqueta es el código más el
                nombre, y este modal es estrecho. */}
            <Select
              label="Equipo"
              placeholder="Seleccione un equipo"
              data={equiposDisponibles}
              searchable
              {...contained}
              value={equipoExtra}
              onChange={setEquipoExtra}
              error={errorEquipos ? 'No se pudo cargar el catálogo de equipos de protección.' : undefined}
            />
            <Group justify="flex-end">
              <Button
                variant="light"
                leftSection={<IconPlus size={16} />}
                onClick={agregarEquipoExtra}
                disabled={!equipoExtra}
              >
                Agregar
              </Button>
            </Group>
          </>
        )}

        <Controller
          name="fecha_entrega"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de entrega"
              placeholder="Seleccionar"
              valueFormat="DD/MM/YYYY"
              required
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValue(d ?? null))}
              error={errors.fecha_entrega?.message}
            />
          )}
        />

        <Textarea
          label="Observaciones"
          placeholder="Observaciones (opcional, aplica a todo el kit)"
          rows={2}
          {...contained}
          {...register('observaciones')}
          error={errors.observaciones?.message}
        />

        <ModalFooter
          onCancel={onCancelar}
          submitLabel={`Entregar kit (${marcados.length})`}
          submitting={registrarKit.isPending}
        />
      </Stack>
    </form>
  )
}
