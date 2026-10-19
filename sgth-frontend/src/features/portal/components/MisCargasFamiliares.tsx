'use client'

import { useState } from 'react'
import { Alert, Button, Stack, Text } from '@mantine/core'
import { IconInfoCircle, IconPlus, IconUsers } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import type { MiCargaFamiliar } from '@/types/api'
import { AVISO_CONDICIONES_DE_SALUD } from '../constants/misCargas'
import { useMisCargas, useMisCargasMutations } from '../hooks/useMisCargas'
import { BajaCargaModal } from './BajaCargaModal'
import { getMisCargasColumns, getSolicitudesCargaColumns } from './misCargas.columns'
import { SolicitudCargaModal } from './SolicitudCargaModal'

/** Qué modal está abierto: el registro, la corrección de una carga o ninguno. */
type Edicion = { carga: MiCargaFamiliar | null } | null

/**
 * La pestaña «Cargas familiares» de «Mi perfil»
 * (docs/diseno-cargas-familiares-portal.md, 5): sus familiares y lo que pidió
 * sobre ellos. Registrar, corregir y dar de baja son solicitudes que no cuentan
 * hasta que Talento Humano las valida. El registro está siempre abierto (D1).
 */
export function MisCargasFamiliares() {
  const { data, isLoading, error, refetch } = useMisCargas()
  const { retirar, verDocumento } = useMisCargasMutations()
  const [edicion, setEdicion] = useState<Edicion>(null)
  const [deBaja, setDeBaja] = useState<MiCargaFamiliar | null>(null)

  const cargas = data?.cargas ?? []
  const solicitudes = data?.solicitudes ?? []

  const columnasCargas = getMisCargasColumns({
    onCorregir: (carga) => setEdicion({ carga }),
    onBaja:     setDeBaja,
  })
  const columnasSolicitudes = getSolicitudesCargaColumns({
    onDocumento: (s) => verDocumento.mutate({ id: s.id, nombre: s.documento_nombre }),
    onRetirar:   (id) => retirar.mutate(id),
  })

  const botonRegistrar = (
    <Button size="xs" variant="light" leftSection={<IconPlus size={14} />}
      onClick={() => setEdicion({ carga: null })}>
      Registrar familiar
    </Button>
  )

  return (
    <Stack gap="lg">
      <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
        <Text size="sm">
          Talento Humano revisa cada solicitud. Hasta que la valide, el familiar no puede atenderse
          en el Dispensario.
        </Text>
        <Text size="sm" mt="xs">{AVISO_CONDICIONES_DE_SALUD}</Text>
      </Alert>

      <SectionCard
        title="Sus cargas familiares"
        description="Su cónyuge o conviviente y sus hijos, como constan en su expediente."
        actions={botonRegistrar}
      >
        <DataState
          loading={isLoading}
          error={error}
          empty={!cargas.length}
          errorTitle="No se pudieron cargar sus cargas familiares"
          onRetry={() => void refetch()}
          skeletonRows={2}
          emptyProps={{
            icon: IconUsers,
            title: 'No tiene cargas familiares registradas',
            description: 'Registre a su cónyuge o conviviente y a sus hijos; Talento Humano los validará.',
            action: botonRegistrar,
          }}
        >
          <SgthTable records={cargas} columns={columnasCargas} minHeight={80} />
        </DataState>
      </SectionCard>

      {solicitudes.length > 0 && (
        <SectionCard
          title="Sus solicitudes"
          description="Lo que pidió a Talento Humano y en qué quedó."
        >
          <SgthTable records={solicitudes} columns={columnasSolicitudes} minHeight={80} />
        </SectionCard>
      )}

      <SolicitudCargaModal
        // Por carga: cada corrección parte de los datos vigentes.
        key={edicion?.carga?.id ?? 'nueva'}
        opened={edicion !== null}
        onClose={() => setEdicion(null)}
        carga={edicion?.carga}
      />
      <BajaCargaModal key={deBaja?.id ?? 'ninguna'} carga={deBaja} onClose={() => setDeBaja(null)} />
    </Stack>
  )
}
