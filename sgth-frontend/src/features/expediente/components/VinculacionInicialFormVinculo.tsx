'use client'

import { Alert, Grid, NumberInput, Select, Switch, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useFormContext, useWatch } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { usePuestos } from '@/features/estructura/hooks/usePuestos'
import { TIPO_NOMBRAMIENTO_OPTIONS } from '../utils/tipoNombramientoOptions'
import { admiteMarcacion, esLosep, remuneracionEsHeredada } from '../utils/nombramiento'
import type { VinculacionInicialFormData } from '../schemas/vinculacionInicial.schema'
import type { PuestoConRelaciones, UnidadConRelaciones } from '@/types/api'

const CON_PLAZO = ['servicios_ocasionales', 'servicios_profesionales']

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('T')[0].split('-').map(Number)
  return new Date(y, m - 1, d)
}

const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

/**
 * Contrato vigente del servidor que se está migrando. Es el paso que distingue
 * esta vía del alta ordinaria: aquí el vínculo nace junto con la ficha, sin
 * Acción de Personal, porque el acto ocurrió en papel antes del sistema.
 */
export function VinculacionInicialFormVinculo() {
  const contained = useContainedInput()
  const { control, setValue, formState: { errors } } = useFormContext<VinculacionInicialFormData>()

  const nombramiento = useWatch({ control, name: 'vinculo.tipo_nombramiento' })
  const unidadId     = useWatch({ control, name: 'vinculo.unidad_administrativa_id' })
  const puestoId     = useWatch({ control, name: 'vinculo.puesto_id' })

  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]

  const { data: puestosData } = usePuestos(
    unidadId ? { unidad_administrativa_id: Number(unidadId), per_page: 100 } : undefined,
  )
  const puestos = (puestosData?.data ?? []) as PuestoConRelaciones[]

  const puestoSel = puestos.find((p) => p.id === Number(puestoId))
  const rmuPuesto = puestoSel?.rmu ? Number(puestoSel.rmu) : null
  const rmuHeredada = remuneracionEsHeredada(nombramiento, rmuPuesto)
  const llevaPlazo = CON_PLAZO.includes(nombramiento ?? '')

  const errVinculo = errors.vinculo

  return (
    <Grid>
      <Grid.Col span={12}>
        <Alert variant="light" color="orange" icon={<IconInfoCircle size={16} />}>
          Este vínculo se registrará <strong>sin Acción de Personal</strong>, porque
          el acto administrativo ya ocurrió en papel. Quedará marcado como carga
          inicial. Todo lo que pase después —traspasos, comisiones, cesaciones—
          seguirá el flujo normal.
        </Alert>
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="vinculo.tipo_nombramiento"
          control={control}
          render={({ field }) => (
            <Select
              label="Modalidad de vinculación"
              placeholder="Seleccionar"
              data={TIPO_NOMBRAMIENTO_OPTIONS}
              searchable
              value={field.value ?? null}
              onChange={(v) => field.onChange(v)}
              error={errVinculo?.tipo_nombramiento?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="vinculo.unidad_administrativa_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Unidad administrativa"
              placeholder="Seleccionar"
              data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))}
              searchable
              value={field.value ? String(field.value) : null}
              onChange={(v) => {
                field.onChange(v ? Number(v) : null)
                setValue('vinculo.puesto_id', undefined as unknown as number)
              }}
              error={errVinculo?.unidad_administrativa_id?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="vinculo.puesto_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Puesto"
              placeholder={unidadId ? 'Seleccionar' : 'Elija primero la unidad'}
              data={puestos.map((p) => ({ value: String(p.id), label: p.cargo?.nombre ?? `Puesto ${p.id}` }))}
              searchable
              disabled={!unidadId}
              value={field.value ? String(field.value) : null}
              onChange={(v) => {
                field.onChange(v ? Number(v) : null)
                const sel = puestos.find((p) => String(p.id) === v)
                if (sel?.rmu) setValue('vinculo.remuneracion', Number(sel.rmu))
              }}
              error={errVinculo?.puesto_id?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="vinculo.remuneracion"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Remuneración vigente (R.M.U.)"
              description={rmuHeredada
                ? 'Fijada por el grupo ocupacional del puesto. No se edita en régimen LOSEP.'
                : esLosep(nombramiento)
                  ? 'Este puesto no tiene grupo ocupacional: ingrese el monto del rol de pagos.'
                  : 'La del rol de pagos vigente. En este régimen se pacta en el contrato.'}
              placeholder="0.00"
              min={0}
              decimalScale={2}
              readOnly={rmuHeredada}
              value={field.value ?? ''}
              onChange={(v) => {
                const n = typeof v === 'number' ? v : parseFloat(String(v))
                field.onChange(Number.isFinite(n) ? n : undefined)
              }}
              error={errVinculo?.remuneracion?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="vinculo.fecha_inicio"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Inicio del contrato vigente"
              description="La del contrato o nombramiento actual, no la del primer ingreso."
              valueFormat="DD/MM/YYYY"
              maxDate={new Date()}
              value={toDate(field.value)}
              onChange={(d) => field.onChange(fromDate(d as Date | null) ?? '')}
              error={errVinculo?.fecha_inicio?.message}
              {...contained}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        {llevaPlazo ? (
          <Controller
            name="vinculo.fecha_fin"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Término del contrato"
                description="Servicios Profesionales toma el 31 de diciembre de su año si se deja vacío."
                valueFormat="DD/MM/YYYY"
                clearable
                value={toDate(field.value)}
                onChange={(d) => field.onChange(fromDate(d as Date | null))}
                error={errVinculo?.fecha_fin?.message}
                {...contained}
              />
            )}
          />
        ) : (
          <TextInput
            label="Término del contrato"
            description="Esta modalidad no lleva plazo."
            value="Sin plazo"
            readOnly
            {...contained}
          />
        )}
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="vinculo.numero_contrato"
          control={control}
          render={({ field }) => (
            <TextInput
              label="N.º de contrato o acción"
              placeholder="Opcional — si consta en el expediente físico"
              value={field.value ?? ''}
              onChange={(e) => field.onChange(e.currentTarget.value)}
              {...contained}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="vinculo.resolucion_numero"
          control={control}
          render={({ field }) => (
            <TextInput
              label="N.º de resolución"
              placeholder="Opcional"
              value={field.value ?? ''}
              onChange={(e) => field.onChange(e.currentTarget.value)}
              {...contained}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={12}>
        <Controller
          name="vinculo.puede_marcar"
          control={control}
          render={({ field }) => {
            // Servicios profesionales, libre nombramiento y elección popular no
            // marcan nunca; el backend lo fuerza igual.
            const admite = admiteMarcacion(nombramiento)

            return (
              <Switch
                label="Marcación biométrica"
                description={admite
                  ? 'Define si la persona entra al control de asistencia.'
                  : 'Esta modalidad no marca biométrico.'}
                checked={admite && !!field.value}
                disabled={!admite}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )
          }}
        />
      </Grid.Col>
    </Grid>
  )
}
