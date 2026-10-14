'use client'

import { Grid, Select, Switch } from '@mantine/core'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { CONDICION_LABELS, ESTADOS_MANUALES } from '../constants/estados'
import type { BienFormData } from '../schemas/bien.schema'
import type { BienInformatico, CatalogosInventario, CondicionFisica } from '../services/tipos'
import { ubicacionesService } from '../services/ubicacionesService'
import { BuscarEquipoSelect } from './BuscarEquipoSelect'

interface Props {
  form: UseFormReturn<BienFormData>
  catalogos: CatalogosInventario
  unidades: { id: number; nombre?: string }[]
  bien: BienInformatico | null
}

const aNumero = (v: string | null) => (v ? Number(v) : null)
const CONDICIONES = (Object.keys(CONDICION_LABELS) as CondicionFisica[]).map((c) => ({ value: c, label: CONDICION_LABELS[c] }))

/**
 * Dónde está y en qué situación: estado, condición, ubicación, unidad, el
 * equipo del que es componente, el contrato de vigencia y si se presta.
 */
export function BienFormUbicacion({ form, catalogos, unidades, bien }: Props) {
  const contained = useContainedInput()
  const { control, getValues, setValue, formState: { errors } } = form

  // Al elegir la unidad, si el equipo todavía no tiene ubicación, se propone
  // la habitual de la unidad. Una ubicación ya puesta no se toca.
  const proponerUbicacion = (unidadId: number | null) => {
    if (!unidadId || getValues('ubicacion_id')) return
    ubicacionesService.sugerida({ unidad_administrativa_id: unidadId })
      .then((u) => { if (u && !getValues('ubicacion_id')) setValue('ubicacion_id', u.id, { shouldDirty: true }) })
      .catch(() => undefined)
  }
  const deBaja = bien?.estado_operativo === 'dado_de_baja'
  const conCustodio = !!bien?.custodio

  return (
    <SectionCard title="Estado y ubicación">
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="condicion_fisica" control={control} render={({ field }) => (
            <Select
              label="Condición física"
              data={CONDICIONES}
              allowDeselect={false}
              error={errors.condicion_fisica?.message}
              {...contained}
              value={field.value}
              onChange={(v) => v && field.onChange(v)}
            />
          )} />
        </Grid.Col>
        {bien && (
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="estado_operativo" control={control} render={({ field }) => (
              <Select
                label="Estado"
                // «En uso» y «Disponible» los pone la custodia: elegir «En
                // servicio» devuelve el equipo al que le toca.
                data={deBaja ? [{ value: 'dado_de_baja', label: 'Dado de baja' }] : ESTADOS_MANUALES}
                disabled={deBaja}
                allowDeselect={false}
                error={errors.estado_operativo?.message}
                {...contained}
                value={field.value === 'en_uso' ? 'disponible' : field.value}
                onChange={(v) => v && field.onChange(v)}
              />
            )} />
          </Grid.Col>
        )}

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="ubicacion_id" control={control} render={({ field }) => (
            <Select
              label="Ubicación"
              placeholder="Sede y piso"
              data={catalogos.ubicaciones.map((u) => ({ value: String(u.id), label: u.piso ? `${u.sede} · ${u.piso}` : u.sede }))}
              searchable
              clearable
              error={errors.ubicacion_id?.message}
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(aNumero(v))}
            />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="unidad_administrativa_id" control={control} render={({ field }) => (
            <Select
              label="Unidad"
              placeholder={conCustodio ? 'La de su custodio' : 'Donde está mientras nadie lo tenga'}
              description={conCustodio ? 'Mientras tenga custodio, cuenta la unidad de esa persona.' : undefined}
              data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? '' }))}
              searchable
              clearable
              error={errors.unidad_administrativa_id?.message}
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => { field.onChange(aNumero(v)); proponerUbicacion(aNumero(v)) }}
            />
          )} />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="bien_padre_id" control={control} render={({ field }) => (
            <BuscarEquipoSelect
              label="Componente de"
              value={field.value}
              textoInicial={bien?.padre ? [bien.padre.codigo_institucional, bien.padre.tipo, bien.padre.modelo].filter(Boolean).join(' · ') : undefined}
              excluirId={bien?.id}
              onChange={field.onChange}
              error={errors.bien_padre_id?.message}
            />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="contrato_vigencia_id" control={control} render={({ field }) => (
            <Select
              label="Contrato de vigencia tecnológica"
              placeholder="Proceso de compra"
              data={catalogos.contratos.map((c) => ({ value: String(c.id), label: c.proceso }))}
              searchable
              clearable
              error={errors.contrato_vigencia_id?.message}
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(aNumero(v))}
            />
          )} />
        </Grid.Col>

        <Grid.Col span={12}>
          <Controller name="prestable" control={control} render={({ field }) => (
            <Switch
              label="Equipo de préstamo"
              description={conCustodio
                ? 'Tiene custodio: para prestarlo, primero debe devolverse a TIC.'
                : 'Lo podrán pedir prestado los servidores desde el Portal.'}
              disabled={conCustodio || deBaja}
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
            />
          )} />
        </Grid.Col>
      </Grid>
    </SectionCard>
  )
}
