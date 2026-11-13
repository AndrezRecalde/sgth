'use client'

import { Tabs } from '@mantine/core'
import { IconBook, IconBriefcase, IconClipboardCheck, IconFileDescription, IconId, IconScale, IconStars } from '@tabler/icons-react'
import { CountBadge, PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { PuestosTab } from '@/features/estructura/components/PuestosTab'
import { CargosTab } from '@/features/estructura/components/CargosTab'
import { GruposOcupacionalesTab } from '@/features/estructura/components/GruposOcupacionalesTab'
import { CompetenciasTab } from '@/features/estructura/components/CompetenciasTab'
import { RegularizacionTipoTab } from '@/features/estructura/components/RegularizacionTipoTab'
import { ManualPuestosTab } from '@/features/estructura/components/ManualPuestosTab'
import { PerfilesCompletitudTab } from '@/features/estructura/components/PerfilesCompletitudTab'
import { usePorRevisarTipo } from '@/features/estructura/hooks/useRegularizacionTipo'
import { useFichasManual } from '@/features/estructura/hooks/useManualPuestos'
import { useCompletitudPerfiles } from '@/features/estructura/hooks/usePerfilesPuestos'

/**
 * Las tablas del catálogo de puestos, juntas y con pestañas.
 *
 * Se quedan en una sola pantalla porque se leen unas contra otras: un puesto
 * es un cargo colocado en una unidad, y su remuneración sale del grupo
 * ocupacional. Separarlas en entradas del menú obligaría a ir y volver para
 * responder una sola pregunta. El diccionario de competencias va con ellas
 * porque de él sale el perfil de cada puesto.
 */
export function PuestosCargosView() {
  // «Por revisar» es de quien gestiona puestos (P9, PR 4). El contador dice si
  // queda algo, sin tener que abrir la pestaña para saberlo.
  const { hasPermiso } = useAuth()
  const puedeRevisar = hasPermiso('gestionar-puestos')
  const { data: porRevisar } = usePorRevisarTipo(puedeRevisar)
  const pendientes = (porRevisar?.puestos.length ?? 0) + (porRevisar?.contratos.length ?? 0)
  // «Manual» aparece cuando hay fichas importadas (P4, PR 12). El contador,
  // las que faltan por cargar.
  const { data: manual } = useFichasManual(puedeRevisar)
  const porCargar = manual ? manual.fichas.length - manual.resumen.cargada : 0
  // «Perfiles», el tablero de completitud (P6, PR 15). El contador, los
  // puestos ocupados con el perfil incompleto: los que ya tienen a quién evaluar.
  const { data: completitud } = useCompletitudPerfiles(puedeRevisar)
  const ocupadosIncompletos = completitud?.resumen.ocupados_incompletos ?? 0

  return (
    <PageShell>
      <PageHeader
        title="Puestos y cargos"
        description="Catálogo de puestos, denominaciones y escala ocupacional"
      />

      <Tabs defaultValue="puestos" keepMounted={false}>
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
          <Tabs.Tab value="competencias" leftSection={<IconStars size={16} />}>
            Competencias
          </Tabs.Tab>
          {puedeRevisar && (
            <Tabs.Tab
              value="por-revisar"
              leftSection={<IconClipboardCheck size={16} />}
              rightSection={pendientes > 0 ? <CountBadge tone="warning" destacado>{pendientes}</CountBadge> : undefined}
            >
              Por revisar
            </Tabs.Tab>
          )}
          {puedeRevisar && !!manual?.fichas.length && (
            <Tabs.Tab
              value="manual"
              leftSection={<IconBook size={16} />}
              rightSection={porCargar > 0 ? <CountBadge>{porCargar}</CountBadge> : undefined}
            >
              Manual
            </Tabs.Tab>
          )}
          {puedeRevisar && (
            <Tabs.Tab
              value="perfiles"
              leftSection={<IconFileDescription size={16} />}
              rightSection={ocupadosIncompletos > 0 ? <CountBadge tone="warning">{ocupadosIncompletos}</CountBadge> : undefined}
            >
              Perfiles
            </Tabs.Tab>
          )}
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

        <Tabs.Panel value="competencias">
          <CompetenciasTab />
        </Tabs.Panel>

        {puedeRevisar && (
          <Tabs.Panel value="por-revisar">
            <RegularizacionTipoTab />
          </Tabs.Panel>
        )}

        {puedeRevisar && !!manual?.fichas.length && (
          <Tabs.Panel value="manual">
            <ManualPuestosTab />
          </Tabs.Panel>
        )}

        {puedeRevisar && (
          <Tabs.Panel value="perfiles">
            <PerfilesCompletitudTab />
          </Tabs.Panel>
        )}
      </Tabs>
    </PageShell>
  )
}
