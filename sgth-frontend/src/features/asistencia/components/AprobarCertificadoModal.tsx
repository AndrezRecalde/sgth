'use client'

import { useEffect } from 'react'
import { Alert, Checkbox, Select, Stack, Text, Textarea } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DataState, FormModal } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUltimoAbierto } from '@/hooks/useUltimoAbierto'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useTiposSirha7 } from '../hooks/usePermisoSirha7'
import {
  useAprobarCertificado,
  useAprobarCertificadoSinSirha7,
  usePreviaCertificado,
} from '../hooks/useCertificadosAprobacion'
import { aprobarCertificadoSchema, type AprobarCertificadoFormData } from './aprobarCertificado.schema'
import { PreviaSirha7Detalle } from './PreviaSirha7Detalle'
import type { CertificadoAprobacion } from '@/types/api'

interface Props {
  /** El certificado a aprobar; `null` con el diálogo cerrado. */
  certificado: CertificadoAprobacion | null
  onClose: () => void
}

/**
 * Aprobar un certificado médico y registrarlo en Sirha7 (decisión del
 * 2026-10-08). Si el SGTH no puede escribirlo allí —lo emitido antes de la
 * fecha de corte, o una cédula que Sirha7 no reconoce—, se aprueba sin Sirha7
 * con una nota: TH ya lo cargó a mano.
 */
export function AprobarCertificadoModal({ certificado, onClose }: Props) {
  const abierto = certificado !== null
  const contained = useContainedInput()
  const previa = usePreviaCertificado(certificado?.id ?? null)
  const tipos = useTiposSirha7(abierto)
  const aprobar = useAprobarCertificado()
  const aprobarSinSirha7 = useAprobarCertificadoSinSirha7()

  // Lo de ahora con el diálogo abierto; lo último mientras se cierra.
  const mostrado = useUltimoAbierto(certificado, abierto)
  const previaMostrada = useUltimoAbierto(previa.data, abierto)
  const registrable = previaMostrada?.registrable_en_sirha7 ?? false

  const { control, handleSubmit, reset, setError, formState: { errors } } = useForm<AprobarCertificadoFormData>({
    resolver: zodResolver(aprobarCertificadoSchema),
    defaultValues: { sin_sirha7: false },
  })
  const sinSirha7 = useWatch({ control, name: 'sin_sirha7' })

  // Se limpia al abrir, no al cerrar: al cerrar se borraba a la vista. Y si el
  // SGTH no puede escribirlo en Sirha7, solo cabe aprobarlo sin Sirha7.
  const soloSinSirha7 = previa.data ? !previa.data.registrable_en_sirha7 : false
  useEffect(() => {
    if (abierto) reset({ sin_sirha7: soloSinSirha7 })
  }, [abierto, soloSinSirha7, reset])

  const alFallar = (error: unknown) => {
    const campos = erroresDeCampo(error)
    if (campos?.leave_id) setError('leave_id', { type: 'server', message: campos.leave_id })
    if (campos?.nota) setError('nota', { type: 'server', message: campos.nota })
  }

  const enviar = handleSubmit((v) => {
    if (!certificado) return

    if (v.sin_sirha7) {
      aprobarSinSirha7.mutate({ id: certificado.id, nota: (v.nota ?? '').trim() }, { onSuccess: onClose, onError: alFallar })
    } else if (v.leave_id) {
      aprobar.mutate({ id: certificado.id, leaveId: v.leave_id }, { onSuccess: onClose, onError: alFallar })
    }
  })

  const opciones = (tipos.data ?? []).map((t) => ({ value: String(t.id), label: t.nombre }))
  const servidor = mostrado?.servidor

  return (
    <FormModal
      opened={abierto}
      onClose={onClose}
      title="Aprobar certificado médico"
      onSubmit={enviar}
      submitLabel={sinSirha7 ? 'Aprobar sin Sirha7' : 'Aprobar y registrar'}
      submitting={aprobar.isPending || aprobarSinSirha7.isPending}
      submitDisabled={!previaMostrada?.pendiente}
      closeOnClickOutside={false}
    >
      <DataState loading={abierto && previa.isLoading} error={abierto ? previa.error : null} empty={false}>
        {mostrado && previaMostrada && (
          <Stack gap="md">
            <PreviaSirha7Detalle
              servidor={[servidor?.apellido, servidor?.nombre].filter(Boolean).join(' ')}
              folio={mostrado.folio}
              previa={previaMostrada}
              registra={!sinSirha7}
            />

            {previaMostrada.pendiente && !registrable && previaMostrada.motivo_sin_sirha7 && (
              <Alert color={SEMANTIC_COLOR.warning} variant="light" icon={<IconAlertTriangle size={16} />}>
                <Text size="sm">{previaMostrada.motivo_sin_sirha7}</Text>
              </Alert>
            )}

            {previaMostrada.pendiente && registrable && (
              <Controller
                name="sin_sirha7"
                control={control}
                render={({ field }) => (
                  <Checkbox
                    label="Ya lo cargué a mano en Sirha7: aprobarlo sin registrarlo"
                    description="Para una cédula que Sirha7 no reconoce. Cuenta igual en el ausentismo."
                    checked={field.value}
                    onChange={(e) => field.onChange(e.currentTarget.checked)}
                  />
                )}
              />
            )}

            {previaMostrada.pendiente && (sinSirha7 ? (
              <Controller
                name="nota"
                control={control}
                render={({ field }) => (
                  <Textarea
                    label="Nota"
                    placeholder="Por ejemplo: cargado a mano en Sirha7 el 09/10/2026"
                    withAsterisk
                    autosize
                    minRows={2}
                    maxLength={500}
                    {...contained}
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.currentTarget.value)}
                    error={errors.nota?.message}
                  />
                )}
              />
            ) : (
              <Controller
                name="leave_id"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Tipo de permiso en Sirha7"
                    placeholder={tipos.isLoading ? 'Cargando los tipos de Sirha7…' : 'Seleccione el tipo'}
                    data={opciones}
                    searchable
                    withAsterisk
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                    error={errors.leave_id?.message ?? (tipos.isError ? 'No se pudieron leer los tipos de Sirha7.' : undefined)}
                  />
                )}
              />
            ))}
          </Stack>
        )}
      </DataState>
    </FormModal>
  )
}
