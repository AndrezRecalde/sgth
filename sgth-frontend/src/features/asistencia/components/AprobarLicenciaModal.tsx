'use client'

import { useEffect } from 'react'
import { Alert, Checkbox, List, Select, Stack, Text, Textarea } from '@mantine/core'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DataState, DetailList, FormModal } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useUltimoAbierto } from '@/hooks/useUltimoAbierto'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { formatFecha } from '@/lib/fecha'
import { useTiposSirha7 } from '../hooks/usePermisoSirha7'
import { useAprobarLicencia, useAprobarLicenciaSinSirha7, usePreviaLicencia } from '../hooks/useLicencias'
import type { Licencia } from '../services/licenciaService'
import { aprobarCertificadoSchema, type AprobarCertificadoFormData } from './aprobarCertificado.schema'

interface Props {
  /** La licencia a aprobar; `null` con el diálogo cerrado. */
  licencia: Licencia | null
  onClose: () => void
}

/**
 * Aprobar una licencia y registrarla en Sirha7 (fase 5.1), como los
 * certificados médicos: con el tipo que elige quien aprueba, o sin Sirha7 y con
 * una nota si TH ya la cargó a mano. La de más de 31 días va en tramos.
 */
export function AprobarLicenciaModal({ licencia, onClose }: Props) {
  const abierto = licencia !== null
  const contained = useContainedInput()
  const previa = usePreviaLicencia(licencia?.id ?? null)
  const tipos = useTiposSirha7(abierto)
  const aprobar = useAprobarLicencia()
  const aprobarSinSirha7 = useAprobarLicenciaSinSirha7()

  // Lo de ahora con el diálogo abierto; lo último mientras se cierra.
  const mostrada = useUltimoAbierto(licencia, abierto)
  const previaMostrada = useUltimoAbierto(previa.data, abierto)
  const registrable = previaMostrada?.registrable_en_sirha7 ?? false

  // El mismo formulario que el de los certificados: tipo de Sirha7, o nota.
  const { control, handleSubmit, reset, setError, formState: { errors } } = useForm<AprobarCertificadoFormData>({
    resolver: zodResolver(aprobarCertificadoSchema),
    defaultValues: { sin_sirha7: false },
  })
  const sinSirha7 = useWatch({ control, name: 'sin_sirha7' })

  // Si el SGTH no puede escribirla en Sirha7, solo cabe aprobarla sin Sirha7.
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
    if (!licencia) return

    if (v.sin_sirha7) {
      aprobarSinSirha7.mutate({ id: licencia.id, nota: (v.nota ?? '').trim() }, { onSuccess: onClose, onError: alFallar })
    } else if (v.leave_id) {
      aprobar.mutate({ id: licencia.id, leaveId: v.leave_id }, { onSuccess: onClose, onError: alFallar })
    }
  })

  const opciones = (tipos.data ?? []).map((t) => ({ value: String(t.id), label: t.nombre }))
  const servidor = mostrada?.servidor
  const tramos = previaMostrada?.tramos ?? []

  return (
    <FormModal
      opened={abierto}
      onClose={onClose}
      title="Aprobar licencia"
      onSubmit={enviar}
      submitLabel={sinSirha7 ? 'Aprobar sin Sirha7' : 'Aprobar y registrar'}
      submitting={aprobar.isPending || aprobarSinSirha7.isPending}
      submitDisabled={!previaMostrada?.pendiente}
      closeOnClickOutside={false}
    >
      <DataState loading={abierto && previa.isLoading} error={abierto ? previa.error : null} empty={false}>
        {mostrada && previaMostrada && (
          <Stack gap="md">
            {!previaMostrada.pendiente && previaMostrada.motivo && (
              <Alert color={SEMANTIC_COLOR.danger} variant="light" icon={<IconAlertTriangle size={16} />}>
                <Text size="sm">{previaMostrada.motivo}</Text>
              </Alert>
            )}

            <DetailList
              items={[
                { label: 'Servidor', value: [servidor?.apellido, servidor?.nombre].filter(Boolean).join(' ') },
                { label: 'Folio', value: mostrada.folio },
                { label: 'Causa', value: mostrada.alcance_etiqueta ? `${mostrada.causa_etiqueta} · ${mostrada.alcance_etiqueta}` : mostrada.causa_etiqueta },
                { label: 'Días', value: `${formatFecha(mostrada.fecha_inicio)} al ${formatFecha(mostrada.fecha_fin)} (${mostrada.dias})` },
              ]}
            />

            {!sinSirha7 && tramos.length > 0 && (
              <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />} py={8}>
                {tramos.length === 1 ? (
                  <Text size="xs">
                    Se escribe una fila por día de jornada completa, con la referencia <b>{tramos[0].referencia}</b>.
                  </Text>
                ) : (
                  <>
                    <Text size="xs">
                      Sirha7 no admite más de 31 días por registro: va en {tramos.length} tramos, cada uno con
                      su referencia y una fila por día de jornada completa.
                    </Text>
                    <List size="xs" mt={4}>
                      {tramos.map((t) => (
                        <List.Item key={t.referencia}>
                          <b>{t.referencia}</b>: del {formatFecha(t.desde)} al {formatFecha(t.hasta)}
                        </List.Item>
                      ))}
                    </List>
                  </>
                )}
              </Alert>
            )}

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
                    label="Ya la cargué a mano en Sirha7: aprobarla sin registrarla"
                    description="Para una cédula que Sirha7 no reconoce."
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
                    placeholder="Por ejemplo: cargada a mano en Sirha7 el 12/10/2026"
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
