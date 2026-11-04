'use client'

import { Card, Group, Stack, Tabs } from '@mantine/core'
import { IconList, IconStars } from '@tabler/icons-react'
import { SgthDrawer, StatusBadge } from '@/components/ui'
import type { PuestoConRelaciones } from '@/types/api'
import { complejidadEnFemenino } from '../constants/perfilPuesto'
import { PuestoActividadesPanel } from './PuestoActividadesPanel'
import { PuestoCompetenciasPanel } from './PuestoCompetenciasPanel'

interface Props {
  opened:  boolean
  onClose: () => void
  puesto:  PuestoConRelaciones | null
  /** Sin `gestionar-puestos`: el perfil se consulta, no se toca. */
  soloLectura?: boolean
}

/**
 * El perfil del puesto: sus actividades esenciales y las competencias que
 * exige. Es lo que la evaluación del desempeño copia en el formato de
 * asignación de responsabilidades (FOR-02) de cada servidor.
 */
export function PuestoPerfilDrawer({ opened, onClose, puesto, soloLectura = false }: Props) {
  const puestoId = puesto?.id ? Number(puesto.id) : null

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Perfil del puesto"
      description={puesto?.cargo?.nombre}
      ancho="lg"
    >
      <Stack gap="md">
        <Card withBorder radius="md" p="sm">
          <Group gap="xs" wrap="wrap">
            {puesto?.unidad_administrativa?.nombre && <StatusBadge>{puesto.unidad_administrativa.nombre}</StatusBadge>}
            {puesto?.grupo_ocupacional?.grupo && <StatusBadge>{puesto.grupo_ocupacional.grupo}</StatusBadge>}
            {puesto?.regimen_laboral && <StatusBadge variant="outline">{puesto.regimen_laboral.toUpperCase()}</StatusBadge>}
            {puesto?.nivel_complejidad && <StatusBadge>Complejidad {complejidadEnFemenino(puesto.nivel_complejidad)}</StatusBadge>}
          </Group>
        </Card>

        {puestoId && (
          <Tabs defaultValue="actividades" keepMounted={false}>
            <Tabs.List mb="md">
              <Tabs.Tab value="actividades" leftSection={<IconList size={16} />}>Actividades</Tabs.Tab>
              <Tabs.Tab value="competencias" leftSection={<IconStars size={16} />}>Competencias</Tabs.Tab>
            </Tabs.List>
            <Tabs.Panel value="actividades">
              <PuestoActividadesPanel puestoId={puestoId} soloLectura={soloLectura} />
            </Tabs.Panel>
            <Tabs.Panel value="competencias">
              <PuestoCompetenciasPanel
                puestoId={puestoId}
                complejidad={puesto?.nivel_complejidad}
                soloLectura={soloLectura}
              />
            </Tabs.Panel>
          </Tabs>
        )}
      </Stack>
    </SgthDrawer>
  )
}
