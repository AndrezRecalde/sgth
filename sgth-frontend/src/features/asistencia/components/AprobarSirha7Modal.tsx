'use client'

import { Select, Stack } from '@mantine/core'
import { Controller, useForm, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DataState, FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useAprobarSirha7, usePreviaSirha7, useTiposSirha7 } from '../hooks/usePermisoSirha7'
import { aprobarSirha7Schema, type AprobarSirha7FormData } from './aprobarSirha7.schema'
import { PreviaSirha7Detalle } from './PreviaSirha7Detalle'
import { TIPOS_TRABAJO_SOCIAL } from './permisos.constants'
import type { PermisoServidor } from '@/types/api'

interface Props {
  /** El permiso a aprobar; `null` con el diálogo cerrado. */
  permiso: PermisoServidor | null
  onClose: () => void
}

// El tipo se omite: lo elige siempre quien aprueba (decisión del 2026-10-07).
const VALORES_INICIALES: DefaultValues<AprobarSirha7FormData> = {}

/**
 * Aprobar un permiso registrándolo en Sirha7, el biométrico.
 *
 * Personal y oficial los aprueba Talento Humano. Enfermedad y calamidad las
 * aprueba Trabajo Social, y aprobarlas las deja validadas: por eso el título
 * dice «Validar y aprobar» (decisión del 2026-10-08).
 */
export function AprobarSirha7Modal({ permiso, onClose }: Props) {
  const contained = useContainedInput()
  const previa = usePreviaSirha7(permiso?.id ?? null)
  const tipos = useTiposSirha7(permiso !== null)
  const aprobar = useAprobarSirha7()

  const { control, handleSubmit, reset, setError, formState: { errors } } = useForm<AprobarSirha7FormData>({
    resolver: zodResolver(aprobarSirha7Schema),
    defaultValues: VALORES_INICIALES,
  })

  const cerrar = () => {
    reset(VALORES_INICIALES)
    onClose()
  }

  const enviar = handleSubmit((valores) => {
    if (!permiso) return

    aprobar.mutate(
      { id: permiso.id, leaveId: valores.leave_id },
      {
        onSuccess: cerrar,
        onError: (error) => {
          const campos = erroresDeCampo(error)
          if (campos?.leave_id) setError('leave_id', { type: 'server', message: campos.leave_id })
        },
      },
    )
  })

  const validaTs = !!permiso && TIPOS_TRABAJO_SOCIAL.includes(permiso.tipo as string)
  const opciones = (tipos.data ?? []).map((t) => ({ value: String(t.id), label: t.nombre }))

  return (
    <FormModal
      opened={permiso !== null}
      onClose={cerrar}
      title={validaTs ? 'Validar y aprobar en Sirha7' : 'Aprobar en Sirha7'}
      onSubmit={enviar}
      submitLabel={validaTs ? 'Validar y aprobar' : 'Aprobar'}
      submitting={aprobar.isPending}
      submitDisabled={!previa.data?.pendiente}
      closeOnClickOutside={false}
    >
      <DataState loading={previa.isLoading} error={previa.error} empty={false}>
        {permiso && previa.data && (
          <Stack gap="md">
            <PreviaSirha7Detalle permiso={permiso} previa={previa.data} />

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
                  disabled={!previa.data?.pendiente}
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                  error={errors.leave_id?.message ?? (tipos.isError ? 'No se pudieron leer los tipos de Sirha7.' : undefined)}
                />
              )}
            />
          </Stack>
        )}
      </DataState>
    </FormModal>
  )
}
