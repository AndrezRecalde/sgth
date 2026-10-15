'use client'

import { Grid, Group, Radio, Select, Stack, Text } from '@mantine/core'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { plural } from '@/lib/plural'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { FORMATOS_IMPRESION } from '../constants/credenciales'
import { useMutacionesCredencial } from '../hooks/useCredenciales'
import { imprimirCredencialesSchema, type ImprimirCredencialesFormData } from '../schemas/credencial.schema'
import type { ServidorConCredencial } from '../services/tiposCredencial'

/**
 * Qué se imprime: unas credenciales elegidas en la tabla, o todas las
 * vigentes con foto de una unidad y de las que cuelgan de ella.
 */
export type PedidoDeImpresion =
  | { modo: 'seleccion'; servidores: ServidorConCredencial[] }
  | { modo: 'unidad'; unidadId?: number }

interface Props {
  pedido: PedidoDeImpresion | null
  onClose: () => void
  /** Ya salió el PDF: la selección de la tabla se vacía. */
  onImpreso: () => void
}

/** Hasta 90 a la vez: el tope del backend (ImprimirCredencialesRequest::MAXIMO). */
export const MAXIMO_POR_IMPRESION = 90

/**
 * Elegir el formato y, si es por unidad, la unidad (docs/diseno-comunicacion-social.md,
 * 6.2; D7). El PDF se abre en otra pestaña para imprimirlo, y cada tarjeta
 * queda registrada con lo que lleva.
 */
export function ImprimirCredencialesModal({ pedido, onClose, onImpreso }: Props) {
  const contained = useContainedInput()
  const { imprimir } = useMutacionesCredencial()
  const { data: unidades = [] } = useTodasUnidades()
  const porUnidad = pedido?.modo === 'unidad'

  const { control, handleSubmit, reset, setError, formState: { errors } } = useForm<ImprimirCredencialesFormData>({
    resolver: zodResolver(imprimirCredencialesSchema),
    values: {
      formato: 'cr80',
      unidad_administrativa_id: pedido?.modo === 'unidad' && pedido.unidadId ? String(pedido.unidadId) : null,
    },
  })

  const cerrar = () => {
    reset()
    onClose()
  }

  const enviar = (valores: ImprimirCredencialesFormData) => {
    if (!pedido) return

    if (pedido.modo === 'unidad' && !valores.unidad_administrativa_id) {
      setError('unidad_administrativa_id', { type: 'manual', message: 'Elija la unidad' })
      return
    }

    const solicitud = pedido.modo === 'unidad'
      ? { formato: valores.formato, unidad_administrativa_id: Number(valores.unidad_administrativa_id) }
      : { formato: valores.formato, credenciales: pedido.servidores.flatMap((s) => (s.credencial ? [s.credencial.id] : [])) }

    imprimir.mutateAsync(solicitud).then(() => {
      onImpreso()
      cerrar()
    }).catch(() => undefined) // Lo notifica el hook.
  }

  const cuantas = pedido?.modo === 'seleccion' ? pedido.servidores.length : 0

  return (
    <FormModal
      opened={!!pedido}
      onClose={cerrar}
      title={porUnidad ? 'Imprimir las credenciales de una unidad' : `Imprimir ${plural(cuantas, 'credencial', 'credenciales')}`}
      onSubmit={handleSubmit(enviar)}
      submitLabel="Preparar el PDF"
      submitting={imprimir.isPending}
      size="lg"
    >
      <Stack gap="md">
        {porUnidad ? (
          <Controller
            name="unidad_administrativa_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Unidad"
                placeholder="Elija la unidad"
                description={`Las vigentes con foto de la unidad y de las que cuelgan de ella; hasta ${MAXIMO_POR_IMPRESION} a la vez.`}
                data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? '' }))}
                searchable
                {...contained}
                value={field.value}
                onChange={field.onChange}
                error={errors.unidad_administrativa_id?.message}
              />
            )}
          />
        ) : (
          <Text size="sm" c="dimmed">
            {pedido?.modo === 'seleccion' && pedido.servidores.map((s) => s.nombre_completo).join(', ')}
          </Text>
        )}

        <Controller
          name="formato"
          control={control}
          render={({ field }) => (
            <Radio.Group label="Formato" value={field.value} onChange={field.onChange}>
              <Grid mt={4}>
                {FORMATOS_IMPRESION.map((f) => (
                  <Grid.Col key={f.value} span={{ base: 12, sm: 6 }}>
                    <Radio.Card value={f.value} radius="md" p="sm" h="100%">
                      <Group wrap="nowrap" align="flex-start" gap="sm">
                        <Radio.Indicator mt={2} />
                        <div>
                          <Text size="sm" fw={500}>{f.label}</Text>
                          <Text size="xs" c="dimmed">{f.descripcion}</Text>
                        </div>
                      </Group>
                    </Radio.Card>
                  </Grid.Col>
                ))}
              </Grid>
            </Radio.Group>
          )}
        />

        <Text size="xs" c="dimmed">
          Al preparar el PDF queda registrado lo que lleva cada tarjeta. Si después cambia el cargo, la unidad, la foto o
          el contacto de emergencia, la tarjeta aparecerá para reimprimir.
        </Text>
      </Stack>
    </FormModal>
  )
}
