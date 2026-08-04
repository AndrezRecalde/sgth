'use client'

import { useState } from 'react'
import { Box, Tabs } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconInbox, IconSignature, IconUserOff, IconUserPlus } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { SelectorServidorCategoria } from '@/features/expediente/components/SelectorServidorCategoria'
import { MovimientoModal } from '@/features/expediente/components/MovimientoModal'
import { BandejaAccionesPersonal } from '@/features/expediente/components/BandejaAccionesPersonal'
import { FirmantesPanel } from '@/features/expediente/components/FirmantesPanel'
import { AusenciasTemporalesPanel } from '@/features/expediente/components/AusenciasTemporalesPanel'
import type { ServidorConRelaciones } from '@/types/api'

export function AccionesPersonalView() {
  const [servidor, setServidor] = useState<ServidorConRelaciones | null>(null)
  const [ingresoOpened, { open: openIngreso, close: closeIngreso }] = useDisclosure(false)

  const handleCategoriaSeleccionada = (categoria: string) => {
    if (categoria === 'ingreso') openIngreso()
  }

  const handleCerrarIngreso = () => {
    closeIngreso()
    setServidor(null)
  }

  return (
    <Box>
      <PageHeader
        title="Acciones de Personal"
        subtitle="Registre nuevas acciones y revise las que esperan aprobación de Talento Humano"
        icon={<IconUserPlus size={28} />}
      />

      <Tabs defaultValue="bandeja" color="emerald">
        <Tabs.List mb="md">
          <Tabs.Tab value="bandeja" leftSection={<IconInbox size={16} />}>
            Bandeja de acciones
          </Tabs.Tab>
          <Tabs.Tab value="nueva" leftSection={<IconUserPlus size={16} />}>
            Nuevo ingreso y vinculación
          </Tabs.Tab>
          <Tabs.Tab value="ausencias" leftSection={<IconUserOff size={16} />}>
            Ausencias y reemplazos
          </Tabs.Tab>
          <Tabs.Tab value="firmantes" leftSection={<IconSignature size={16} />}>
            Firmantes
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="bandeja">
          <BandejaAccionesPersonal />
        </Tabs.Panel>

        <Tabs.Panel value="nueva">
          <SelectorServidorCategoria
            servidor={servidor}
            onServidorChange={setServidor}
            onCategoriaSeleccionada={handleCategoriaSeleccionada}
          />

          {/* El mismo formulario de Registrar acción de personal, con el tipo
              ya fijado: un ingreso pide exactamente los mismos datos que
              cualquier otra acción, más los de la contratación. */}
          {servidor && (
            <MovimientoModal
              opened={ingresoOpened}
              onClose={handleCerrarIngreso}
              servidorId={servidor.id}
              tipoFijo="ingreso"
              titulo={`Ingreso y Vinculación — ${[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}`}
            />
          )}
        </Tabs.Panel>

        <Tabs.Panel value="ausencias">
          <AusenciasTemporalesPanel />
        </Tabs.Panel>

        <Tabs.Panel value="firmantes">
          <FirmantesPanel />
        </Tabs.Panel>
      </Tabs>
    </Box>
  )
}
