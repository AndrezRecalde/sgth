'use client'

import { Button, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui'
import { useCondicionCargaMutations } from '../hooks/useCondicionCargaMutations'
import {
  getDiscapacidadCargaColumns, getEnfermedadCargaColumns,
} from './condicionCarga.columns'
import { DiscapacidadCargaFamiliarModal } from './DiscapacidadCargaFamiliarModal'
import { EnfermedadCargaFamiliarModal } from './EnfermedadCargaFamiliarModal'
import type { CargaFamiliar } from '@/types/api'

interface Props {
  carga: CargaFamiliar
  servidorId: number
}

/**
 * El detalle de las condiciones de un familiar, que se despliega bajo su fila.
 * Solo aparecen los bloques que la carga declara: sin discapacidad marcada no
 * hay nada que registrar ahí.
 */
export function CargaFamiliarCondiciones({ carga, servidorId }: Props) {
  const [discOpened, { open: abrirDisc, close: cerrarDisc }] = useDisclosure(false)
  const [enfOpened, { open: abrirEnf, close: cerrarEnf }] = useDisclosure(false)
  const { eliminarDiscapacidad, eliminarEnfermedad } =
    useCondicionCargaMutations(servidorId, Number(carga.id))

  const discapacidades = carga.discapacidades ?? []
  const enfermedades = carga.enfermedades_catastroficas ?? []

  const discColumns = getDiscapacidadCargaColumns({
    onEliminar: (id) => eliminarDiscapacidad.mutate(id),
  })

  const enfColumns = getEnfermedadCargaColumns({
    onEliminar: (id) => eliminarEnfermedad.mutate(id),
  })

  return (
    <Stack gap="md" p="md">
      {carga.persona_con_discapacidad && (
        <Stack gap="xs">
          <Group justify="space-between">
            <Text size="xs" fw={600} tt="uppercase" c="dimmed">Discapacidades</Text>
            <Button size="xs" variant="subtle"
              leftSection={<IconPlus size={12} />} onClick={abrirDisc}>
              Agregar
            </Button>
          </Group>
          <SgthTable
            records={discapacidades}
            columns={discColumns}
            minHeight={60}
            noRecordsText="Sin discapacidades registradas aún."
          />
        </Stack>
      )}

      {carga.posee_enfermedad_catastrofica && (
        <Stack gap="xs">
          <Group justify="space-between">
            <Text size="xs" fw={600} tt="uppercase" c="dimmed">Enfermedades catastróficas</Text>
            <Button size="xs" variant="subtle"
              leftSection={<IconPlus size={12} />} onClick={abrirEnf}>
              Agregar
            </Button>
          </Group>
          <SgthTable
            records={enfermedades}
            columns={enfColumns}
            minHeight={60}
            noRecordsText="Sin enfermedades registradas aún."
          />
        </Stack>
      )}

      <DiscapacidadCargaFamiliarModal
        opened={discOpened}
        onClose={cerrarDisc}
        cargaId={Number(carga.id)}
        servidorId={servidorId}
      />
      <EnfermedadCargaFamiliarModal
        opened={enfOpened}
        onClose={cerrarEnf}
        cargaId={Number(carga.id)}
        servidorId={servidorId}
      />
    </Stack>
  )
}
