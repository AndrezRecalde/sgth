'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import {
  IconBuildingBank, IconFileText, IconHourglass, IconInbox, IconMailForward, IconSignature, IconStack2, IconUserMinus,
  IconUserOff, IconUserPlus,
} from '@tabler/icons-react'
import { SelectorServidorCategoria } from '@/features/expediente/components/SelectorServidorCategoria'
import { MovimientoModal } from '@/features/expediente/components/MovimientoModal'
import { BandejaAccionesPersonal } from '@/features/expediente/components/BandejaAccionesPersonal'
import { FirmantesPanel } from '@/features/expediente/components/FirmantesPanel'
import { AusenciasTemporalesPanel } from '@/features/expediente/components/AusenciasTemporalesPanel'
import { PeriodosDePruebaPanel } from '@/features/expediente/components/PeriodosDePruebaPanel'
import { AutoridadesElectasPanel } from '@/features/expediente/components/AutoridadesElectasPanel'
import { TerminacionesObrerosPanel } from '@/features/expediente/components/TerminacionesObrerosPanel'
import { IncrementosEnLotePanel } from '@/features/expediente/components/IncrementosEnLotePanel'
import { ContratoColectivoPanel } from '@/features/expediente/components/ContratoColectivoPanel'
import { SolicitudesDelPortalPanel } from '@/features/expediente/components/SolicitudesDelPortalPanel'
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
          {/* Renuncias, licencias y jubilaciones pedidas desde el portal (fase 6.3b). */}
          {puedePreparar && (
            <Tabs.Tab value="solicitudes" leftSection={<IconMailForward size={16} />}>
              Solicitudes del portal
            </Tabs.Tab>
          )}
          <Tabs.Tab value="ausencias" leftSection={<IconUserOff size={16} />}>
            Ausencias y reemplazos
          </Tabs.Tab>
          <Tabs.Tab value="prueba" leftSection={<IconHourglass size={16} />}>
            Períodos de prueba
          </Tabs.Tab>
          <Tabs.Tab value="autoridades" leftSection={<IconBuildingBank size={16} />}>
            Autoridades electas
          </Tabs.Tab>
          <Tabs.Tab value="terminaciones-obreros" leftSection={<IconUserMinus size={16} />}>
            Terminaciones de obreros
          </Tabs.Tab>
          <Tabs.Tab value="lotes" leftSection={<IconStack2 size={16} />}>
            Incrementos en lote
          </Tabs.Tab>
          <Tabs.Tab value="contrato-colectivo" leftSection={<IconFileText size={16} />}>
            Contrato colectivo
          </Tabs.Tab>
          <Tabs.Tab value="firmantes" leftSection={<IconSignature size={16} />}>
            Firmantes
          </Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="bandeja">
          <BandejaAccionesPersonal />
        </Tabs.Panel>

        <Tabs.Panel value="solicitudes">
          <SolicitudesDelPortalPanel />
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
              ultimoNombramiento={servidor.ultimo_nombramiento}
              familia={familia.codigo}
              titulo={`${familia.etiqueta} — ${[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}`}
            />
          )}
        </Tabs.Panel>

        <Tabs.Panel value="ausencias">
          <AusenciasTemporalesPanel />
        </Tabs.Panel>

        <Tabs.Panel value="prueba">
          <PeriodosDePruebaPanel />
        </Tabs.Panel>

        <Tabs.Panel value="autoridades">
          <AutoridadesElectasPanel />
        </Tabs.Panel>

        <Tabs.Panel value="terminaciones-obreros">
          <TerminacionesObrerosPanel />
        </Tabs.Panel>

        {/* Los obreros: el incremento en lote y el contrato colectivo (fase 4.6). */}
        <Tabs.Panel value="lotes">
          <IncrementosEnLotePanel />
        </Tabs.Panel>

        <Tabs.Panel value="contrato-colectivo">
          <ContratoColectivoPanel />
        </Tabs.Panel>

        <Tabs.Panel value="firmantes">
          <FirmantesPanel />
        </Tabs.Panel>
      </Tabs>
    </PageShell>
  )
}
