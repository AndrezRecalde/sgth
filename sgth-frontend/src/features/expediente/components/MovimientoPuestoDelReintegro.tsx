'use client'

import { Grid, Select, Text } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestosDeUnidad } from '@/features/estructura/hooks/usePuestosDeUnidad'
import type { MovimientoFormData } from '../schemas/movimiento.schema'

/**
 * El obrero que vuelve por despido ineficaz vuelve a su puesto (fase 4.5b).
 * Solo si ese ya no tiene plaza, Talento Humano elige otro del catálogo de
 * obreros que la tenga: la orden del juez no espera a que se libere una. Las
 * plazas las cuenta el backend.
 */
export function MovimientoPuestoDelReintegro({ form }: { form: UseFormReturn<MovimientoFormData> }) {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = form

  const unidadId = useWatch({ control, name: 'unidad_destino_id' })
  const puestoId = useWatch({ control, name: 'puesto_destino_id' })

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const {
    puestos, descripcionRecorte, searchValue, onSearchChange,
  } = usePuestosDeUnidad(unidadId, { seleccionadoId: puestoId })

  const puestosDeObreros = puestos.filter((p) => p.regimen_laboral === 'codigo_trabajo')

  return (
    <div>
      <SectionHeading title="Puesto al que vuelve" mb={4} />
      <Text size="xs" c="dimmed" mb="xs">
        Déjelo vacío: vuelve al suyo, con su contrato y su remuneración. Solo si
        su puesto ya no tiene plaza, elija otro del catálogo de obreros que la tenga.
      </Text>
      <Grid gap="sm">
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="unidad_destino_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Unidad administrativa"
                placeholder="La suya"
                data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))}
                searchable
                clearable
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  field.onChange(v ? Number(v) : null)
                  setValue('puesto_destino_id', null)
                }}
                error={errors.unidad_destino_id?.message}
                {...contained}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="puesto_destino_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Puesto"
                // Que es del catálogo de obreros ya lo dice el texto de arriba:
                // repetirlo aquí descuadraba las dos columnas.
                description={descripcionRecorte}
                placeholder={unidadId ? 'El suyo' : 'Elija primero la unidad'}
                data={puestosDeObreros.map((p) => ({
                  value: String(p.id), label: p.cargo?.nombre ?? `Puesto ${p.id}`,
                }))}
                searchable
                clearable
                searchValue={searchValue}
                onSearchChange={onSearchChange}
                disabled={!unidadId}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
                error={errors.puesto_destino_id?.message}
                {...contained}
              />
            )}
          />
        </Grid.Col>
      </Grid>
    </div>
  )
}
