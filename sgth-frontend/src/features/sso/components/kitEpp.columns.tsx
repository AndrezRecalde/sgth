import { Checkbox, NumberInput, Text } from '@mantine/core'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_KIT_LABELS, TONO_ESTADO_KIT } from '../constants/kitEpp'
import type { EntregaKitEppFormData } from '../schemas/entregaKitEpp.schema'
import type { DataTableColumn } from 'mantine-datatable'

/** Una fila del arreglo de campos, con su índice para dirigir al `Controller`. */
export type FilaKitEpp = EntregaKitEppFormData['equipos'][number] & {
  id: string
  indice: number
}

interface Opciones {
  control: Control<EntregaKitEppFormData>
  /** Los valores vigilados del arreglo: el `Checkbox` de una fila desactiva su cantidad. */
  equipos: EntregaKitEppFormData['equipos']
  errors: FieldErrors<EntregaKitEppFormData>
}

/**
 * Las filas del kit que se entrega, cada celda con su `Controller`.
 *
 * Era una lista de `Group` escritos a mano, con la cantidad en un
 * `NumberInput` que solo llevaba `aria-label` porque en una fila suelta no
 * había dónde poner la etiqueta. Son filas que se capturan, que es el caso
 * que la regla 06 manda resolver con `SgthTable` y un `Controller` por celda,
 * y así cada columna lleva su encabezado visible.
 *
 * Es una función y no una constante porque las celdas necesitan el `control`
 * del formulario, igual que `getConsolidadoColumns`.
 */
export function columnasKitEpp({ control, equipos, errors }: Opciones): DataTableColumn<FilaKitEpp>[] {
  return [
    {
      accessor: 'incluido',
      title: 'Entregar',
      width: 90,
      textAlign: 'center',
      render: ({ indice }) => (
        <Controller
          name={`equipos.${indice}.incluido`}
          control={control}
          render={({ field }) => (
            <Checkbox
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
              aria-label={`Entregar ${equipos[indice]?.nombre ?? ''}`}
            />
          )}
        />
      ),
    },
    {
      accessor: 'nombre',
      title: 'Equipo',
      render: ({ indice, nombre }) => (
        <Text size="sm">{equipos[indice]?.nombre ?? nombre}</Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 130,
      render: ({ indice }) => {
        const estado = equipos[indice]?.estado
        if (! estado) return '—'
        return (
          <StatusBadge tone={TONO_ESTADO_KIT[estado]}>
            {ESTADO_KIT_LABELS[estado]}
          </StatusBadge>
        )
      },
    },
    {
      accessor: 'reponerDesde',
      title: 'Vuelve a tocar',
      width: 140,
      // «Vigente» sin decir hasta cuándo no le sirve a quien está decidiendo
      // si entrega o no.
      render: ({ indice }) => {
        const fila = equipos[indice]
        // Sin estado es un equipo agregado a mano: no pertenece al kit del
        // puesto, así que no hay plazo del que hablar.
        if (! fila?.estado) return '—'
        if (fila.estado === 'pendiente') return <Text size="xs" c="dimmed">Nunca entregado</Text>
        if (! fila.reponerDesde) return <Text size="xs" c="dimmed">Sin plazo fijado</Text>
        return <Text size="sm">{formatFecha(fila.reponerDesde)}</Text>
      },
    },
    {
      accessor: 'cantidad',
      title: 'Cantidad',
      width: 110,
      render: ({ indice }) => (
        <Controller
          name={`equipos.${indice}.cantidad`}
          control={control}
          render={({ field }) => (
            <NumberInput
              aria-label={`Cantidad de ${equipos[indice]?.nombre ?? ''}`}
              min={1}
              hideControls
              w={80}
              disabled={! equipos[indice]?.incluido}
              value={field.value}
              onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
              error={errors.equipos?.[indice]?.cantidad?.message}
            />
          )}
        />
      ),
    },
  ]
}
