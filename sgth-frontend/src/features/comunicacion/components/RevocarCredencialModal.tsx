'use client'

import { Alert, Select, Stack, Textarea } from '@mantine/core'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm, useWatch, type DefaultValues } from 'react-hook-form'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { MOTIVOS_REVOCACION } from '../constants/credenciales'
import { useMutacionesCredencial } from '../hooks/useCredenciales'
import { revocarCredencialSchema, type RevocarCredencialFormData } from '../schemas/credencial.schema'
import type { ServidorConCredencial } from '../services/tiposCredencial'

interface Props {
  servidor: ServidorConCredencial | null
  onClose: () => void
}

// El motivo se omite: lo elige quien revoca.
const VALORES_INICIALES: DefaultValues<RevocarCredencialFormData> = { detalle: '' }

/**
 * Revocar una credencial (docs/diseno-comunicacion-social.md, 6.3): su QR deja
 * de validar y, si el servidor sigue activo y con foto, se repone en el acto
 * con otro código. Una tarjeta solo deteriorada no se revoca: se reimprime.
 */
export function RevocarCredencialModal({ servidor, onClose }: Props) {
  const contained = useContainedInput()
  const { revocar } = useMutacionesCredencial()

  const { control, register, handleSubmit, reset, setError, clearErrors, formState: { errors } } = useForm<RevocarCredencialFormData>({
    resolver: zodResolver(revocarCredencialSchema),
    defaultValues: VALORES_INICIALES,
  })
  const motivo = useWatch({ control, name: 'motivo' })

  const cerrar = () => {
    reset(VALORES_INICIALES)
    onClose()
  }

  const enviar = (valores: RevocarCredencialFormData) => {
    if (!servidor?.credencial) return

    revocar.mutateAsync({
      credencialId: servidor.credencial.id,
      motivo: valores.motivo,
      detalle: valores.detalle || null,
    })
      .then(cerrar)
      .catch((error) => erroresAlFormulario(error, setError, ['motivo', 'detalle'], null))
  }

  return (
    <FormModal
      opened={!!servidor}
      onClose={cerrar}
      title="Revocar y reponer la credencial"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Revocar"
      submitting={revocar.isPending}
      destructiva
      size="md"
    >
      <Stack gap="md">
        <Alert variant="light" color="amber">
          La tarjeta de {servidor?.nombre_completo} dejará de validar al escanearla. Se crea otra con un código nuevo, que
          habrá que imprimir. Si la tarjeta solo está deteriorada, no la revoque: vuelva a imprimirla.
        </Alert>

        <Controller
          name="motivo"
          control={control}
          render={({ field }) => (
            <Select
              label="Motivo"
              placeholder="Elija el motivo"
              data={MOTIVOS_REVOCACION}
              {...contained}
              value={field.value ?? null}
              onChange={(v) => {
                field.onChange(v)
                // El detalle solo es obligatorio con «Otro»: su error no sigue a otro motivo.
                clearErrors('detalle')
              }}
              error={errors.motivo?.message}
            />
          )}
        />

        <Textarea
          label="Detalle"
          placeholder={motivo === 'otro' ? 'Diga por qué se revoca' : 'Opcional: la denuncia, el dato equivocado…'}
          autosize
          minRows={2}
          {...contained}
          {...register('detalle')}
          error={errors.detalle?.message}
        />
      </Stack>
    </FormModal>
  )
}
