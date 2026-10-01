'use client'

import { useState } from 'react'
import { Group, TextInput, Button, Text, Alert } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import {
  IconSearch, IconList, IconAlertCircle, IconClipboardCheck,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { DataState, PageHeader, PageShell, SgthTable, Toolbar } from '@/components/ui'
import { useListaVerificacion } from '@/features/sso/hooks/useCumplimiento'
import { NormativaLegalModal } from '@/features/sso/components/NormativaLegalModal'
import { RegistrarCumplimientoModal } from '@/features/sso/components/RegistrarCumplimientoModal'
import { columnasListaVerificacion } from '@/features/sso/components/listaVerificacion.columns'
import { TONO_ESTADO_CUMPLIMIENTO } from '@/features/sso/schemas/cumplimiento.schema'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { AYUDA_PERIODO, EJEMPLO_PERIODO, esPeriodoValido } from '@/features/sso/constants/periodo'
import type { FilaListaVerificacion } from '@/features/sso/services/tipos'

export function CumplimientoView() {
  // La variante compacta de 40 px: es una barra de filtros, no un formulario
  // de captura (regla 06).
  const contained = useContainedInput('sm')
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [normativasOpened, { open: openNormativas, close: closeNormativas }] = useDisclosure(false)
  const [cumplimientoOpened, { open: openCumplimiento, close: closeCumplimiento }] = useDisclosure(false)
  const [filaSeleccionada, setFilaSeleccionada] = useState<FilaListaVerificacion | null>(null)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { data: lista, isLoading, error, refetch } = useListaVerificacion(periodo)

  const handleConsultar = () => {
    if (esPeriodoValido(periodoInput)) {
      setPeriodo(periodoInput)
    }
  }

  const handleEditar = (fila: FilaListaVerificacion) => {
    setFilaSeleccionada(fila)
    openCumplimiento()
  }

  return (
    <PageShell>
      <PageHeader
        title="Cumplimiento Normativo"
        description="Lista de verificación de la normativa legal de seguridad y salud por período"
        // El catálogo sale de la `Toolbar`: las acciones de la barra son las
        // ligadas al filtro —aquí, Consultar—, y abrir el catálogo es la
        // acción principal de la pantalla (regla 06).
        actions={puedeGestionar ? (
          <Button leftSection={<IconList size={16} />} variant="light" onClick={openNormativas}>
            Catálogo de normativas
          </Button>
        ) : undefined}
      />

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
            {...contained}
            value={periodoInput}
            onChange={(e) => setPeriodoInput(e.currentTarget.value)}
          />
      </Toolbar>

      {!periodo && (
        <Alert icon={<IconAlertCircle size={18} />} color="ocean" variant="light">
          Ingrese un período y presione Consultar para ver la lista de verificación de cumplimiento.
        </Alert>
      )}

      {periodo && (
        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudo cargar la lista de verificación"
          errorHint="No quiere decir que no haya normativa registrada: no se pudo consultar."
          onRetry={() => refetch()}
          empty={!lista?.filas.length}
          emptyProps={{
            icon: IconClipboardCheck,
            title: 'No hay normativa en el catálogo',
            description: 'La lista de verificación se arma con la normativa activa. Agréguela para poder registrar su cumplimiento.',
            action: puedeGestionar ? (
              <Button variant="light" leftSection={<IconList size={16} />} onClick={openNormativas}>
                Catálogo de normativas
              </Button>
            ) : undefined,
          }}
        >
          {lista && (
            <>
              {/* El color de cada total sale del mismo mapa que la insignia de
                  su fila (regla 06): escritos a mano, «En proceso» era `amber.7`
                  aquí y el ámbar del sistema tres centímetros más abajo, en la
                  tabla. */}
              <Group gap="lg" mb="sm">
                <Text size="sm">Total: <Text span fw={600}>{lista.totales.total}</Text></Text>
                <Text size="sm" c={SEMANTIC_COLOR[TONO_ESTADO_CUMPLIMIENTO.cumple]}>
                  Cumple: <Text span fw={600}>{lista.totales.cumple}</Text>
                </Text>
                <Text size="sm" c={SEMANTIC_COLOR[TONO_ESTADO_CUMPLIMIENTO.no_cumple]}>
                  No cumple: <Text span fw={600}>{lista.totales.no_cumple}</Text>
                </Text>
                <Text size="sm" c={SEMANTIC_COLOR[TONO_ESTADO_CUMPLIMIENTO.en_proceso]}>
                  En proceso: <Text span fw={600}>{lista.totales.en_proceso}</Text>
                </Text>
                <Text size="sm" c="dimmed">Sin registrar: <Text span fw={600}>{lista.totales.no_registrado}</Text></Text>
              </Group>

              <SgthTable
                records={lista.filas}
                columns={columnasListaVerificacion(puedeGestionar ? handleEditar : undefined)}
                idAccessor="normativa.id"
                minHeight={150}
              />
            </>
          )}
        </DataState>
      )}

      <NormativaLegalModal opened={normativasOpened} onClose={closeNormativas} />
      <RegistrarCumplimientoModal
        opened={cumplimientoOpened}
        onClose={() => { setFilaSeleccionada(null); closeCumplimiento() }}
        fila={filaSeleccionada}
        periodo={periodo ?? ''}
      />
    </PageShell>
  )
}
