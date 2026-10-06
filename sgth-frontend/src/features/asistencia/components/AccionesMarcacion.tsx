'use client'

import { Button, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconBriefcase, IconLogin, IconLogout, IconSoup } from '@tabler/icons-react'
import type { AccionMarcacion, ClaveAccion } from '../utils/marcacionOnline'

const ICONO: Record<ClaveAccion, typeof IconLogin> = {
  Entrada:         IconLogin,
  AlmuerzoSalida:  IconSoup,
  AlmuerzoRetorno: IconBriefcase,
  Salida:          IconLogout,
}

interface Props {
  acciones: AccionMarcacion[]
  siguiente: AccionMarcacion | null
  /** La acción que se está registrando ahora, si hay una. */
  enCurso: ClaveAccion | null
  /** Sin ubicación posible (navegador sin GPS o permiso denegado) no se marca. */
  bloqueadas: boolean
  onMarcar: (accion: AccionMarcacion) => void
}

/**
 * Un botón por acción del día. La siguiente esperada se destaca; las demás
 * siguen disponibles, pero quien las pulsa recibe una confirmación (la decide
 * la pantalla con `motivoParaConfirmar`).
 */
export function AccionesMarcacion({ acciones, siguiente, enCurso, bloqueadas, onMarcar }: Props) {
  return (
    <SimpleGrid cols={2} spacing="sm">
      {acciones.map((accion) => {
        const Icono = ICONO[accion.clave]
        const destacada = siguiente?.clave === accion.clave

        return (
          <Button
            key={accion.clave}
            h={88}
            variant={destacada ? 'light' : 'default'}
            loading={enCurso === accion.clave}
            disabled={bloqueadas || (enCurso !== null && enCurso !== accion.clave)}
            onClick={() => onMarcar(accion)}
            aria-describedby={destacada ? 'marcacion-siguiente' : undefined}
          >
            <Stack gap={4} align="center">
              <Icono size={20} stroke={1.5} />
              <Text size="xs" fw={600} ta="center" lh={1.2}>
                {accion.etiqueta}
              </Text>
            </Stack>
          </Button>
        )
      })}
      {siguiente && (
        <Text id="marcacion-siguiente" size="xs" c="dimmed" style={{ gridColumn: '1 / -1' }}>
          La siguiente marcación esperada es «{siguiente.etiqueta}».
        </Text>
      )}
    </SimpleGrid>
  )
}
