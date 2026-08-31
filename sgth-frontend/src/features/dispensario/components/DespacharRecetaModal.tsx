'use client'

import {
  Modal, Stack, Text, Group, Badge,
  NumberInput, Button, Alert, Card,
  ThemeIcon,
} from '@mantine/core'
import {
  IconPill, IconCheck,
} from '@tabler/icons-react'
import { useState, useMemo } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useDespacharReceta } from '../hooks/useReceta'
import type { RecetaMedica } from '../services/recetaService'

interface Props {
  opened:  boolean
  onClose: () => void
  receta:  RecetaMedica | null
}

function getNombrePaciente(receta: RecetaMedica): string {
  const historia = receta.consulta_medica?.historia_clinica
  if (historia?.servidor) {
    return `${historia.servidor.nombre} ${historia.servidor.apellido}`
  }
  if (historia?.carga_familiar) {
    return `${historia.carga_familiar.nombres} ${historia.carga_familiar.apellidos}`
  }
  return '—'
}

const ESTADO_ITEM: Record<string, { label: string; color: string }> = {
  pendiente:         { label: 'Pendiente',  color: 'gray'   },
  despachado_parcial:{ label: 'Parcial',    color: 'orange' },
  despachado_completo:{label: 'Completo',   color: 'emerald'},
}

export function DespacharRecetaModal({
  opened, onClose, receta,
}: Props) {
  const contained  = useContainedInput()
  const despachar  = useDespacharReceta()

  // Por defecto se propone despachar todo lo que falta de cada ítem. Se
  // calcula a partir de la receta en lugar de sembrarlo con un efecto.
  const cantidadesIniciales = useMemo(() => {
    const init: Record<number, number> = {}
    receta?.items.forEach(item => {
      const faltante = item.cantidad_prescrita -
        (item.cantidad_despachada ?? 0)
      init[item.id!] = faltante > 0 ? faltante : 0
    })
    return init
  }, [receta])

  // `edicion` es null mientras el farmacéutico no cambie ninguna cantidad.
  const [edicion, setEdicion] =
    useState<Record<number, number> | null>(null)
  const cantidades = edicion ?? cantidadesIniciales

  if (!receta) return null

  const nombrePaciente = getNombrePaciente(receta)
  const itemsPendientes = receta.items.filter(
    item => item.estado !== 'despachado_completo'
  )

  const handleDespachar = () => {
    const items = itemsPendientes
      .filter(item => (cantidades[item.id!] ?? 0) > 0)
      .map(item => ({
        item_receta_id: item.id!,
        cantidad:       cantidades[item.id!] ?? 0,
      }))

    if (items.length === 0) return

    despachar.mutate(
      { id: receta.id, data: { items } },
      { onSuccess: () => {
        setEdicion(null)
        onClose()
      }}
    )
  }

  const handleClose = () => {
    setEdicion(null)
    onClose()
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Despachar receta médica"
      size="lg"
      radius="xl"
    >
      <Stack gap="sm">
        <Card withBorder radius="md" p="sm">
          <Group justify="space-between">
            <Stack gap={0}>
              <Text size="sm" fw={600}>{nombrePaciente}</Text>
              <Text size="xs" c="dimmed" ff="monospace">
                Receta emitida:{' '}
                {new Date(receta.fecha_emision).toLocaleDateString('es-EC', {
                  day: '2-digit', month: 'short', year: 'numeric',
                })}
              </Text>
            </Stack>
            <Badge
              variant="light"
              color={receta.estado === 'pendiente' ? 'orange' : 'blue'}
            >
              {receta.estado.replace(/_/g, ' ')}
            </Badge>
          </Group>
          {receta.indicaciones_generales && (
            <Text size="xs" c="dimmed" mt="xs">
              {receta.indicaciones_generales}
            </Text>
          )}
        </Card>

        <Text size="sm" fw={500}>
          Medicamentos a despachar
        </Text>

        {itemsPendientes.length === 0 ? (
          <Alert color="emerald" variant="light">
            <Text size="sm">
              Todos los ítems de esta receta ya fueron despachados.
            </Text>
          </Alert>
        ) : (
          <Stack gap="sm">
            {itemsPendientes.map((item) => {
              const faltante = item.cantidad_prescrita -
                (item.cantidad_despachada ?? 0)
              const estadoItem = ESTADO_ITEM[item.estado ?? 'pendiente']
                ?? { label: item.estado, color: 'gray' }

              return (
                <Card key={item.id} withBorder radius="md" p="sm">
                  <Stack gap="xs">
                    <Group justify="space-between" wrap="nowrap">
                      <Group gap="xs" wrap="nowrap">
                        <ThemeIcon
                          size="sm" color="blue" variant="light"
                        >
                          <IconPill size={12} />
                        </ThemeIcon>
                        <Stack gap={0}>
                          <Text size="sm" fw={500}>
                            {item.inventario?.nombre ?? '—'}
                          </Text>
                          <Text size="xs" c="dimmed">
                            {item.inventario?.concentracion ?? ''}
                            {' · '}
                            {item.dosis} · {item.frecuencia}
                          </Text>
                        </Stack>
                      </Group>
                      <Badge
                        size="xs"
                        variant="light"
                        color={estadoItem.color}
                      >
                        {estadoItem.label}
                      </Badge>
                    </Group>

                    <Group grow align="flex-end" gap="sm">
                      <Stack gap={2}>
                        <Text size="xs" c="dimmed">Prescrito</Text>
                        <Text size="sm" fw={500}>
                          {item.cantidad_prescrita}
                        </Text>
                      </Stack>
                      <Stack gap={2}>
                        <Text size="xs" c="dimmed">Ya despachado</Text>
                        <Text size="sm">
                          {item.cantidad_despachada ?? 0}
                        </Text>
                      </Stack>
                      <Stack gap={2}>
                        <Text size="xs" c="dimmed">Faltante</Text>
                        <Text size="sm" fw={500} c="orange">
                          {faltante}
                        </Text>
                      </Stack>
                      <NumberInput
                        label="Cantidad a despachar"
                        size="xs"
                        min={0}
                        max={faltante}
                        {...contained}
                        value={cantidades[item.id!] ?? 0}
                        onChange={(v) => setEdicion(prev => ({
                          ...(prev ?? cantidadesIniciales),
                          [item.id!]: Math.min(Number(v) || 0, faltante),
                        }))}
                      />
                    </Group>
                  </Stack>
                </Card>
              )
            })}
          </Stack>
        )}

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Button
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={despachar.isPending}
            disabled={
              itemsPendientes.length === 0 ||
              itemsPendientes.every(
                item => (cantidades[item.id!] ?? 0) === 0
              )
            }
            onClick={handleDespachar}
          >
            Confirmar despacho
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
