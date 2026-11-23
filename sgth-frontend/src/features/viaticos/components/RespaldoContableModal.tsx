'use client'

import { useEffect } from 'react'
import { Alert, Stack, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, hoyIso, toDateValue } from '@/lib/fecha'
import { SelectPartidaPresupuestaria } from '@/features/estructura/components/SelectPartidaPresupuestaria'
import { respaldoContableSchema, respaldoDelAnticipoSchema } from '../schemas/aprobacion.schema'
import type { RespaldoContable } from '../services/viaticoService'

/** Las partidas del gasto de viáticos, confirmadas con Gestión Financiera. */
const PARTIDAS_DE_VIATICO = ['530303', '530304']

/*
| El respaldo del pago que asigna Gestión Financiera (2026-09-15): al entregar
| el anticipo, y al contabilizar cuando el viático se tramitó sin anticipo.
|
| Las dos columnas existían desde el principio y ningún formulario las pedía:
| el comprobante contable salía sin respaldo.
|
| Al entregar el anticipo se pide además la fecha (`conFecha`), que es cuando
| sale el dinero: hasta ahora el comprobante lo descontaba sin decir cuándo
| salió ni con qué documento (Gestión Financiera, 2026-11-23).
*/

interface Props {
  opened: boolean
  onClose: () => void
  title: string
  descripcion: React.ReactNode
  confirmLabel: string
  cargando?: boolean
  /** Entregando el anticipo: se pide también la fecha en que salió. */
  conFecha?: boolean
  onConfirm: (datos: RespaldoContable) => void
}

export function RespaldoContableModal({
  opened,
  onClose,
  title,
  descripcion,
  confirmLabel,
  cargando = false,
  conFecha = false,
  onConfirm,
}: Props) {
  const contained = useContainedInput()

  // La partida se omite: la elige quien abre el modal, y el valor en tiempo de
  // ejecución es el mismo `undefined`.
  const iniciales = (): DefaultValues<RespaldoContable> => ({
    numero_resolucion: '',
    ...(conFecha ? { fecha_anticipo: hoyIso() } : {}),
  })

  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<RespaldoContable>({
    resolver: zodResolver(conFecha ? respaldoDelAnticipoSchema : respaldoContableSchema),
    defaultValues: iniciales(),
  })

  // El modal vive montado: sin esto conserva lo escrito la vez anterior.
  useEffect(() => {
    if (opened) reset(iniciales())
    // `iniciales` se rehace en cada render; lo que importa es abrir y el modo.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, conFecha, reset])

  const cerrar = () => {
    reset()
    onClose()
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title={title}
      size="md"
      closeOnClickOutside={false}
      onSubmit={handleSubmit((datos) => onConfirm(datos))}
      submitLabel={confirmLabel}
      submitting={cargando}
    >
      <Stack gap="md">
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light" py={8}>
          {descripcion}
        </Alert>

        <TextInput
          label="Número del comprobante de pago"
          placeholder="El que emite el sistema de Gestión Financiera"
          {...contained}
          {...register('numero_resolucion')}
          error={errors.numero_resolucion?.message}
        />

        {conFecha && (
          <Controller
            name="fecha_anticipo"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de entrega"
                placeholder="Seleccionar fecha"
                valueFormat="DD/MM/YYYY"
                // Es el día en que sale el dinero: no puede ser futuro.
                maxDate={new Date()}
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                error={errors.fecha_anticipo?.message}
              />
            )}
          />
        )}

        <Controller
          name="partida_presupuestaria_id"
          control={control}
          render={({ field }) => (
            <SelectPartidaPresupuestaria
              value={field.value ?? null}
              onChange={(v) => field.onChange(v ?? undefined)}
              codigos={PARTIDAS_DE_VIATICO}
              description="El gasto de viáticos se imputa a 530303 en el interior y a 530304 en el exterior."
              error={errors.partida_presupuestaria_id?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
