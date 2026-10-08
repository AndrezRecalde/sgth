'use client'

import { useEffect, useState } from 'react'
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
 * El último valor que tuvo `valor` con el diálogo abierto.
 *
 * El diálogo se anima al cerrarse, y para entonces el permiso ya es `null`:
 * sin esto, durante la animación el título pasaba a «Aprobar en Sirha7» y el
 * cuerpo se quedaba vacío. Visto el 2026-10-08 al aprobar un certificado.
 */
function useUltimoAbierto<T>(valor: T | null | undefined, abierto: boolean): T | null {
  const [ultimo, setUltimo] = useState<T | null>(valor ?? null)

  if (abierto && valor != null && valor !== ultimo) setUltimo(valor)

  return abierto ? (valor ?? null) : ultimo
}

/**
 * Aprobar un permiso registrándolo en Sirha7, el biométrico.
 *
 * Personal y oficial los aprueba Talento Humano. Enfermedad y calamidad las
 * aprueba Trabajo Social, y aprobarlas las deja validadas: por eso el título
 * dice «Validar y aprobar» (decisión del 2026-10-08).
 */
export function AprobarSirha7Modal({ permiso, onClose }: Props) {
  const abierto = permiso !== null
  const contained = useContainedInput()
  const previa = usePreviaSirha7(permiso?.id ?? null)
  const tipos = useTiposSirha7(abierto)
  const aprobar = useAprobarSirha7()

  // Lo que se pinta: lo de ahora con el diálogo abierto, lo último mientras se cierra.
  const mostrado = useUltimoAbierto(permiso, abierto)
  const previaMostrada = useUltimoAbierto(previa.data, abierto)

  const { control, handleSubmit, reset, setError, formState: { errors } } = useForm<AprobarSirha7FormData>({
    resolver: zodResolver(aprobarSirha7Schema),
    defaultValues: VALORES_INICIALES,
  })

  // Se limpia al abrir y no al cerrar: al cerrar, el tipo elegido se borraba
  // a la vista durante la animación.
  useEffect(() => { if (abierto) reset(VALORES_INICIALES) }, [abierto, reset])

  const enviar = handleSubmit((valores) => {
    if (!permiso) return

    aprobar.mutate(
      { id: permiso.id, leaveId: valores.leave_id },
      {
        onSuccess: onClose,
        onError: (error) => {
          const campos = erroresDeCampo(error)
          if (campos?.leave_id) setError('leave_id', { type: 'server', message: campos.leave_id })
        },
      },
    )
  })

  const validaTs = !!mostrado && TIPOS_TRABAJO_SOCIAL.includes(mostrado.tipo as string)
  const opciones = (tipos.data ?? []).map((t) => ({ value: String(t.id), label: t.nombre }))

  return (
    <FormModal
      opened={abierto}
      onClose={onClose}
      title={validaTs ? 'Validar y aprobar en Sirha7' : 'Aprobar en Sirha7'}
      onSubmit={enviar}
      submitLabel={validaTs ? 'Validar y aprobar' : 'Aprobar'}
      submitting={aprobar.isPending}
      submitDisabled={!previaMostrada?.pendiente}
      closeOnClickOutside={false}
    >
      <DataState loading={abierto && previa.isLoading} error={abierto ? previa.error : null} empty={false}>
        {mostrado && previaMostrada && (
          <Stack gap="md">
            <PreviaSirha7Detalle permiso={mostrado} previa={previaMostrada} />

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
                  disabled={!previaMostrada?.pendiente}
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
