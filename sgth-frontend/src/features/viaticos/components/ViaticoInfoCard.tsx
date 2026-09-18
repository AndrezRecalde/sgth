'use client'

import { Button } from '@mantine/core'
import { IconPencil } from '@tabler/icons-react'
import { DetailList, SectionCard, type DetailItem } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import { ZONA_LABELS } from '../constants/viatico.constants'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico: ViaticoConRelaciones
  puedeEditar: boolean
  onEditar: () => void
}

/*
| Quién viaja, a dónde, cuándo y para qué.
|
| Con `DetailList` la etiqueta va sobre el valor. Antes iban en una fila
| etiqueta-valor y un cargo largo bajaba a la línea siguiente, debajo de la
| etiqueta que venía después.
*/
export function ViaticoInfoCard({ viatico: d, puedeEditar, onEditar }: Props) {
  const s = d.servidor
  const noches = Number(d.noches ?? 0)

  const items: DetailItem[] = [
    { label: 'Servidor', value: [s?.nombre, s?.apellido].filter(Boolean).join(' ') },
    { label: 'Cargo', value: s?.puesto?.cargo?.nombre },
    { label: 'Unidad', value: s?.puesto?.unidad_administrativa?.nombre, ancho: true },
    { label: 'Zona', value: ZONA_LABELS[d.zona ?? ''] ?? d.zona },
    { label: 'Noches', value: `${noches} ${noches === 1 ? 'noche' : 'noches'}` },
    { label: 'Salida', value: formatFechaHora(d.datetime_salida) },
    { label: 'Regreso', value: formatFechaHora(d.datetime_llegada) },
  ]

  if (d.zona === 'exterior') {
    items.push(
      { label: 'País de destino', value: d.pais_destino as string | null },
      {
        label: 'Coeficiente',
        value: d.coeficiente_exterior ? Number(d.coeficiente_exterior).toFixed(4) : 'Lo fija Financiero al aprobar',
      },
    )
  }

  items.push({ label: 'Justificación', value: d.justificacion, ancho: true })

  return (
    <SectionCard
      title="Datos del viaje"
      actions={
        puedeEditar && (
          <Button variant="subtle" size="xs" leftSection={<IconPencil size={14} />} onClick={onEditar}>
            Editar datos
          </Button>
        )
      }
    >
      <DetailList items={items} />
    </SectionCard>
  )
}
