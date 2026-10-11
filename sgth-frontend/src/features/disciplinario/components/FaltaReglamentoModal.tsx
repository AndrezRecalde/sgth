'use client'

import { Alert, Checkbox, Group, Input, NumberInput, Stack, Switch, Textarea, TextInput } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import type { FaltaReglamentoInterno } from '@/types/api'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import {
  MULTA_MAXIMA_OBRERO,
  faltaReglamentoSchema,
  type FaltaReglamentoFormData,
} from '../schemas/faltaReglamento.schema'

interface Props {
  opened: boolean
  onClose: () => void
  /** Presente = se corrige; ausente = se agrega una nueva. */
  falta?: FaltaReglamentoInterno | null
}

/**
 * Una falta del reglamento interno de los obreros, como la redacta su copia
 * aprobada (fase 4.4). La que ya se imputó en un procedimiento no se reescribe:
 * se desactiva y se carga la redacción nueva.
 */
export function FaltaReglamentoModal({ opened, onClose, falta }: Props) {
  // Se monta al abrir: el formulario arranca con la falta elegida sin
  // resetearlo desde un efecto.
  return opened ? <FormularioFalta key={falta?.id ?? 'nueva'} onClose={onClose} falta={falta ?? null} /> : null
}

function FormularioFalta({ onClose, falta }: { onClose: () => void; falta: FaltaReglamentoInterno | null }) {
  const contained = useContainedInput()
  const { guardarFalta } = useDisciplinarioMutations()

  const {
    control, register, handleSubmit, setError, formState: { errors },
  } = useForm<FaltaReglamentoFormData>({
    resolver: zodResolver(faltaReglamentoSchema),
    defaultValues: {
      articulo: falta?.articulo ?? '',
      descripcion: falta?.descripcion ?? '',
      gravedad: falta?.gravedad ?? '',
      admite_amonestacion_verbal: falta?.admite_amonestacion_verbal ?? false,
      admite_amonestacion_escrita: falta?.admite_amonestacion_escrita ?? false,
      admite_multa: falta?.admite_multa ?? false,
      porcentaje_maximo_multa: falta?.porcentaje_maximo_multa != null ? Number(falta.porcentaje_maximo_multa) : null,
      activo: falta?.activo ?? true,
    },
  })

  const conMulta = useWatch({ control, name: 'admite_multa' })

  const guardar = async (v: FaltaReglamentoFormData) => {
    try {
      await guardarFalta.mutateAsync({
        id: falta?.id,
        data: {
          ...v,
          gravedad: v.gravedad?.trim() || null,
          porcentaje_maximo_multa: v.admite_multa ? v.porcentaje_maximo_multa ?? null : null,
        },
      })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      for (const campo of ['articulo', 'descripcion', 'gravedad', 'porcentaje_maximo_multa'] as const) {
        if (campos?.[campo]) setError(campo, { type: 'server', message: campos[campo] })
      }
      if (campos?.sanciones) setError('admite_multa', { type: 'server', message: campos.sanciones })
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title={falta ? 'Corregir falta del reglamento interno' : 'Agregar falta del reglamento interno'}
      onSubmit={handleSubmit(guardar)}
      submitLabel={falta ? 'Guardar cambios' : 'Agregar falta'}
      submitting={guardarFalta.isPending}
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          Cópiela tal como la trae el reglamento interno aprobado: al obrero solo
          se le sanciona lo que el reglamento prevé (Código del Trabajo, Art. 44 a).
          Las faltas que el reglamento manda al visto bueno no van aquí.
        </Alert>

        <Group grow align="flex-start">
          <TextInput
            label="Artículo o numeral"
            placeholder="Ej. Art. 45, núm. 3"
            withAsterisk
            error={errors.articulo?.message}
            {...contained}
            {...register('articulo')}
          />
          <TextInput
            label="Gravedad"
            placeholder="Como la llame el reglamento"
            error={errors.gravedad?.message}
            {...contained}
            {...register('gravedad')}
          />
        </Group>

        <Textarea
          label="Descripción de la falta"
          withAsterisk
          autosize
          minRows={3}
          error={errors.descripcion?.message}
          {...contained}
          {...register('descripcion')}
        />

        <Input.Wrapper label="Sanciones que admite" withAsterisk error={errors.admite_multa?.message}>
          <Stack gap={6} mt={6}>
            {([
              ['admite_amonestacion_verbal', 'Amonestación verbal'],
              ['admite_amonestacion_escrita', 'Amonestación escrita'],
              ['admite_multa', 'Multa'],
            ] as const).map(([campo, etiqueta]) => (
              <Controller
                key={campo}
                name={campo}
                control={control}
                render={({ field }) => (
                  <Checkbox
                    label={etiqueta}
                    checked={!!field.value}
                    onChange={(e) => field.onChange(e.currentTarget.checked)}
                  />
                )}
              />
            ))}
          </Stack>
        </Input.Wrapper>

        {conMulta && (
          <Controller
            name="porcentaje_maximo_multa"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Multa de hasta (% de la remuneración)"
                description={`Nunca más del ${MULTA_MAXIMA_OBRERO} %.`}
                withAsterisk
                min={0.01}
                max={MULTA_MAXIMA_OBRERO}
                decimalScale={2}
                hideControls
                error={errors.porcentaje_maximo_multa?.message}
                {...contained}
                value={field.value ?? ''}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : null)}
              />
            )}
          />
        )}

        {falta && (
          <Controller
            name="activo"
            control={control}
            render={({ field }) => (
              <Switch
                label="Vigente"
                description="Desactívela si el reglamento la derogó o cambió: lo ya resuelto la conserva."
                checked={!!field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
        )}
      </Stack>
    </FormModal>
  )
}
