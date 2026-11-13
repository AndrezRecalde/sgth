'use client'

import { useState } from 'react'
import { Button, Card, Group, Stack, Tabs } from '@mantine/core'
import { IconCopy, IconFileDescription, IconList, IconPrinter, IconStars } from '@tabler/icons-react'
import { SgthDrawer, StatusBadge } from '@/components/ui'
import type { NivelCompetencia } from '@/types/api'
import { REGIMEN_LABELS } from '@/lib/regimen'
import { complejidadEnFemenino } from '../constants/perfilPuesto'
import { PuestoActividadesPanel } from './PuestoActividadesPanel'
import { PuestoDescripcionPanel } from './PuestoDescripcionPanel'
import { useFichaDelPuestoPdf } from '../hooks/useDescripcionPuesto'
import { PuestoCompetenciasPanel } from './PuestoCompetenciasPanel'
import { CopiarPerfilModal } from './CopiarPerfilModal'

/**
 * Lo que el cajón muestra del puesto. Lo trae el listado de puestos y también
 * el tablero de completitud (PR 15), que no carga el puesto entero.
 */
export interface PuestoDelPerfil {
  id: number | string
  cargo?: { nombre?: string } | null
  unidad_administrativa?: { nombre?: string } | null
  grupo_ocupacional?: {
    grupo?: string | null
    grado_codigo?: string | null
    nivel_competencia_sugerido?: NivelCompetencia | null
  } | null
  regimen_laboral?: string | null
  nivel_complejidad?: string | null
}

interface Props {
  opened:  boolean
  onClose: () => void
  puesto:  PuestoDelPerfil | null
  /** Sin `gestionar-puestos`: el perfil se consulta, no se toca. */
  soloLectura?: boolean
}

/**
 * El perfil del puesto, en el orden de la ficha del Manual de Puestos: su
 * descripción (misión, requisitos y capacitación), sus actividades esenciales
 * y las competencias que exige. Las actividades y las competencias son lo que
 * la evaluación del desempeño copia en el formato de asignación de
 * responsabilidades (FOR-02) de cada servidor.
 */
export function PuestoPerfilDrawer({ opened, onClose, puesto, soloLectura = false }: Props) {
  const puestoId = puesto?.id ? Number(puesto.id) : null
  const ficha = useFichaDelPuestoPdf()
  const [copiando, setCopiando] = useState(false)

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
          <Group justify="space-between" align="flex-start" wrap="nowrap">
            <Group gap="xs" wrap="wrap">
              {puesto?.unidad_administrativa?.nombre && <StatusBadge>{puesto.unidad_administrativa.nombre}</StatusBadge>}
              {puesto?.grupo_ocupacional?.grupo && <StatusBadge>{puesto.grupo_ocupacional.grupo}</StatusBadge>}
              {/* El régimen es una categoría: neutra y con su nombre. Salía como
                  «CODIGO_TRABAJO», el valor guardado en mayúsculas. */}
              {puesto?.regimen_laboral && (
                <StatusBadge>{REGIMEN_LABELS[puesto.regimen_laboral] ?? puesto.regimen_laboral}</StatusBadge>
              )}
              {puesto?.nivel_complejidad && <StatusBadge>Complejidad {complejidadEnFemenino(puesto.nivel_complejidad)}</StatusBadge>}
            </Group>
            {puestoId && (
              <Group gap="xs" wrap="nowrap" style={{ flexShrink: 0 }}>
                {/* El perfil de un puesto parecido, para los que no tienen ficha en el manual (D10, PR 15). */}
                {!soloLectura && (
                  <Button size="xs" variant="default" leftSection={<IconCopy size={14} />} onClick={() => setCopiando(true)}>
                    Copiar de otro puesto
                  </Button>
                )}
                {/* La ficha «Descripción y perfil del puesto», como la del Manual de Puestos (P5). */}
                <Button size="xs" variant="light" leftSection={<IconPrinter size={14} />} loading={ficha.abriendo}
                  onClick={() => ficha.abrir(puestoId, puesto?.cargo?.nombre ?? `puesto-${puestoId}`)}>
                  Imprimir ficha
                </Button>
              </Group>
            )}
          </Group>
        </Card>

        {puestoId && (
          <Tabs defaultValue="descripcion" keepMounted={false}>
            <Tabs.List mb="md">
              <Tabs.Tab value="descripcion" leftSection={<IconFileDescription size={16} />}>Descripción</Tabs.Tab>
              <Tabs.Tab value="actividades" leftSection={<IconList size={16} />}>Actividades</Tabs.Tab>
              <Tabs.Tab value="competencias" leftSection={<IconStars size={16} />}>Competencias</Tabs.Tab>
            </Tabs.List>
            <Tabs.Panel value="descripcion">
              <PuestoDescripcionPanel puestoId={puestoId} soloLectura={soloLectura} />
            </Tabs.Panel>
            <Tabs.Panel value="actividades">
              <PuestoActividadesPanel puestoId={puestoId} soloLectura={soloLectura} />
            </Tabs.Panel>
            <Tabs.Panel value="competencias">
              <PuestoCompetenciasPanel
                puestoId={puestoId}
                grado={puesto?.grupo_ocupacional?.grado_codigo}
                porGrado={puesto?.grupo_ocupacional?.nivel_competencia_sugerido}
                complejidad={puesto?.nivel_complejidad}
                soloLectura={soloLectura}
              />
            </Tabs.Panel>
          </Tabs>
        )}
      </Stack>

      {puestoId && !soloLectura && (
        <CopiarPerfilModal opened={copiando} onClose={() => setCopiando(false)} puestoId={puestoId} />
      )}
    </SgthDrawer>
  )
}
