'use client'

import { Button, Center, Group, Paper, Stack, Text } from '@mantine/core'
import { IconHistory, IconUpload } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { FONDOS_CREDENCIAL, REGLAS_VARIANTE } from '../constants/identidad'
import type { EstadoVariante } from '../services/tiposIdentidad'
import { VistaVersion } from './VistaVersion'

interface Props {
  variante: EstadoVariante
  /** La que se ve hoy si esta variante no tiene versión propia (su respaldo). */
  enUso: EstadoVariante | null
  onSubir: () => void
  onHistorial: () => void
}

/** Una variante del logo: la vigente, desde cuándo, lo programado y dónde se usa. */
export function VarianteCard({ variante, enUso, onSubir, onHistorial }: Props) {
  const { vigente, programadas } = variante
  const regla = REGLAS_VARIANTE[variante.variante]
  // Las de página entera se ven más altas: si no, no se distingue nada.
  const esFondo = FONDOS_CREDENCIAL.includes(variante.variante)
  const alto = variante.variante === 'hoja_membretada' || esFondo ? 150 : 64
  const prestada = !vigente && enUso?.vigente

  return (
    <Paper withBorder radius="md" p="md">
      <Stack gap="sm" h="100%">
        {/* Un título largo se parte en dos líneas; la etiqueta no se corta. */}
        <Group justify="space-between" wrap="nowrap" align="flex-start">
          <Text fw={600} style={{ minWidth: 0 }}>{variante.etiqueta}</Text>
          <StatusBadge size="xs" style={{ flexShrink: 0 }}>{variante.obligatoria ? 'Obligatoria' : 'Opcional'}</StatusBadge>
        </Group>

        {vigente ? (
          <VistaVersion versionId={vigente.id} alt={variante.etiqueta} alto={alto} oscuro={regla.fondoOscuro} />
        ) : prestada ? (
          <VistaVersion versionId={prestada.id} alt={prestada.variante_etiqueta} alto={alto} oscuro={regla.fondoOscuro} atenuada />
        ) : (
          <Center h={alto + 24}>
            <Text size="sm" c="dimmed" ta="center">
              {esFondo ? 'Sin cargar: la cara queda en blanco, con su franja.' : 'Sin cargar: no se muestra.'}
            </Text>
          </Center>
        )}

        <div>
          {vigente ? (
            <Text size="xs" c="dimmed">
              Vigente desde el {formatFecha(vigente.vigente_desde)} · {vigente.ancho} × {vigente.alto} px
            </Text>
          ) : prestada ? (
            <Text size="xs" c="dimmed">Sin versión propia: se usa el {prestada.variante_etiqueta.toLowerCase()}.</Text>
          ) : null}
          {programadas.length > 0 && (
            <StatusBadge tone="info" size="xs" mt={6}>
              {programadas.length === 1
                ? `Programada desde el ${formatFecha(programadas[0].vigente_desde)}`
                : `${programadas.length} programadas`}
            </StatusBadge>
          )}
        </div>

        <Text size="xs" c="dimmed">{variante.usos}. {variante.requisito}.</Text>

        <Group gap="xs" mt="auto">
          <Button variant="light" size="xs" leftSection={<IconUpload size={14} />} onClick={onSubir}>
            Subir versión
          </Button>
          <Button variant="subtle" size="xs" leftSection={<IconHistory size={14} />} onClick={onHistorial}>
            Historial
          </Button>
        </Group>
      </Stack>
    </Paper>
  )
}
