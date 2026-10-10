'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconInbox, IconSignature, IconUserOff, IconUserPlus } from '@tabler/icons-react'
import { SelectorServidorCategoria } from '@/features/expediente/components/SelectorServidorCategoria'
import { MovimientoModal } from '@/features/expediente/components/MovimientoModal'
import { BandejaAccionesPersonal } from '@/features/expediente/components/BandejaAccionesPersonal'
import { FirmantesPanel } from '@/features/expediente/components/FirmantesPanel'
import { AusenciasTemporalesPanel } from '@/features/expediente/components/AusenciasTemporalesPanel'
import { usePuedePrepararAccion } from '@/features/expediente/hooks/usePuedePrepararAccion'
import type { FamiliaDelCatalogo, ServidorConRelaciones } from '@/types/api'
import { PageHeader, PageShell } from '@/components/ui'

export function AccionesPersonalView() {
  const [servidor, setServidor] = useState<ServidorConRelaciones | null>(null)

  // La pestaña de registrar es de quien prepara acciones (diseño, 6.3). A
  // quien solo suscribe o registra le queda la bandeja.
  const puedePreparar = usePuedePrepararAccion()

  /**
   * La familia elegida es lo que abre el formulario: no hace falta un
   * disclosure aparte, y así no puede quedar abierto sin familia ni con la de
   * la vez anterior.
   */
  const [familia, setFamilia] = useState<FamiliaDelCatalogo | null>(null)

  /**
   * Cerrar el formulario devuelve al grid con el servidor todavía elegido.
   * Limpiarlo obligaba a buscarlo de nuevo tras cancelar por error, y dejaba
   * el buscador mostrando un nombre que ya no estaba seleccionado.
   */
  const handleCerrar = () => setFamilia(null)

  return (
    <PageShell>
      <PageHeader
        title="Acciones de Personal"
        description="Registre nuevas acciones y revise las que esperan aprobación de Talento Humano"
      />

      {/* Sin esto Mantine monta las cuatro pestañas de golpe y la pantalla
          arranca pidiendo la bandeja, las ausencias y los firmantes aunque
          solo se vaya a mirar una. La ficha del servidor ya lo hacía así. */}
      <Tabs defaultValue="bandeja" keepMounted={false}>
        <Tabs.List mb="md">
          <Tabs.Tab value="bandeja" leftSection={<IconInbox size={16} />}>
            Bandeja de acciones
          </Tabs.Tab>
          {puedePreparar && (
            <Tabs.Tab value="nueva" leftSection={<IconUserPlus size={16} />}>
              Nueva acción de personal
            </Tabs.Tab>
          )}
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
            onFamiliaSeleccionada={setFamilia}
          />

          {/* El mismo formulario de Registrar acción de personal, con la
              familia ya elegida: cualquier acción pide los mismos datos, y el
              ingreso suma los de la contratación. */}
          {servidor && familia && (
            <MovimientoModal
              opened
              onClose={handleCerrar}
              servidorId={servidor.id}
              tipoNombramiento={servidor.contrato_vigente?.tipo_nombramiento}
              sinVinculo={servidor.pendiente_vinculacion === true}
              familia={familia.codigo}
              titulo={`${familia.etiqueta} — ${[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}`}
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
    </PageShell>
  )
}
