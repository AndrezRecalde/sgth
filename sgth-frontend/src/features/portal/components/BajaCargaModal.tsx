'use client'

import { Alert, FileInput, Stack, Text, Textarea } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import type { MiCargaFamiliar } from '@/types/api'
import { useMisCargasMutations } from '../hooks/useMisCargas'
import { bajaCargaSchema, type BajaCargaFormData } from '../schemas/solicitudCarga.schema'

interface Props {
  carga:   MiCargaFamiliar | null
  onClose: () => void
}

const VACIO: BajaCargaFormData = { motivo: '', documento: null }

/**
 * Pedir que un familiar deje de ser carga: divorcio, fallecimiento, el hijo ya
 * no depende. Al validarla, Talento Humano lo desactiva sin borrarlo, así que
 * su historia clínica se conserva. El motivo es obligatorio; el documento, no
 * (D9: ningún beneficio en dinero depende de las cargas).
 */
export function BajaCargaModal({ carga, onClose }: Props) {
  const contained = useContainedInput()
  const { presentar } = useMisCargasMutations()
  const form = useForm<BajaCargaFormData>({
    resolver: zodResolver(bajaCargaSchema),
    defaultValues: VACIO,
  })
  const { control, register, formState: { errors } } = form

  const cerrar = () => {
    form.reset(VACIO)
    onClose()
  }

  const enviar = form.handleSubmit((v) => {
    if (!carga) return
    presentar.mutateAsync({ tipo: 'baja', carga_familiar_id: carga.id, motivo: v.motivo, documento: v.documento })
      .then(cerrar)
      .catch((e) => erroresAlFormulario(e, form.setError, ['motivo', 'documento'], 'No se pudo pedir la baja'))
  })

  return (
    <FormModal
      opened={carga !== null}
      onClose={cerrar}
      title="Pedir la baja de una carga familiar"
      size="md"
      onSubmit={enviar}
      submitLabel="Enviar a Talento Humano"
      submitting={presentar.isPending}
    >
      <Stack gap="sm">
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
          <Text size="sm">
            {carga ? `${carga.nombres} ${carga.apellidos}` : ''} dejará de ser su carga cuando Talento Humano
            valide la baja. No se borra de su expediente.
          </Text>
        </Alert>

        <Textarea label="Motivo" placeholder="Ej: ya trabaja y no depende de mí"
          rows={2} withAsterisk {...contained} {...register('motivo')}
          error={errors.motivo?.message} />

        <Controller name="documento" control={control}
          render={({ field }) => (
            <FileInput label="Documento que lo respalde (PDF, opcional)" placeholder="Seleccione el PDF"
              accept="application/pdf" clearable {...contained}
              value={field.value ?? null} onChange={field.onChange}
              error={errors.documento?.message} />
          )} />
      </Stack>
    </FormModal>
  )
}
