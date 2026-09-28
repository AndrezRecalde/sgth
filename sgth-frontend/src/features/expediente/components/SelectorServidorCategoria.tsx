'use client'

import { Alert, SimpleGrid, Stack, Text, Tooltip, UnstyledButton } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { BuscarServidorSelect } from './BuscarServidorSelect'
import {
  CATEGORIAS_ACCION_PERSONAL, categoriaHabilitada,
} from '../utils/categoriasAccionPersonal'
import type { AccionTipo } from '../utils/taxonomiaAccionPersonal'
import type { ServidorConRelaciones } from '@/types/api'
import { SectionHeading } from '@/components/ui'
import classes from './SelectorServidorCategoria.module.css'

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
        onSelect={onServidorChange}
      />

      {servidor && (
        <Stack gap="xs">
          <SectionHeading title="Categoría de la acción de personal" />

          {/* Un aviso es un aviso, no una etiqueta de estado: `StatusBadge`
              sirve para el estado de un registro o para una categoría, y esto es
              una frase que explica por qué no se puede continuar. */}
          {pendienteVinculacion == null && (
            <Alert
              variant="light"
              color="amber"
              icon={<IconAlertTriangle size={16} />}
            >
              No se pudo determinar si este servidor tiene vínculo vigente, así
              que ninguna categoría está disponible. Vuelva a abrirlo desde el
              buscador; si sigue igual, revise su expediente.
            </Alert>
          )}

          <SimpleGrid cols={{ base: 1, sm: 2, md: 3 }} spacing="sm">
            {CATEGORIAS_ACCION_PERSONAL.map((categoria) => {
              const habilitada = categoriaHabilitada(categoria, pendienteVinculacion)

              /*
              | Las no elegibles llevan `aria-disabled` y no `disabled`, y siguen
              | en el orden de tabulación a propósito: el motivo vive en el
              | tooltip, y un botón deshabilitado no se puede enfocar, así que
              | quien navega con teclado no tenía forma de leerlo. El `Tooltip`
              | va directamente sobre el botón —antes envolvía un `Box`, que no
              | es enfocable— y con `focus: true`, porque el ajuste de Mantine
              | por defecto solo lo abre al pasar el ratón.
              */
              const boton = (
                <UnstyledButton
                  key={categoria.value}
                  onClick={() => handleClickCategoria(categoria.value, habilitada)}
                  aria-disabled={!habilitada || undefined}
                  className={habilitada
                    ? classes.tarjeta
                    : `${classes.tarjeta} ${classes.deshabilitada}`}
                >
                  <Text size="sm" fw={500}>{categoria.label}</Text>
                </UnstyledButton>
              )

              return habilitada ? boton : (
                <Tooltip
                  key={categoria.value}
                  label={tooltipDeshabilitado(categoria.value, pendienteVinculacion)}
                  events={{ hover: true, focus: true, touch: true }}
                  multiline
                  w={260}
                  withArrow
                >
                  {boton}
                </Tooltip>
              )
            })}
          </SimpleGrid>
        </Stack>
      )}
    </Stack>
  )
}
