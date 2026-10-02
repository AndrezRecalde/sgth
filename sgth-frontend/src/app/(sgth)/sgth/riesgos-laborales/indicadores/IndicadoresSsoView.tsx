'use client'

import { useState } from 'react'
import { TextInput, Button, Select, Alert, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import { IconSearch, IconClock, IconAlertCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { AYUDA_PERIODO, EJEMPLO_PERIODO, esPeriodoValido } from '@/features/sso/constants/periodo'
import { GestionarHorasTrabajadasModal } from '@/features/sso/components/GestionarHorasTrabajadasModal'
import { IndicadoresReactivosPanel } from '@/features/sso/components/IndicadoresReactivosPanel'
import { IndicadoresProactivosPanel } from '@/features/sso/components/IndicadoresProactivosPanel'
import { PageHeader, PageShell, Toolbar } from '@/components/ui'

export function IndicadoresSsoView() {
  // La variante compacta de 40 px: es una barra de filtros, no un
  // formulario de captura (regla 06).
  const compacto = useContainedInput('sm')
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [unidadInput, setUnidadInput] = useState<string | null>(null)
  const [unidad, setUnidad] = useState<string | null>(null)
  const [horasOpened, { open: openHoras, close: closeHoras }] = useDisclosure(false)

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  // La unidad viaja con el período y no por su cuenta: los dos se aplican al
  // pulsar Consultar, para que cambiar de unidad no dispare dos consultas
  // mientras se elige.
  const handleConsultar = () => {
    if (esPeriodoValido(periodoInput)) {
      setPeriodo(periodoInput)
      setUnidad(unidadInput)
    }
  }

  return (
    <PageShell>
      <PageHeader
        title="Indicadores SSO"
        description="Índices reactivos del CD 513 e índices proactivos del período"
        // Cargar las horas es la acción principal de la pantalla; «Consultar»
        // pertenece al filtro y se queda en la `Toolbar` (regla 06).
        actions={puedeGestionar ? (
          <Button leftSection={<IconClock size={16} />} variant="light" onClick={openHoras}>
            Horas trabajadas
          </Button>
        ) : undefined}
      />

      {/* En `Toolbar` y no en un `Group` escrito a mano, con la variante
          compacta de 40 px que la regla 06 pide para las barras de filtro. */}
      <Toolbar
        actions={
          <Button
            leftSection={<IconSearch size={16} />}
            onClick={handleConsultar}
            disabled={!esPeriodoValido(periodoInput)}
          >
            Consultar
          </Button>
        }
      >
        <TextInput
          label="Período"
          placeholder={EJEMPLO_PERIODO}
          description={AYUDA_PERIODO}
          {...compacto}
          value={periodoInput}
          onChange={(e) => setPeriodoInput(e.currentTarget.value)}
        />
        {/* El backend calcula los dos juegos de índices por unidad desde que
            existen, y la pantalla solo sabía pedir el total institucional:
            una dirección no tenía forma de ver su propio índice. */}
        <Select
          label="Unidad administrativa"
          placeholder="Toda la institución"
          data={unidadOptions}
          searchable
          clearable
          style={{ minWidth: 240 }}
          {...compacto}
          value={unidadInput}
          onChange={setUnidadInput}
        />
      </Toolbar>

      {!periodo && (
        <Alert icon={<IconAlertCircle size={18} />} color="ocean" variant="light">
          Ingrese un período y presione Consultar para ver los índices reactivos (CD 513) y proactivos.
        </Alert>
      )}

      {periodo && (
        <Stack gap="xl">
          <IndicadoresReactivosPanel
            periodo={periodo}
            unidadId={unidad ? Number(unidad) : undefined}
            puedeGestionar={puedeGestionar}
            onCargarHoras={openHoras}
          />
          <IndicadoresProactivosPanel
            periodo={periodo}
            unidadId={unidad ? Number(unidad) : undefined}
          />
        </Stack>
      )}

      <GestionarHorasTrabajadasModal opened={horasOpened} onClose={closeHoras} />
    </PageShell>
  )
}
