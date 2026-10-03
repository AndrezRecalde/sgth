'use client'

import { Group, Progress, SimpleGrid, Stack, Text } from '@mantine/core'
import { SectionCard, StatusBadge } from '@/components/ui'
import type { TableroSaludOcupacional } from '../services/tableroSaludOcupacionalService'
import { APTITUD_OPTIONS, TONO_APTITUD } from '../services/femoOptions'

interface Props {
  datos?: TableroSaludOcupacional
}

const CATEGORIA: Record<string, string> = {
  fisico: 'Físico', seguridad: 'De seguridad', quimico: 'Químico',
  biologico: 'Biológico', ergonomico: 'Ergonómico', psicosocial: 'Psicosocial',
}

/** Una fila «nombre … cifra» con su barra, relativa a la mayor de la lista. */
function Fila({ titulo, detalle, valor, maximo }: {
  titulo: React.ReactNode; detalle?: string; valor: number; maximo: number
}) {
  return (
    <Stack gap={2}>
      <Group justify="space-between" wrap="nowrap" gap="sm">
        <Group gap="xs" wrap="nowrap" miw={0}>
          {titulo}
          {detalle && <Text size="sm" lineClamp={1}>{detalle}</Text>}
        </Group>
        <Text size="sm" fw={600}>{valor}</Text>
      </Group>
      <Progress value={maximo ? (valor / maximo) * 100 : 0} size="sm" aria-hidden />
    </Stack>
  )
}

/** Lo emitido en el año: aptitud, diagnósticos y exposición a factores de riesgo. */
export function TableroSsoEvaluaciones({ datos }: Props) {
  const aptitud = datos?.aptitud ?? {}
  const totalAptitud = Object.values(aptitud).reduce((a, b) => a + b, 0)
  const diagnosticos = datos?.diagnosticos ?? []
  const factores = datos?.factores_riesgo ?? []

  return (
    <SimpleGrid cols={{ base: 1, lg: 3 }} spacing="md">
      <SectionCard
        title="Aptitud emitida"
        description={`${totalAptitud} evaluación${totalAptitud !== 1 ? 'es' : ''} cerrada${totalAptitud !== 1 ? 's' : ''} en ${datos?.anio ?? ''}`}
      >
        <Stack gap="sm">
          {APTITUD_OPTIONS.map(o => (
            <Fila
              key={o.value}
              titulo={<StatusBadge tone={TONO_APTITUD[o.value]}>{o.label}</StatusBadge>}
              valor={aptitud[o.value] ?? 0}
              maximo={totalAptitud}
            />
          ))}
        </Stack>
      </SectionCard>

      <SectionCard title="Diagnósticos más frecuentes" description="CIE-10 en las fichas FEMO del año">
        {diagnosticos.length === 0 ? (
          <Text size="sm" c="dimmed">Sin diagnósticos registrados en el año.</Text>
        ) : (
          <Stack gap="sm">
            {diagnosticos.map(d => (
              <Fila
                key={d.codigo}
                titulo={<Text size="sm" ff="monospace" fw={600}>{d.codigo}</Text>}
                detalle={d.descripcion}
                valor={d.total}
                maximo={diagnosticos[0].total}
              />
            ))}
          </Stack>
        )}
      </SectionCard>

      <SectionCard
        title="Exposición a factores de riesgo"
        description="Fichas del año con el factor marcado (sección G)"
      >
        {factores.length === 0 ? (
          <Text size="sm" c="dimmed">Sin factores de riesgo registrados en el año.</Text>
        ) : (
          <Stack gap="sm">
            {factores.map(f => (
              <Fila
                key={`${f.categoria}-${f.factor}`}
                titulo={<StatusBadge size="xs">{CATEGORIA[f.categoria] ?? f.categoria}</StatusBadge>}
                detalle={f.factor}
                valor={f.fichas}
                maximo={factores[0].fichas}
              />
            ))}
          </Stack>
        )}
      </SectionCard>
    </SimpleGrid>
  )
}
