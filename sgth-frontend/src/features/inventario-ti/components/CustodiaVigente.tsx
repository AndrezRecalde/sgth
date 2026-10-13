'use client'

import { Button, Group, Text } from '@mantine/core'
import { IconArrowBackUp, IconFileText, IconHandGrab, IconPaperclip, IconSwitchHorizontal } from '@tabler/icons-react'
import { DetailList, SectionCard } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { Asignacion, BienInformatico } from '../services/tipos'

interface Props {
  bien: BienInformatico
  vigente: Asignacion | null
  puedeGestionar: boolean
  onEntregar: () => void
  onTraspasar: (a: Asignacion) => void
  onDevolver: (a: Asignacion) => void
  onMemorando: (a: Asignacion) => void
  onActa: (a: Asignacion) => void
}

/**
 * Quién tiene el equipo hoy y lo que se puede hacer con su custodia. Uno de
 * baja, extraviado o del grupo de préstamo no se entrega: la tarjeta dice por
 * qué en vez de ofrecer un botón que el backend rechazaría.
 */
export function CustodiaVigente({ bien, vigente, puedeGestionar, onEntregar, onTraspasar, onDevolver, onMemorando, onActa }: Props) {
  const motivoNoEntregable = bien.estado_operativo === 'dado_de_baja' || bien.estado_operativo === 'extraviado'
    ? `El equipo está ${bien.estado_etiqueta.toLowerCase()}: no se entrega.`
    : bien.prestable ? 'Es un equipo de préstamo: se presta, no se entrega en custodia.' : null

  if (!vigente) {
    return (
      <SectionCard
        title="Custodia vigente"
        actions={puedeGestionar && !motivoNoEntregable
          ? <Button variant="light" leftSection={<IconHandGrab size={16} />} onClick={onEntregar}>Entregar en custodia</Button>
          : undefined}
      >
        <Text size="sm" c="dimmed">{motivoNoEntregable ?? 'Nadie tiene este equipo: está en TIC.'}</Text>
      </SectionCard>
    )
  }

  return (
    <SectionCard
      title="Custodia vigente"
      actions={
        <Group gap="xs">
          <Button variant="subtle" leftSection={<IconFileText size={16} />} onClick={() => onActa(vigente)}>Acta</Button>
          {puedeGestionar && (
            <>
              <Button variant="subtle" leftSection={<IconPaperclip size={16} />} onClick={() => onMemorando(vigente)}>
                {vigente.tiene_memorando_pdf ? 'Reemplazar memorando' : 'Adjuntar memorando'}
              </Button>
              <Button variant="light" leftSection={<IconSwitchHorizontal size={16} />} onClick={() => onTraspasar(vigente)}>Traspasar</Button>
              <Button variant="light" leftSection={<IconArrowBackUp size={16} />} onClick={() => onDevolver(vigente)}>Devolver</Button>
            </>
          )}
        </Group>
      }
    >
      <DetailList columnas={3} items={[
        { label: 'Custodio', value: vigente.custodio ? `${vigente.custodio.nombre} (${vigente.custodio.cedula})` : null },
        { label: 'Unidad', value: vigente.unidad?.nombre },
        { label: 'Desde', value: vigente.fecha_asignacion ? formatFecha(vigente.fecha_asignacion) : null },
        { label: 'Usuario del equipo', value: vigente.usuario?.nombre ?? 'El mismo custodio' },
        { label: 'Memorando', value: vigente.memorando_numero },
        { label: 'Entregó', value: vigente.registrada_por },
      ]} />
    </SectionCard>
  )
}
