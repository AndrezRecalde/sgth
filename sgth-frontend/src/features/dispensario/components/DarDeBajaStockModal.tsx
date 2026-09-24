'use client'

import {
  Stack, NumberInput, Textarea, Select,
  Group, Text, Alert,
} from '@mantine/core'
import { FormModal, StatusBadge } from '@/components/ui'
import { useEffect } from 'react'
import { useForm, Controller, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconAlertTriangle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import {
  useInventarioMutations, useLotesDeMedicina,
} from '../hooks/useInventarioMedicina'
import type {
  InventarioMedicina, LoteMedicina,
} from '../services/inventarioMedicinaService'
import {
  bajaStockSchema, motivoDeBaja, CAUSAS_BAJA,
  type BajaStockFormData,
} from '../schemas/inventarioMovimiento.schema'
import { estaCaducado } from '../utils/caducidad'
import { formatFechaMes } from '@/lib/fecha'

interface Props {
  opened:   boolean
  onClose:  () => void
  medicina: InventarioMedicina | null
}

/** Referencia estable para cuando la consulta de lotes aún no ha respondido. */
const SIN_LOTES: LoteMedicina[] = []

/** ¿Caducó ya este lote? La regla vive en `utils/caducidad`, con el resto. */
const loteCaducado = (lote: LoteMedicina): boolean =>
  estaCaducado(lote.fecha_caducidad)

function etiquetaDeLote(lote: LoteMedicina): string {
  const nombre = lote.codigo_lote ?? 'Sin identificar'
  const caduca = lote.fecha_caducidad
    ? formatFechaMes(lote.fecha_caducidad)
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
  } = useForm<BajaStockFormData>({
    resolver: zodResolver(bajaStockSchema),
    // `causa` se omite: no hay una por defecto, y darle la cadena vacía
    // obligaría a mentir sobre el tipo del enum (regla 09).
    defaultValues: { lote_id: '', cantidad: 0, detalle: '' },
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
      // Se propone «Caducidad» solo si el lote lo está; si no, la causa se
      // omite en vez de ir vacía, que el enum ya no admite.
      ...(vencido ? { causa: 'Caducidad' as const } : {}),
      detalle:  '',
    })
  }, [opened, medicina, lotes, reset])

  const cantidad = useWatch({ control, name: 'cantidad' })
  const loteId   = useWatch({ control, name: 'lote_id' })

  const loteElegido = lotes.find(l => String(l.id) === loteId) ?? null
  const tope = loteElegido?.stock_actual ?? medicina?.stock_actual ?? 0

  const onSubmit = (values: BajaStockFormData) => {
    if (!medicina) return
    const motivo = motivoDeBaja(values.causa, values.detalle)

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
    <FormModal
      opened={opened}
      onClose={() => { reset(); onClose() }}
      title="Dar de baja existencias"
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Dar de baja"
      submitting={registrarBaja.isPending}
      destructiva
    >
      <Stack gap="sm">
        <Group justify="space-between">
          <Text size="sm" fw={600}>{medicina.nombre}</Text>
          <StatusBadge>
            Stock: {medicina.stock_actual}
          </StatusBadge>
        </Group>

        <Alert
          icon={<IconAlertTriangle size={14} />}
          color={caducado ? 'red' : 'amber'}
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
          render={({ field }) => (
            <NumberInput
              label="Unidades a dar de baja"
              min={1}
              max={tope}
              allowDecimal={false}
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
          render={({ field }) => (
            <Select
              label="Causa"
              placeholder="Seleccione"
              data={CAUSAS_BAJA}
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
          // El tope del motivo se comprueba sobre causa + detalle, y el aviso
          // sale aquí: sin este `error` la validación bloqueaba el envío en
          // silencio, que es peor que dejar pasar el 422.
          error={errors.detalle?.message}
        />
      </Stack>
    </FormModal>
  )
}
