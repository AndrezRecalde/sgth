'use client'

import {
  Modal, Stack, NumberInput, Textarea, Select,
  Button, Group, Text, Badge, Alert,
} from '@mantine/core'
import { useEffect } from 'react'
import { useForm, Controller, useWatch } from 'react-hook-form'
import { IconCheck, IconAlertTriangle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useInventarioMutations, useLotesDeMedicina,
} from '../hooks/useInventarioMedicina'
import type {
  InventarioMedicina, LoteMedicina,
} from '../services/inventarioMedicinaService'

interface Props {
  opened:   boolean
  onClose:  () => void
  medicina: InventarioMedicina | null
}

type FormData = {
  lote_id:  string
  cantidad: number
  causa:    string
  detalle:  string
}

/** Referencia estable para cuando la consulta de lotes aún no ha respondido. */
const SIN_LOTES: LoteMedicina[] = []

const CAUSAS = [
  { value: 'Caducidad',     label: 'Caducidad'                  },
  { value: 'Merma',         label: 'Merma'                      },
  { value: 'Rotura',        label: 'Rotura o envase dañado'     },
  { value: 'Contaminación', label: 'Contaminación'              },
  { value: 'Otra',          label: 'Otra'                       },
]

/** ¿Caducó ya este lote? El día impreso en el envase todavía es válido. */
function loteCaducado(lote: LoteMedicina): boolean {
  if (!lote.fecha_caducidad) return false
  const [y, m, d] = lote.fecha_caducidad.slice(0, 10).split('-').map(Number)
  const caduca = new Date(y, m - 1, d)
  const hoy = new Date()
  hoy.setHours(0, 0, 0, 0)
  return caduca < hoy
}

function etiquetaDeLote(lote: LoteMedicina): string {
  const nombre = lote.codigo_lote ?? 'Sin identificar'
  const caduca = lote.fecha_caducidad
    ? new Date(lote.fecha_caducidad).toLocaleDateString('es-EC', {
        day: '2-digit', month: 'short', year: 'numeric',
      })
    : 'sin fecha'

  return `${nombre} · ${caduca} · ${lote.stock_actual} unid.`
    + (loteCaducado(lote) ? ' · VENCIDO' : '')
}

export function DarDeBajaStockModal({ opened, onClose, medicina }: Props) {
  const contained = useContainedInput()
  const { registrarBaja } = useInventarioMutations()

  // El array por defecto es una constante y no un literal: `= []` en el
  // destructurado crea una referencia nueva en cada render, y como `lotes` es
  // dependencia del efecto que resiembra el formulario, mientras la consulta
  // cargaba el efecto se disparaba sin parar —«Maximum update depth exceeded»—.
  const { data } = useLotesDeMedicina(medicina?.id ?? null, opened)
  const lotes = data ?? SIN_LOTES

  const {
    control, register, handleSubmit, reset, setValue,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: { lote_id: '', cantidad: 0, causa: '', detalle: '' },
  })

  // Se propone el primero en salir por FEFO, que con lo vencido delante es
  // justo lo que trae aquí a casi todo el mundo, y su lote entero.
  useEffect(() => {
    if (!opened || !medicina) return

    const primero = lotes[0]
    const vencido = primero ? loteCaducado(primero) : false

    reset({
      lote_id:  primero ? String(primero.id) : '',
      cantidad: vencido ? primero!.stock_actual : 0,
      causa:    vencido ? 'Caducidad' : '',
      detalle:  '',
    })
  }, [opened, medicina, lotes, reset])

  const cantidad = useWatch({ control, name: 'cantidad' })
  const loteId   = useWatch({ control, name: 'lote_id' })

  const loteElegido = lotes.find(l => String(l.id) === loteId) ?? null
  const tope = loteElegido?.stock_actual ?? medicina?.stock_actual ?? 0

  const onSubmit = (values: FormData) => {
    if (!medicina) return
    const motivo = values.detalle.trim()
      ? `${values.causa} — ${values.detalle.trim()}`
      : values.causa

    registrarBaja.mutateAsync({
      id: medicina.id,
      cantidad: values.cantidad,
      motivo,
      loteId: values.lote_id ? Number(values.lote_id) : null,
    }).then(() => {
      reset()
      onClose()
    }).catch(() => {})
  }

  if (!medicina) return null

  const caducado = loteElegido ? loteCaducado(loteElegido) : false

  return (
    <Modal
      opened={opened}
      onClose={() => { reset(); onClose() }}
      title="Dar de baja existencias"
      size="sm"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="sm">
          <Group justify="space-between">
            <Text size="sm" fw={600}>{medicina.nombre}</Text>
            <Badge variant="light" color="blue">
              Stock: {medicina.stock_actual}
            </Badge>
          </Group>

          <Alert
            icon={<IconAlertTriangle size={14} />}
            color={caducado ? 'red' : 'orange'}
            variant="light"
          >
            <Text size="xs">
              {caducado
                ? 'Este lote está caducado y el despacho lo rechaza. Al darlo de baja sale del inventario y queda constancia en el kardex.'
                : 'Las unidades salen del inventario por una causa conocida y queda constancia en el kardex. Para corregir una diferencia de conteo use «Ajustar inventario».'}
            </Text>
          </Alert>

          {/* De qué lote sale. Una caja rota o un lote que retira el
              fabricante son de uno concreto, y hacerlo salir por el más
              próximo a caducar anotaría una mentira en el kardex. */}
          <Controller
            name="lote_id"
            control={control}
            rules={{ required: 'Indique de qué lote salen' }}
            render={({ field }) => (
              <Select
                label="Lote"
                placeholder={lotes.length ? 'Seleccione' : 'Sin existencias'}
                data={lotes.map(l => ({
                  value: String(l.id),
                  label: etiquetaDeLote(l),
                }))}
                disabled={lotes.length === 0}
                required
                {...contained}
                value={field.value}
                onChange={(v) => {
                  field.onChange(v ?? '')

                  // La cantidad se reajusta al lote nuevo. Sin esto quedaba la
                  // del lote anterior —ochenta unidades para un lote de
                  // veinticinco—, que el formulario mostraba como válida y el
                  // servidor rechazaba después.
                  const nuevo = lotes.find(l => String(l.id) === v)
                  if (!nuevo) return

                  setValue(
                    'cantidad',
                    loteCaducado(nuevo)
                      ? nuevo.stock_actual
                      : Math.min(cantidad, nuevo.stock_actual),
                    { shouldValidate: true }
                  )
                }}
                error={errors.lote_id?.message}
              />
            )}
          />

          <Controller
            name="cantidad"
            control={control}
            rules={{
              required: 'Indique cuántas unidades salen',
              min: { value: 1, message: 'Debe ser al menos 1' },
              max: {
                value: tope,
                message: `El lote tiene ${tope} unidades`,
              },
            }}
            render={({ field }) => (
              <NumberInput
                label="Unidades a dar de baja"
                min={1}
                max={tope}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(Number(v) || 0)}
                error={errors.cantidad?.message}
              />
            )}
          />

          {cantidad > 0 && cantidad <= tope && (
            <Text size="xs" c="dimmed">
              El lote quedará en {tope - cantidad} y el stock total en{' '}
              {medicina.stock_actual - cantidad}.
            </Text>
          )}

          <Controller
            name="causa"
            control={control}
            rules={{ required: 'Seleccione la causa' }}
            render={({ field }) => (
              <Select
                label="Causa"
                placeholder="Seleccione"
                data={CAUSAS}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.causa?.message}
              />
            )}
          />

          <Textarea
            label="Detalle (opcional)"
            placeholder="Ej: lote L123 vencido el 12/08/2026"
            autosize
            minRows={2}
            {...contained}
            {...register('detalle')}
          />

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="orange"
              leftSection={<IconCheck size={14} />}
              loading={registrarBaja.isPending}
            >
              Dar de baja
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
