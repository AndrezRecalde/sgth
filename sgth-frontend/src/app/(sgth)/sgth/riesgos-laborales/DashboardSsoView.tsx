'use client'

import { useState } from 'react'
import { Box, TextInput, Button, Select, Alert, Stack } from '@mantine/core'
import { IconSearch, IconAlertCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { DataState, PageHeader, PageShell, Toolbar } from '@/components/ui'
import { AvisoAlcance } from '@/features/sso/components/AvisoAlcance'
import { ResumenSsoTarjetas } from '@/features/sso/components/ResumenSsoTarjetas'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useDashboardSso } from '@/features/sso/hooks/useDashboardSso'
import { AYUDA_PERIODO, EJEMPLO_PERIODO, esPeriodoValido } from '@/features/sso/constants/periodo'

export function DashboardSsoView() {
  // La variante compacta de 40 px: barra de filtros, no formulario de
  // captura (regla 06).
  const compacto = useContainedInput('sm')
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [unidadInput, setUnidadInput] = useState<string | null>(null)
  const [unidad, setUnidad] = useState<string | null>(null)

  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  // La unidad viaja con el período y no por su cuenta: los dos se aplican al
  // pulsar Consultar, para que cambiar de unidad no dispare una consulta
  // mientras se elige.
  const params = periodo
    ? { periodo, unidad_administrativa_id: unidad ? Number(unidad) : undefined }
    : null
  const { data: resumen, isLoading, error, refetch } = useDashboardSso(params)

  const handleConsultar = () => {
    if (esPeriodoValido(periodoInput)) {
      setPeriodo(periodoInput)
      setUnidad(unidadInput)
    }
  }

  return (
    <PageShell>
      <PageHeader
        title="Riesgos Laborales (SSO)"
        description="Resumen del período: riesgos, accidentes, índices del CD 513, cumplimiento y tamizajes"
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
        {/* El resumen acepta una unidad desde que existe —el controlador la
            valida y seis de los nueve bloques la respetan—, pero la pantalla
            solo sabía pedir el total institucional. Sin este campo, el aviso
            de alcance de abajo no podía mostrarse nunca: el backend solo manda
            la nota cuando se pidió una unidad que el indicador no puede dar. */}
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

      <Box>

        {!periodo && (
          <Alert icon={<IconAlertCircle size={18} />} color="ocean" variant="light">
            Ingrese un período y presione Consultar para ver el resumen de indicadores de todas las fases del módulo SSO.
          </Alert>
        )}

        {periodo && (
          <DataState
            loading={isLoading}
            error={error}
            errorTitle="No se pudo calcular el resumen del período"
            errorHint="No quiere decir que el período no tenga actividad registrada: no se pudo consultar."
            onRetry={() => refetch()}
            skeletonRows={4}
          >
            {resumen && (
              <Stack gap="lg">
                {/* Seis de los nueve bloques filtran por la unidad pedida; el
                    catálogo de EPP, la normativa legal y las actividades del
                    programa no se registran por unidad y lo dicen. */}
                <AvisoAlcance
                  alcances={resumen.alcances}
                  etiquetas={{
                    riesgos: 'Riesgos',
                    accidentes: 'Accidentes',
                    epp: 'EPP',
                    cumplimiento: 'Cumplimiento normativo',
                    psicosocial: 'Psicosocial',
                    assist: 'ASSIST',
                    programa_drogas: 'Programa de drogas',
                    ausentismo: 'Ausentismo',
                  }}
                />
                <ResumenSsoTarjetas resumen={resumen} />
              </Stack>
            )}
          </DataState>
        )}
      </Box>
    </PageShell>
  )
}
