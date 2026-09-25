'use client'

import { useEffect, useState } from 'react'
import {
  Button, Group, Stack, Select, Textarea, Text, } from '@mantine/core'
import { ModalFooter, SectionHeading, SgthModal } from '@/components/ui'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCumplimientoMutations } from '../hooks/useCumplimiento'
import { DocumentosSsoPanel } from './DocumentosSsoPanel'
import {
  cumplimientoSchema, type CumplimientoFormData, ESTADO_CUMPLIMIENTO_OPTIONS,
} from '../schemas/cumplimiento.schema'
import type { FilaListaVerificacion } from '../services/tipos'

interface Props {
  opened: boolean
  onClose: () => void
  fila: FilaListaVerificacion | null
  periodo: string
}

export function RegistrarCumplimientoModal({ opened, onClose, fila, periodo }: Props) {
  const contained = useContainedInput()
  const { registrar } = useCumplimientoMutations()
  const [cumplimientoId, setCumplimientoId] =
    useState<number | null>(fila?.cumplimiento?.id ?? null)

  // El id se siembra desde la fila y lo actualiza el guardado, así que es
  // estado y no derivado. La resiembra se hace durante el render, no en un
  // efecto: el panel de documentos apuntaba a la fila anterior mientras el
  // efecto no se hubiera ejecutado.
  const claveFila = fila?.normativa.id ?? null
  const [claveAplicada, setClaveAplicada] = useState<number | null>(claveFila)

  if (claveFila !== claveAplicada) {
    setClaveAplicada(claveFila)
    setCumplimientoId(fila?.cumplimiento?.id ?? null)
  }

  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<CumplimientoFormData>({
    resolver: zodResolver(cumplimientoSchema) as Resolver<CumplimientoFormData>,
    defaultValues: {
      estado: (fila?.cumplimiento?.estado as CumplimientoFormData['estado']) ?? 'en_proceso',
      observaciones: fila?.cumplimiento?.observaciones ?? '',
    },
  })

  useEffect(() => {
    reset({
      estado: (fila?.cumplimiento?.estado as CumplimientoFormData['estado']) ?? 'en_proceso',
      observaciones: fila?.cumplimiento?.observaciones ?? '',
    })
  }, [fila, reset])

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: CumplimientoFormData) => {
    if (!fila) return
    registrar.mutateAsync({
      normativa_legal_sso_id: fila.normativa.id,
      periodo,
      ...values,
    }).then((resultado) => {
      setCumplimientoId(resultado?.id ?? null)
    }).catch(() => {})
  }

  return (
    <SgthModal
      opened={opened}
      onClose={handleClose}
      title="Registrar cumplimiento"
      size="md"
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="sm">
          {fila && (
            <Text size="sm" c="dimmed">
              <Text span fw={600}>{fila.normativa.nombre}</Text> — período {periodo}
            </Text>
          )}
          <Controller
            name="estado"
            control={control}
            render={({ field }) => (
              <Select
                label="Estado"
                data={ESTADO_CUMPLIMIENTO_OPTIONS}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v as CumplimientoFormData['estado'])}
                error={errors.estado?.message}
              />
            )}
          />
          <Textarea
            label="Observaciones"
            rows={2}
            {...contained}
            {...register('observaciones')}
            error={errors.observaciones?.message}
          />
          <Group justify="flex-end">
            <Button type="submit" loading={registrar.isPending} size="xs" variant="light">
              Guardar
            </Button>
          </Group>

          <SectionHeading title="Evidencia" />
          <DocumentosSsoPanel tipo="cumplimiento_normativa" documentableId={cumplimientoId} />

          <ModalFooter onCancel={handleClose} cancelLabel="Cerrar" sinPrincipal />
        </Stack>
      </form>
    </SgthModal>
  )
}
