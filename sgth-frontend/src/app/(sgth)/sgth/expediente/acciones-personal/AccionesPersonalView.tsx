'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconInbox, IconSignature, IconUserOff, IconUserPlus } from '@tabler/icons-react'
import { SelectorServidorCategoria } from '@/features/expediente/components/SelectorServidorCategoria'
import { MovimientoModal } from '@/features/expediente/components/MovimientoModal'
import { BandejaAccionesPersonal } from '@/features/expediente/components/BandejaAccionesPersonal'
import { FirmantesPanel } from '@/features/expediente/components/FirmantesPanel'
import { AusenciasTemporalesPanel } from '@/features/expediente/components/AusenciasTemporalesPanel'
import {
  TIPO_LABELS, type AccionTipo,
} from '@/features/expediente/utils/taxonomiaAccionPersonal'
import type { ServidorConRelaciones } from '@/types/api'
import { PageHeader, PageShell } from '@/components/ui'

export function AccionesPersonalView() {
  const [servidor, setServidor] = useState<ServidorConRelaciones | null>(null)

  /**
   * La categoría elegida es lo que abre el formulario: no hace falta un
   * disclosure aparte, y así no puede quedar abierto sin tipo ni con el tipo
   * de la vez anterior.
   */
  const [categoria, setCategoria] = useState<AccionTipo | null>(null)

  /**
   * Cerrar el formulario devuelve al grid con el servidor todavía elegido.
   * Limpiarlo obligaba a buscarlo de nuevo tras cancelar por error, y dejaba
   * el buscador mostrando un nombre que ya no estaba seleccionado.
   */
  const handleCerrar = () => setCategoria(null)

  return (
    <PageShell>
      <PageHeader
        title="Acciones de Personal"
        description="Registre nuevas acciones y revise las que esperan aprobación de Talento Humano"
      />

      <Tabs defaultValue="bandeja" color="emerald">
        <Tabs.List mb="md">
          <Tabs.Tab value="bandeja" leftSection={<IconInbox size={16} />}>
            Bandeja de acciones
          </Tabs.Tab>
          <Tabs.Tab value="nueva" leftSection={<IconUserPlus size={16} />}>
            Nueva acción de personal
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
            onCategoriaSeleccionada={setCategoria}
          />

          {/* El mismo formulario de Registrar acción de personal, con el tipo
              ya fijado: cualquier acción pide los mismos datos, y el ingreso
              suma los de la contratación. */}
          {servidor && categoria && (
            <MovimientoModal
              opened
              onClose={handleCerrar}
              servidorId={servidor.id}
              tipoNombramiento={servidor.contrato_vigente?.tipo_nombramiento}
              tipoFijo={categoria}
              titulo={`${TIPO_LABELS[categoria]} — ${[servidor.apellido, servidor.nombre].filter(Boolean).join(' ')}`}
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
