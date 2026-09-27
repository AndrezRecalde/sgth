'use client'

import { Alert, Stack, Text, TextInput } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { ModalFooter, SgthModal } from '@/components/ui'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import type { MovimientoPersonal } from '@/types/api'

/*
| Un solo campo, pero con el estándar del proyecto (regla 07): validaba a mano
| con `useState` y, al no haber `<form>`, la tecla Intro no enviaba —había que
| alcanzar el botón con el ratón o con el tabulador—.
*/
const schema = z.object({
  referencia: z
    .string()
    .trim()
    .min(3, 'Escriba la referencia del dictamen — es el respaldo del compromiso')
    .max(255, 'No puede exceder los 255 caracteres'),
})

type FormData = z.infer<typeof schema>

/**
 * Referencia del dictamen presupuestario, pedida en el acto de suscribir.
 *
 * El Art. 105 de la LOSEP no deja comprometer presupuesto sin certificación
 * previa, y el backend lo hace valer: sin esta referencia la transición a
 * Suscrita se rechaza. Antes no había dónde escribirla, así que ninguna acción
 * con efecto económico —subrogación, incremento de remuneración— podía
 * suscribirse desde la aplicación.
 *
 * Es un dato de respaldo, no un cálculo: quien certifica es la Dirección
 * Financiera y lo que se guarda aquí es el número de su oficio o memorando,
 * para que el documento diga contra qué certificación se autorizó.
 */
interface Props {
  opened: boolean
  onClose: () => void
  movimiento: MovimientoPersonal | null
}

function dinero(v?: string | number | null): string | null {
  return v != null ? `$ ${Number(v).toFixed(2)}` : null
}

export function DictamenPresupuestarioModal({ opened, onClose, movimiento }: Props) {
  const contained = useContainedInput()
  const { transicionar } = useMovimientoMutations()

  const {
    register, handleSubmit, reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { referencia: '' },
  })

  const cerrar = () => {
    reset({ referencia: '' })
    onClose()
  }

  const suscribir = ({ referencia }: FormData) => {
    if (!movimiento) return

    transicionar.mutate(
      {
        id: Number(movimiento.id),
        estado: 'suscrita',
        dictamen_presupuestario_ref: referencia,
      },
      { onSuccess: cerrar },
    )
  }

  // El monto que se compromete: la diferencia en una subrogación, la
  // remuneración propuesta en el resto. Se muestra para que quien suscribe vea
  // contra qué cifra está exigiendo la certificación.
  const origen    = movimiento?.remuneracion_origen
  const propuesta = movimiento?.remuneracion_propuesta
  const esSubrogacion = movimiento?.tipo_movimiento === 'subrogacion'

  const comprometido = esSubrogacion && origen != null && propuesta != null
    ? Number(propuesta) - Number(origen)
    : propuesta != null ? Number(propuesta) : null

  return (
    <SgthModal
      opened={opened}
      onClose={cerrar}
      title="Suscribir con dictamen presupuestario"
      size="md"
    >
      <form onSubmit={handleSubmit(suscribir)} noValidate>
        <Stack gap="md">
          <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
            Esta acción compromete presupuesto, así que no puede suscribirse sin la
            certificación previa de la Dirección Financiera (Art. 105 LOSEP).
          </Alert>

          {comprometido != null && comprometido > 0 && (
            <div>
              <Text size="xs" fw={600} c="dimmed" tt="uppercase">
                {esSubrogacion ? 'Diferencia mensual a pagar' : 'Remuneración mensual'}
              </Text>
              <Text size="lg" fw={700} c="emerald">{dinero(comprometido)}</Text>
            </div>
          )}

          <TextInput
            label="N.º de dictamen o certificación presupuestaria"
            placeholder="Ej. DF-CP-2026-0142"
            description="Oficio o memorando con el que la Dirección Financiera certificó la disponibilidad."
            error={errors.referencia?.message}
            data-autofocus
            {...contained}
            {...register('referencia')}
          />

          <ModalFooter
            onCancel={cerrar}
            submitLabel="Suscribir"
            submitting={transicionar.isPending}
          />
        </Stack>
      </form>
    </SgthModal>
  )
}
