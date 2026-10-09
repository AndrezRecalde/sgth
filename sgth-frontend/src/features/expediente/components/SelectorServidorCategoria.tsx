'use client'

import { Alert, SimpleGrid, Skeleton, Stack, Text, Tooltip, UnstyledButton } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { BuscarServidorSelect } from './BuscarServidorSelect'
import { useCatalogoAcciones } from '../hooks/useCatalogoAcciones'
import {
  familiaDisponible, familiaRequiereVinculo, familiasDelFormulario, type VinculoDelServidor,
} from '../utils/catalogoAcciones'
import type { CatalogoAccionesPersonal, FamiliaDelCatalogo, ServidorConRelaciones } from '@/types/api'
import { getApiErrorMessage } from '@/types/api'
import { SectionHeading } from '@/components/ui'
import { etiquetaNombramiento } from '../utils/tipoNombramientoOptions'
import classes from './SelectorServidorCategoria.module.css'

interface Props {
  servidor:          ServidorConRelaciones | null
  onServidorChange:  (servidor: ServidorConRelaciones | null) => void
  onFamiliaSeleccionada: (familia: FamiliaDelCatalogo) => void
}

function tooltipDeshabilitado(
  catalogo: CatalogoAccionesPersonal,
  familia: FamiliaDelCatalogo,
  pendienteVinculacion: boolean | null | undefined,
  tipoNombramiento?: string | null,
): string {
  const exigeVinculo = familiaRequiereVinculo(catalogo, familia.codigo)

  if (pendienteVinculacion === true) {
    return 'Este servidor aún no tiene un vínculo laboral vigente — registre primero su Ingreso y Vinculación.'
  }
  if (pendienteVinculacion === false && !exigeVinculo) {
    return 'Este servidor ya tiene un vínculo laboral vigente — no aplica un nuevo ingreso.'
  }
  if (pendienteVinculacion === false) {
    return `Esta acción no aplica al nombramiento vigente del servidor (${etiquetaNombramiento(tipoNombramiento)}).`
  }
  return 'No se pudo determinar el estado de vínculo de este servidor.'
}

export function SelectorServidorCategoria({ servidor, onServidorChange, onFamiliaSeleccionada }: Props) {
  const catalogo = useCatalogoAcciones()

  const pendienteVinculacion = servidor?.pendiente_vinculacion
  const tipoNombramiento = servidor?.contrato_vigente?.tipo_nombramiento

  // Con `pendiente_vinculacion` en null no se sabe si tiene vínculo, y sin
  // saberlo no se ofrece nada: no hay con qué decidir.
  const vinculo: VinculoDelServidor = {
    sinVinculo: pendienteVinculacion === true,
    tipoNombramiento: pendienteVinculacion === false ? tipoNombramiento : null,
  }

  const datos = catalogo.data
  const familias = datos ? familiasDelFormulario(datos) : []

  const ningunaAplica = !!datos && familias.every(
    (f) => !familiaDisponible(datos, f.codigo, vinculo),
  )

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

          {catalogo.isError && (
            <Alert variant="light" color="red" icon={<IconAlertTriangle size={16} />}>
              {getApiErrorMessage(catalogo.error, 'No se pudo cargar el catálogo de acciones de personal.')}
            </Alert>
          )}

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

          {/* Tarjetas en gris con su tooltip no son una respuesta: si ninguna
              aplica, hay que decirlo de frente y nombrar el motivo. */}
          {pendienteVinculacion === false && ningunaAplica && (
            <Alert
              variant="light"
              color="amber"
              icon={<IconAlertTriangle size={16} />}
            >
              Ninguna acción de personal aplica al nombramiento vigente de este
              servidor ({etiquetaNombramiento(tipoNombramiento)}). Las reglas de
              elegibilidad las fija Talento Humano por tipo de nombramiento.
            </Alert>
          )}

          <SimpleGrid cols={{ base: 1, sm: 2, md: 3 }} spacing="sm">
            {catalogo.isPending && Array.from({ length: 6 }, (_, i) => (
              <Skeleton key={i} height={52} radius="md" />
            ))}

            {datos && familias.map((familia) => {
              const habilitada = familiaDisponible(datos, familia.codigo, vinculo)

              /*
              | Las no elegibles llevan `aria-disabled` y no `disabled`, y siguen
              | en el orden de tabulación a propósito: el motivo vive en el
              | tooltip, y un botón deshabilitado no se puede enfocar, así que
              | quien navega con teclado no tenía forma de leerlo. El `Tooltip`
              | va directamente sobre el botón y con `focus: true`, porque el
              | ajuste de Mantine por defecto solo lo abre al pasar el ratón.
              */
              const boton = (
                <UnstyledButton
                  key={familia.codigo}
                  onClick={() => { if (habilitada) onFamiliaSeleccionada(familia) }}
                  aria-disabled={!habilitada || undefined}
                  className={habilitada
                    ? classes.tarjeta
                    : `${classes.tarjeta} ${classes.deshabilitada}`}
                >
                  <Text size="sm" fw={500}>{familia.etiqueta}</Text>
                </UnstyledButton>
              )

              return habilitada ? boton : (
                <Tooltip
                  key={familia.codigo}
                  label={tooltipDeshabilitado(datos, familia, pendienteVinculacion, tipoNombramiento)}
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
