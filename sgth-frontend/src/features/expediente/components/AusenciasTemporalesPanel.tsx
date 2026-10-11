'use client'

import { useState } from 'react'
import { Alert, Select, Stack, Text } from '@mantine/core'
import { IconInfoCircle, IconUserOff } from '@tabler/icons-react'
import { DataState, SgthTable, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAusenciasTemporales } from '../hooks/useAusenciasTemporales'
import { usePuedePrepararAcciones } from '../hooks/usePuedePrepararAccion'
import type { AusenciaTemporal } from '../services/ausenciaTemporalService'
import { getAusenciaColumns } from './ausenciasTemporales.columns'
import { ReintegroModal } from './ReintegroModal'

const COBERTURA_OPTIONS = [
  { value: 'pendientes', label: 'Sin cubrir' },
  { value: 'cubiertas', label: 'Ya cubiertas' },
]

/**
 * Quién está temporalmente fuera y qué huecos quedan por cubrir.
 *
 * La ausencia no es un estado guardado: se deriva del período de la comisión o
 * la licencia, así que al vencer sale sola de este listado. El contrato del
 * titular sigue vigente todo el tiempo — no se libera la plaza, se autoriza un
 * apoyo temporal encima de ella.
 *
 * Cuando el titular vuelve, el reintegro se prepara desde aquí (fase 2.4): es
 * el acto que cierra la ausencia, y nace de ella porque sin ella no sabe qué
 * cerrar.
 */
export function AusenciasTemporalesPanel() {
  // Compacta: es la altura que pide el contrato de `Toolbar`.
  const contained = useContainedInput('sm')
  const [cobertura, setCobertura] = useState<string | null>(null)
  const [aReintegrar, setAReintegrar] = useState<AusenciaTemporal | null>(null)
  const puedePreparar = usePuedePrepararAcciones()

  const { data: ausencias = [], isLoading, error, refetch } = useAusenciasTemporales(
    cobertura ? { cubiertas: cobertura === 'cubiertas' } : {},
  )

  const columns = getAusenciaColumns({ puedePreparar, onReintegrar: setAReintegrar })

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        El titular conserva su vínculo y su plaza mientras dura la ausencia. El
        reemplazo se contrata por Servicios Ocasionales o Profesionales, encima
        de esa plaza y sin pasar de la fecha en que el titular regresa.
      </Alert>

      {/* El filtro va en `Toolbar`, como en la bandeja: era un `Group` suelto,
          y las dos pestañas del mismo módulo presentaban sus filtros distinto. */}
      <Toolbar
        actions={
          <Text size="sm" c="dimmed">
            {ausencias.length} ausencia(s) {cobertura ? 'en el filtro' : 'vigente(s)'}
          </Text>
        }
      >
        <Select
          label="Filtrar por cobertura"
          placeholder="Todas"
          data={COBERTURA_OPTIONS}
          value={cobertura}
          onChange={setCobertura}
          clearable
          {...contained}
          style={{ minWidth: 260 }}
        />
      </Toolbar>

      {/* Los cuatro estados. Antes el error no se leía en ninguna parte: la
          consulta fallaba, `ausencias` se quedaba en su `[]` por defecto y la
          pantalla afirmaba que no había nadie ausente. Y el vacío solo se
          pintaba sin filtro; con uno puesto salía el «No hay registros para
          mostrar» genérico de la tabla, que no dice qué hacer. */}
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar quién está temporalmente ausente"
        errorHint="No quiere decir que no haya ausencias: no se pudieron consultar."
        onRetry={refetch}
        empty={!ausencias.length}
        emptyProps={{
          icon: IconUserOff,
          title: cobertura === 'cubiertas'
            ? 'Ninguna ausencia está cubierta'
            : cobertura === 'pendientes'
              ? 'No queda ninguna ausencia sin cubrir'
              : 'Nadie está temporalmente ausente',
          description: cobertura
            ? 'Quite el filtro para ver todas las ausencias vigentes hoy.'
            : 'Aquí aparecen las comisiones de servicios y licencias sin remuneración vigentes hoy, para cubrir el hueco con personal de apoyo.',
        }}
      >
        <SgthTable records={ausencias} columns={columns} minHeight={200} />
      </DataState>

      <ReintegroModal
        opened={aReintegrar !== null}
        onClose={() => setAReintegrar(null)}
        ausencia={aReintegrar}
      />
    </Stack>
  )
}
