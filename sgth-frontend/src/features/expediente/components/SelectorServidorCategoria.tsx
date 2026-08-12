'use client'

import { Stack, SimpleGrid, UnstyledButton, Text, Tooltip, Box, Badge } from '@mantine/core'
import { BuscarServidorSelect } from './BuscarServidorSelect'
import {
  CATEGORIAS_ACCION_PERSONAL, categoriaHabilitada,
} from '../utils/categoriasAccionPersonal'
import type { AccionTipo } from '../utils/taxonomiaAccionPersonal'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  servidor:          ServidorConRelaciones | null
  onServidorChange:  (servidor: ServidorConRelaciones | null) => void
  onCategoriaSeleccionada: (categoria: AccionTipo) => void
}

function tooltipDeshabilitado(categoria: string, pendienteVinculacion: boolean | null | undefined): string {
  if (pendienteVinculacion === true) {
    return 'Este servidor aún no tiene un vínculo laboral vigente — registre primero su Ingreso y Vinculación.'
  }
  if (pendienteVinculacion === false && categoria === 'ingreso') {
    return 'Este servidor ya tiene un vínculo laboral vigente — no aplica un nuevo ingreso.'
  }
  return 'No se pudo determinar el estado de vínculo de este servidor.'
}

export function SelectorServidorCategoria({ servidor, onServidorChange, onCategoriaSeleccionada }: Props) {
  const pendienteVinculacion = servidor?.pendiente_vinculacion

  /**
   * Todas las categorías abren el mismo formulario con el tipo ya fijado.
   * Antes solo el ingreso lo hacía y el resto avisaba "formulario en
   * construcción" — un mensaje que había quedado viejo: esos formularios ya
   * existían y se llegaba a ellos desde el expediente del servidor.
   */
  const handleClickCategoria = (categoriaValue: AccionTipo, habilitada: boolean) => {
    if (!habilitada) return

    onCategoriaSeleccionada(categoriaValue)
  }

  return (
    <Stack gap="lg">
      <BuscarServidorSelect
        label="Buscar servidor"
        value={servidor?.id ?? null}
        onChange={(id) => { if (!id) onServidorChange(null) }}
        onSelect={(srv) => onServidorChange(srv as ServidorConRelaciones)}
      />

      {servidor && (
        <Stack gap="xs">
          <Text size="sm" fw={500}>
            Seleccione la categoría de acción de personal
          </Text>

          {pendienteVinculacion == null && (
            <Badge color="yellow" variant="light" size="sm" style={{ alignSelf: 'flex-start' }}>
              No se pudo determinar el estado de vínculo de este servidor
            </Badge>
          )}

          <SimpleGrid cols={{ base: 1, sm: 2, md: 3 }} spacing="sm">
            {CATEGORIAS_ACCION_PERSONAL.map((categoria) => {
              const habilitada = categoriaHabilitada(categoria, pendienteVinculacion)

              const boton = (
                <UnstyledButton
                  onClick={() => handleClickCategoria(categoria.value, habilitada)}
                  data-disabled={!habilitada || undefined}
                  style={{
                    border: '1px solid var(--mantine-color-default-border)',
                    borderRadius: 'var(--mantine-radius-md)',
                    padding: 'var(--mantine-spacing-sm)',
                    opacity: habilitada ? 1 : 0.5,
                    cursor: habilitada ? 'pointer' : 'not-allowed',
                  }}
                >
                  <Text size="sm" fw={500}>{categoria.label}</Text>
                </UnstyledButton>
              )

              return habilitada ? (
                <Box key={categoria.value}>{boton}</Box>
              ) : (
                <Tooltip
                  key={categoria.value}
                  label={tooltipDeshabilitado(categoria.value, pendienteVinculacion)}
                  multiline
                  w={260}
                  withArrow
                >
                  <Box>{boton}</Box>
                </Tooltip>
              )
            })}
          </SimpleGrid>
        </Stack>
      )}
    </Stack>
  )
}
