'use client'

import { Tabs } from '@mantine/core'
import { IconClipboardList, IconShieldHeart } from '@tabler/icons-react'
import { CoberturaTab } from '@/features/dispensario/components/CoberturaTab'
import { SeguimientoSolicitudesTab } from '@/features/dispensario/components/SeguimientoSolicitudesTab'
import { PageHeader, PageShell } from '@/components/ui'

/*
| Certificaciones médicas ocupacionales.
|
| La pantalla era una tabla de solicitudes de solo lectura: lo mismo que ya
| enseña la bandeja del Dispensario, con dos filtros más y ninguna acción.
|
| El problema de fondo no era que faltaran botones, sino el eje. Una solicitud
| solo existe cuando alguien se acordó de pedirla, así que quien nunca fue
| evaluado no aparecía en ninguna pantalla del sistema —ni aquí, ni en Salud
| Ocupacional, ni en el expediente, que va de uno en uno—, y es justo a quien
| hay que mandar a evaluar.
|
| Ahora abre por Cobertura, que lista SERVIDORES; el seguimiento de solicitudes
| queda detrás, que es donde se retira una pedida por error.
*/
export function CertificacionesMedicasView() {
  return (
    <PageShell>
      <PageHeader
        title="Certificaciones médicas"
        description="Estado de las evaluaciones médicas ocupacionales de la plantilla y seguimiento de las solicitudes enviadas al Dispensario."
      />

      <Tabs defaultValue="cobertura">
        <Tabs.List mb="md">
          <Tabs.Tab value="cobertura" leftSection={<IconShieldHeart size={16} />}>
            Cobertura de la plantilla
          </Tabs.Tab>
          <Tabs.Tab value="solicitudes" leftSection={<IconClipboardList size={16} />}>
            Solicitudes enviadas
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="cobertura">
          <CoberturaTab />
        </Tabs.Panel>

        <Tabs.Panel value="solicitudes">
          <SeguimientoSolicitudesTab />
        </Tabs.Panel>
      </Tabs>
    </PageShell>
  )
}
