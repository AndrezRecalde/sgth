'use client'

import { Alert, Button, Group, Text } from '@mantine/core'
import { IconCloud, IconCloudOff, IconHistory } from '@tabler/icons-react'
import { formatCuando } from '../utils/consultaMedica'
import type { useBorradorConsulta } from '../hooks/useBorradorConsulta'

interface Props {
  /** El controlador del borrador, tal cual lo devuelve su hook. */
  borrador: ReturnType<typeof useBorradorConsulta>
  /** `true` si se recuperó algo escrito antes y hay que decirlo. */
  recuperado: boolean
  /** Solo una consulta nueva guarda borrador; una ya registrada se corrige. */
  esConsultaNueva: boolean
  onDescartar: () => void
}

/**
 * Lo que el formulario cuenta sobre el borrador: que se recuperó algo de antes,
 * y si el guardado automático va o falla.
 *
 * Recuperar en silencio sería peor que no recuperar: el médico tiene que saber
 * que lo que ve en pantalla es de una sesión anterior, y poder tirarlo.
 */
export function BorradorConsultaAviso({
  borrador,
  recuperado,
  esConsultaNueva,
  onDescartar,
}: Props) {
  return (
    <>
      {recuperado && (
        <Alert
          icon={<IconHistory size={15} />}
          color="ocean"
          variant="light"
          p="xs"
        >
          <Group justify="space-between" wrap="nowrap" gap="xs">
            <Text size="xs">
              Se recuperó lo que estaba escrito
              {borrador.borrador?.updated_at && (
                <> (guardado {formatCuando(borrador.borrador.updated_at)})</>
              )}
              . Todavía no es parte de la historia clínica.
            </Text>
            <Button size="compact-xs" variant="subtle" onClick={onDescartar}>
              Descartar
            </Button>
          </Group>
        </Alert>
      )}

      {esConsultaNueva && borrador.estado !== 'inactivo' && (
        <Group gap={6} justify="flex-end">
          {borrador.estado === 'error' ? (
            <>
              <IconCloudOff size={13} color="var(--mantine-color-amber-6)" />
              <Text size="xs" c="amber">
                No se pudo guardar el borrador. Lo escrito sigue en pantalla.
              </Text>
            </>
          ) : (
            <>
              <IconCloud size={13} color="var(--mantine-color-slate-6)" />
              <Text size="xs" c="dimmed">
                {borrador.estado === 'guardando'
                  ? 'Guardando borrador…'
                  : `Borrador guardado ${borrador.guardadoEn
                      ? 'a las ' +
                        borrador.guardadoEn.toLocaleTimeString('es-EC', {
                          hour: '2-digit',
                          minute: '2-digit',
                        })
                      : ''}`}
              </Text>
            </>
          )}
        </Group>
      )}
    </>
  )
}
