'use client'

import { useEffect } from 'react'
import { Alert, Select, Stack, Text } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useRegularizacionTipoMutations } from '../hooks/useRegularizacionTipo'
import { reubicarContratoSchema, type ReubicarContratoFormData } from '../schemas/reubicarContrato.schema'
import type { ContratoPorRegularizar } from '../services/regularizacionTipoService'

interface Props {
  contrato: ContratoPorRegularizar | null
  onClose: () => void
}

/**
 * Pasa un ocasional o un profesional de un puesto de plantilla a uno de
 * contrato de su unidad (P9, PR 4). Solo se ofrecen los que no le cambian la
 * situación: la misma unidad, el mismo régimen y, si es ocasional, el mismo
 * grupo ocupacional. Por eso no es una acción de personal.
 */
export function ReubicarContratoModal({ contrato, onClose }: Props) {
  const contained = useContainedInput()
  const { reubicar } = useRegularizacionTipoMutations()

  const { control, handleSubmit, reset, setError, formState: { errors } } = useForm<ReubicarContratoFormData>({
    resolver: zodResolver(reubicarContratoSchema),
  })

  // Con un solo destino, ya viene elegido.
  useEffect(() => {
    if (!contrato) return
    reset(contrato.destinos.length === 1 ? { puesto_destino_id: contrato.destinos[0].id } : {})
  }, [contrato, reset])

  const enviar = ({ puesto_destino_id }: ReubicarContratoFormData) => {
    if (!contrato) return
    reubicar.mutateAsync({ contratoId: contrato.id, puestoDestinoId: puesto_destino_id })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['puesto_destino_id'], null))
  }

  const opciones = (contrato?.destinos ?? []).map((d) => ({ value: String(d.id), label: d.cargo ?? `Puesto ${d.id}` }))

  return (
    <FormModal
      opened={contrato !== null}
      onClose={onClose}
      title="Reubicar en un puesto de contrato"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Reubicar"
      submitting={reubicar.isPending}
    >
      <Stack gap="md">
        <Text size="sm">
          <b>{contrato?.servidor}</b> ({contrato?.modalidad}) está en «{contrato?.puesto?.cargo}», que es de
          plantilla.
        </Text>
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          Sigue en la misma unidad, con el mismo régimen y, si es ocasional, con el mismo grado: no
          cambia su situación, así que no lleva acción de personal. El cambio queda en la bitácora.
        </Alert>
        <Controller
          name="puesto_destino_id"
          control={control}
          render={({ field }) => (
            <Select
              label="Puesto de contrato"
              placeholder="Seleccione el puesto"
              data={opciones}
              allowDeselect={false}
              withAsterisk
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : undefined)}
              error={errors.puesto_destino_id?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
