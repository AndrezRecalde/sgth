'use client'

import { Grid, Select, TextInput } from '@mantine/core'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCompanerosDeUnidad } from '../hooks/useCompanerosDeUnidad'
import { SaldoVacacionesAlert } from './SaldoVacacionesAlert'
import { MOTIVO_OPCIONES } from './vacaciones.constants'
import type { VacacionFormData } from './vacacion.schema'
import type { CompaneroDeUnidad } from '@/types/api'

interface Props {
  form:         UseFormReturn<VacacionFormData>
  servidorId:   number
  nombrePropio: string
}

const opcion = (c: CompaneroDeUnidad) => ({
  value: String(c.id),
  label: c.cargo ? `${c.nombre} — ${c.cargo}` : c.nombre,
})

/**
 * Quien pide sus propias vacaciones desde el portal: el solicitante es él
 * mismo, y el jefe inmediato y la persona que lo reemplaza salen de su unidad.
 *
 * La lista es la del autoservicio, como en «Mis permisos»: el listado de
 * expedientes que usa `VacacionSolicitanteCampos` está cerrado a Talento
 * Humano y aquí respondería 403.
 */
export function VacacionSolicitantePropio({ form, servidorId, nombrePropio }: Props) {
  const contained = useContainedInput()
  const { control, formState: { errors } } = form
  const motivo = useWatch({ control, name: 'motivo' })
  const { data: companeros = [], isLoading } = useCompanerosDeUnidad()

  const jefes = companeros.filter(c => c.es_jefe).map(opcion)
  const resto = companeros.filter(c => !c.es_jefe).map(opcion)
  const opcionesJefe = [
    ...(jefes.length ? [{ group: 'Jefes de la unidad', items: jefes }] : []),
    ...(resto.length ? [{ group: 'Otros servidores de la unidad', items: resto }] : []),
  ]
  // Nadie se reemplaza a sí mismo: el backend lo rechaza.
  const opcionesReemplazo = companeros.filter(c => c.id !== servidorId).map(opcion)

  const placeholderUnidad = (texto: string) =>
    isLoading ? 'Cargando…' : companeros.length ? texto : 'Sin más servidores en su unidad'

  return (
    <>
      <Grid>
        {/* El solicitante es quien tiene la sesión: no se elige. */}
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput label="Servidor" value={nombrePropio} readOnly {...contained} />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="jefe_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Jefe inmediato"
                placeholder={placeholderUnidad('Seleccionar jefe')}
                data={opcionesJefe}
                searchable
                clearable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
                error={errors.jefe_id?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="persona_reemplaza_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Persona que lo reemplaza"
                placeholder={placeholderUnidad('Seleccionar (opcional)')}
                data={opcionesReemplazo}
                searchable
                clearable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
                error={errors.persona_reemplaza_id?.message}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="motivo"
            control={control}
            render={({ field }) => (
              <Select
                label="Motivo"
                placeholder="Seleccionar motivo"
                data={MOTIVO_OPCIONES}
                {...contained}
                value={field.value || null}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.motivo?.message}
              />
            )}
          />
        </Grid.Col>
      </Grid>

      <SaldoVacacionesAlert servidorId={servidorId} motivo={motivo} />
    </>
  )
}
