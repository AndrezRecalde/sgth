import { Badge, Group, Stack, Text } from '@mantine/core'
import { IconBriefcase } from '@tabler/icons-react'
import classes from './PuestoSellado.module.css'

interface Props {
  nombre?: string | null
  /** Código CIUO-08 heredado del cargo. */
  ciuo?: string | null
}

/**
 * El puesto que se evalúa, tal como lo tiene el sistema.
 *
 * Se muestra y no se edita porque no es un dato de la evaluación médica: viene
 * del aspirante en reclutamiento express, del expediente en las periódicas, o
 * de la convocatoria en los concursos formales. Pedirlo como texto libre hacía
 * que el mismo cargo apareciera escrito de tres maneras según quién llenara la
 * ficha, y que el código CIUO se inventara evaluación por evaluación.
 *
 * Si el cargo no tiene CIUO registrado se dice, en vez de callarlo: el hueco se
 * corrige una vez en Estructura y queda resuelto para todas sus fichas.
 */
export function PuestoSellado({ nombre, ciuo }: Props) {
  return (
    <div className={classes.panel}>
      <Group gap="sm" wrap="nowrap" align="flex-start">
        <span className={classes.icono} aria-hidden="true">
          <IconBriefcase size={18} stroke={1.7} />
        </span>

        <Stack gap={2} style={{ minWidth: 0, flex: 1 }}>
          <Text size="xs" fw={600} c="dimmed">
            Puesto de trabajo
          </Text>

          <Group gap="sm" wrap="wrap" align="center">
            <Text fw={600}>{nombre || 'Sin cargo registrado'}</Text>

            {ciuo ? (
              <Badge variant="light" size="sm" radius="sm">
                CIUO {ciuo}
              </Badge>
            ) : (
              <Badge variant="light" color="amber" size="sm" radius="sm">
                Sin código CIUO
              </Badge>
            )}
          </Group>

          <Text size="xs" c="dimmed">
            {ciuo
              ? 'Tomado del expediente. Se registra en la ficha tal como está hoy.'
              : 'El cargo no tiene código CIUO. Se asigna en Estructura › Cargos y lo heredarán todas sus fichas.'}
          </Text>
        </Stack>
      </Group>
    </div>
  )
}
