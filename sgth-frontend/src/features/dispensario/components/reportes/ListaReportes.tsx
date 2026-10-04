'use client'

import { Card, Group, Stack, Text, UnstyledButton } from '@mantine/core'
import { IconLock } from '@tabler/icons-react'
import type { ReporteDisponible } from '../../services/reportesDispensarioService'
import classes from './ListaReportes.module.css'

interface Props {
  reportes:   ReporteDisponible[]
  activo:     string | null
  onElegir:   (clave: string) => void
}

/**
 * El catálogo de reportes que este usuario puede sacar, agrupado por área en
 * el orden en que llega. Cada reporte es un botón: se llega con Tab, se elige
 * con Enter, y el elegido se anuncia como actual.
 */
export function ListaReportes({ reportes, activo, onElegir }: Props) {
  const areas = [...new Set(reportes.map((r) => r.area))]

  return (
    <Card withBorder radius="lg" p={0}>
      {areas.map((area) => (
        <Stack key={area} gap={0} className={classes.area}>
          <Text size="xs" fw={700} tt="uppercase" c="dimmed" className={classes.titulo}>
            {area}
          </Text>
          {reportes.filter((r) => r.area === area).map((r) => (
            <UnstyledButton
              key={r.clave}
              className={`${classes.reporte} mantine-focus-auto`}
              data-activo={r.clave === activo || undefined}
              aria-current={r.clave === activo ? 'true' : undefined}
              onClick={() => onElegir(r.clave)}
            >
              <Group gap={6} wrap="nowrap">
                <Text size="sm" fw={600}>{r.titulo}</Text>
                {/* Con nombres de pacientes: se dice antes de sacarlo. */}
                {r.nominal && <IconLock size={14} title="Lleva datos de pacientes" />}
              </Group>
              <Text size="xs" c="dimmed">{r.descripcion}</Text>
            </UnstyledButton>
          ))}
        </Stack>
      ))}
    </Card>
  )
}
