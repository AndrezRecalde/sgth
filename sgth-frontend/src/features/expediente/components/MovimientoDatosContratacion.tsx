'use client'

import { Alert, Select, Switch, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAusenciasTemporales } from '../hooks/useAusenciasTemporales'
import { admiteMarcacion } from '../utils/nombramiento'
import { TIPO_NOMBRAMIENTO_OPTIONS } from '../utils/tipoNombramientoOptions'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import { formatFecha } from '@/lib/fecha'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'

/**
 * Un reemplazo es transitorio por definición: dura lo que dura la ausencia del
 * titular. La misma regla la impone el backend en validarReemplazo().
 */
const NOMBRAMIENTOS_DE_REEMPLAZO = ['servicios_ocasionales', 'servicios_profesionales']

/** Como formatFecha, pero un plazo sin fin se dice con palabras. */
const fechaCorta = (f?: string | null): string => (f ? formatFecha(f) : 'sin fin')

/**
 * Los datos del instrumento que nace con un Ingreso y Vinculación: nombramiento,
 * enlace de reemplazo, número de contrato, plazo y marcación.
 *
 * Solo el ingreso da origen a un contrato. En un traspaso o una comisión estos
 * campos ni se muestran ni se envían —`soloLoQueAplica()` los anula—: no hay
 * instrumento nuevo que numerar.
 */
export function MovimientoDatosContratacion({
  form,
}: {
  form: UseFormReturn<MovimientoFormData>
}) {
  const contained = useContainedInput()
  const { control, register, setValue, formState: { errors } } = form

  const nombramiento = useWatch({ control, name: 'tipo_nombramiento_propuesto' })
  const cubreId = useWatch({ control, name: 'cubre_movimiento_id' })

  const puedeCubrir = NOMBRAMIENTOS_DE_REEMPLAZO.includes(nombramiento ?? '')

  // Solo las que hoy siguen sin cubrir: ofrecer una ya cubierta serviría solo
  // para que el backend la rechace.
  const { data: ausencias = [] } = useAusenciasTemporales({ cubiertas: false })

  const ausenciaOptions = ausencias.map((a) => ({
    value: String(a.id),
    label: `${a.servidor.nombre} — ${a.etiqueta ?? 'Ausencia'} (hasta ${fechaCorta(a.hasta)})`,
  }))

  const ausenciaSel = ausencias.find((a) => a.id === Number(cubreId))
  const marca = admiteMarcacion(nombramiento)

  return (
    <>
      <Controller
        name="tipo_nombramiento_propuesto"
        control={control}
        render={({ field }) => (
          <Select
            label="Tipo de nombramiento"
            data={TIPO_NOMBRAMIENTO_OPTIONS}
            searchable
            value={field.value ?? null}
            onChange={(v) => {
              field.onChange(v)
              // Al salir de un nombramiento temporal el enlace de reemplazo
              // deja de ser válido.
              if (!NOMBRAMIENTOS_DE_REEMPLAZO.includes(v ?? '')) {
                setValue('cubre_movimiento_id', null)
              }
              // Y si la modalidad nueva no marca nunca, la casilla se apaga DE
              // VERDAD. El interruptor se pintaba apagado con
              // `checked={admite && …}` pero el valor del formulario seguía en
              // true, y eso es lo que viajaba: al editar el borrador el backend
              // lo guardaba tal cual y el documento acababa diciendo «Marca
              // asistencia: Sí» en un contrato civil.
              if (!admiteMarcacion(v)) {
                setValue('puede_marcar', false)
              }
            }}
            error={errors.tipo_nombramiento_propuesto?.message}
            {...contained}
          />
        )}
      />

      {/* Un reemplazo dura lo que dura la ausencia, así que solo se ofrece con
          nombramiento temporal. La misma regla la impone validarReemplazo(). */}
      {puedeCubrir && (
        <Controller
          name="cubre_movimiento_id"
          control={control}
          render={({ field }) => (
            <Select
              label="¿Cubre una ausencia temporal?"
              description="Enlaza este ingreso con la comisión o licencia cuyo hueco viene a cubrir. El titular conserva su plaza."
              placeholder={ausenciaOptions.length === 0
                ? 'No hay ausencias sin cubrir'
                : 'Ninguna — es un ingreso ordinario'}
              data={ausenciaOptions}
              disabled={ausenciaOptions.length === 0}
              searchable
              clearable
              value={field.value ? String(field.value) : null}
              onChange={(v) => {
                field.onChange(v ? Number(v) : null)

                // El suplente entra a la plaza del ausente, no a otra.
                const sel = ausencias.find((a) => String(a.id) === v)
                if (sel?.unidad_id) setValue('unidad_destino_id', sel.unidad_id)
                if (sel?.puesto_id) setValue('puesto_destino_id', sel.puesto_id)
              }}
              {...contained}
            />
          )}
        />
      )}

      {ausenciaSel && (
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          Reemplaza a <strong>{ausenciaSel.servidor.nombre}</strong> en{' '}
          {ausenciaSel.puesto ?? 'su puesto'}. El contrato no puede
          pasar del {fechaCorta(ausenciaSel.hasta)}, que es cuando regresa.
        </Alert>
      )}

      <TextInput
        label="Número de contrato"
        placeholder="Ej: CT-2026-0099"
        error={errors.numero_contrato?.message}
        {...contained}
        {...register('numero_contrato')}
      />

      <Controller
        name="fecha_fin_propuesta"
        control={control}
        render={({ field }) => (
          <DatePickerInput
            label="Fecha de término del contrato"
            description="Servicios Profesionales toma el 31 de diciembre de su año si se deja vacío."
            valueFormat="DD/MM/YYYY"
            clearable
            value={toDateValue(field.value)}
            onChange={(d) => field.onChange(fromDateValueOrNull(d))}
            error={errors.fecha_fin_propuesta?.message}
            {...contained}
          />
        )}
      />

      <Controller
        name="puede_marcar"
        control={control}
        render={({ field }) => (
          // Servicios profesionales, libre nombramiento y elección popular no
          // marcan nunca: el interruptor se apaga y se bloquea, y el backend
          // fuerza el valor igualmente.
          <Switch
            label="Marcación biométrica"
            description={marca
              ? 'Sugerida según el nombramiento; ajústela si este caso es distinto.'
              : 'Esta modalidad no marca biométrico.'}
            checked={marca && !!field.value}
            disabled={!marca}
            onChange={(e) => field.onChange(e.currentTarget.checked)}
          />
        )}
      />
    </>
  )
}
