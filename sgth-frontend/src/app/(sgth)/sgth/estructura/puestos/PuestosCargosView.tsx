'use client'

import { Tabs } from '@mantine/core'
import { IconBriefcase, IconId, IconScale } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { PuestosTab } from '@/features/estructura/components/PuestosTab'
import { CargosTab } from '@/features/estructura/components/CargosTab'
import { GruposOcupacionalesTab } from '@/features/estructura/components/GruposOcupacionalesTab'

/**
 * Las tres tablas del catálogo de puestos, juntas y con pestañas.
 *
 * Se quedan en una sola pantalla porque se leen unas contra otras: un puesto
 * es un cargo colocado en una unidad, y su remuneración sale del grupo
 * ocupacional. Separarlas en tres entradas del menú obligaría a ir y volver
 * para responder una sola pregunta.
 */
export function PuestosCargosView() {
  return (
    <PageShell>
      <PageHeader
        title="Puestos y cargos"
        description="Catálogo de puestos, denominaciones y escala ocupacional"
      />

      <Tabs defaultValue="puestos" color="emerald" keepMounted={false}>
        <Tabs.List mb="md">
          <Tabs.Tab value="puestos" leftSection={<IconBriefcase size={16} />}>
            Puestos
          </Tabs.Tab>
          <Tabs.Tab value="cargos" leftSection={<IconId size={16} />}>
            Cargos
          </Tabs.Tab>
          <Tabs.Tab value="grupos" leftSection={<IconScale size={16} />}>
            Grupos ocupacionales
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="puestos">
          <PuestosTab />
        </Tabs.Panel>

        <Tabs.Panel value="cargos">
          <CargosTab />
        </Tabs.Panel>

        <Tabs.Panel value="grupos">
          <GruposOcupacionalesTab />
        </Tabs.Panel>
      </Tabs>
    </PageShell>
  )
}
