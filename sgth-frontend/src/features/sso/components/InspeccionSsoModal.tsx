'use client'

import { useEffect } from 'react'
import { Autocomplete, Group, Select, Stack, Switch, Textarea, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useInspeccionMutations } from '../hooks/useInspecciones'
import {
  inspeccionSchema, type InspeccionFormData, TIPO_INSPECCION_SUGERENCIAS,
} from '../schemas/inspeccion.schema'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import type { InspeccionSso } from '../services/tipos'

interface Props {
  opened: boolean
  onClose: () => void
  inspeccion?: InspeccionSso | null
}

export function InspeccionSsoModal({ opened, onClose, inspeccion }: Props) {
  const contained = useContainedInput()
  const { usuario } = useAuth()
  const { crear, editar } = useInspeccionMutations()
  const editando = !!inspeccion

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  const valoresDe = (registro?: InspeccionSso | null): InspeccionFormData => ({
    unidad_administrativa_id: registro?.unidad_administrativa_id ?? 0,
    fecha_inspeccion: registro?.fecha_inspeccion ?? '',
    tipo_inspeccion: registro?.tipo_inspeccion ?? '',
    hallazgos: registro?.hallazgos ?? '',
    recomendaciones: registro?.recomendaciones ?? '',
    // El inspector es quien registra: el único endpoint que lista usuarios
    // pide `gestionar-usuarios`, que el técnico de SSO no tiene, así que un
    // selector le devolvería un 403. Ver el PR.
    inspector_id: registro?.inspector_id ?? usuario?.id ?? 0,
    estado: registro?.estado ?? true,
  })

  const {
    register, control, handleSubmit, reset, setError,
    formState: { errors },
  } = useForm<InspeccionFormData>({
    resolver: zodResolver(inspeccionSchema) as Resolver<InspeccionFormData>,
    defaultValues: valoresDe(inspeccion),
  })

  useEffect(() => {
    reset(valoresDe(inspeccion))
    // `valoresDe` se recrea en cada render; lo que decide los valores es la
    // inspección que llega y el usuario de la sesión.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [inspeccion, usuario?.id, reset])

  const cerrar = () => {
    reset()
    onClose()
  }

  const guardar = (valores: InspeccionFormData) => {
    const mutacion = editando
      ? editar.mutateAsync({ id: inspeccion!.id, data: valores })
      : crear.mutateAsync(valores)

    mutacion.then(cerrar).catch((error) => {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó
      for (const [campo, mensaje] of Object.entries(campos)) {
        setError(campo as keyof InspeccionFormData, { message: mensaje })
      }
    })
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title={editando ? 'Editar inspección' : 'Nueva inspección de seguridad'}
      size="lg"
      onSubmit={handleSubmit(guardar)}
      submitLabel={editando ? 'Actualizar' : 'Registrar inspección'}
      submitting={crear.isPending || editar.isPending}
    >
      <Stack gap="sm">
        <Controller
          name="unidad_administrativa_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Unidad inspeccionada"
              placeholder="Seleccione la unidad"
              data={unidadOptions}
              searchable
              required
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : 0)}
              error={errors.unidad_administrativa_id?.message}
            />
          )}
        />

        <Group grow>
          <Controller
            name="fecha_inspeccion"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de la inspección"
                placeholder="Seleccionar"
                valueFormat="DD/MM/YYYY"
                required
                // El backend rechaza una fecha futura: no se inspecciona lo
                // que todavía no ha pasado.
                maxDate={new Date()}
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                error={errors.fecha_inspeccion?.message}
              />
            )}
          />
          <Controller
            name="tipo_inspeccion"
            control={control}
            render={({ field }) => (
              <Autocomplete
                label="Tipo de inspección"
                placeholder="Ej: Inspección general de seguridad"
                // Sugerencias y no lista cerrada: el backend acepta texto
                // libre. Están para que el mismo concepto no acabe escrito de
                // cuatro maneras y el índice se pueda agrupar.
                data={TIPO_INSPECCION_SUGERENCIAS}
                required
                {...contained}
                value={field.value}
                onChange={field.onChange}
                error={errors.tipo_inspeccion?.message}
              />
            )}
          />
        </Group>

        <Textarea
          label="Hallazgos"
          placeholder="Qué se encontró durante la inspección"
          autosize
          minRows={3}
          maxRows={8}
          {...contained}
          {...register('hallazgos')}
          error={errors.hallazgos?.message}
        />

        <Textarea
          label="Recomendaciones"
          placeholder="Qué se recomienda corregir, y en qué plazo"
          autosize
          minRows={3}
          maxRows={8}
          {...contained}
          {...register('recomendaciones')}
          error={errors.recomendaciones?.message}
        />

        <Controller
          name="estado"
          control={control}
          render={({ field }) => (
            // `estado` no cambia ningún cálculo: `calcularIndicadoresProactivos`
            // cuenta todas las inspecciones del período sin mirarlo. Sirve para
            // saber cuáles siguen pendientes de atender, y para filtrar.
            <Switch
              label="Seguimiento abierto"
              description="Ciérrelo cuando las recomendaciones estén atendidas. El índice del período la cuenta en los dos casos."
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
            />
          )}
        />

        <Text size="xs" c="dimmed">
          Queda registrada a nombre de {usuario?.nombre_completo ?? 'su usuario'} como inspector.
        </Text>
      </Stack>
    </FormModal>
  )
}
