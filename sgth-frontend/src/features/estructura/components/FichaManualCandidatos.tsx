import { Group, Radio, Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { textoContenido } from '../constants/manualPuestos'
import type { CandidatoFicha } from '../services/manualPuestosService'

interface Props {
  candidatos: CandidatoFicha[]
  valor: number | null
  onChange: (puestoId: number) => void
}

/**
 * Los puestos de plantilla de la unidad de la ficha, del más parecido al
 * menos (P4, PR 12). Cada uno dice en qué coincide y qué tiene ya, que es lo
 * que la carga reemplaza. El que ya tiene otra ficha no se puede elegir.
 */
export function FichaManualCandidatos({ candidatos, valor, onChange }: Props) {
  return (
    <Radio.Group value={valor ? String(valor) : null} onChange={(v) => onChange(Number(v))}>
      <Stack gap="xs">
        {candidatos.map((c) => {
          const tiene = textoContenido(c.contenido)
          return (
            <Radio.Card key={c.puesto_id} value={String(c.puesto_id)} radius="md" p="sm" disabled={c.ficha_cargada !== null}
              style={c.ficha_cargada ? { opacity: 0.6, cursor: 'not-allowed' } : undefined}>
              <Group wrap="nowrap" align="flex-start" gap="sm">
                <Radio.Indicator disabled={c.ficha_cargada !== null} />
                <Stack gap={4} style={{ minWidth: 0 }}>
                  <Text size="sm" fw={600}>
                    {c.cargo}
                    <Text span size="xs" c="dimmed" fw={400}>
                      {' · '}{[c.grupo, c.grado ? `grado ${c.grado}` : null, c.plazas ? `${c.plazas} ${c.plazas === 1 ? 'plaza' : 'plazas'}` : null].filter(Boolean).join(', ')}
                    </Text>
                  </Text>
                  <Group gap={4}>
                    {c.coincide.rol && <StatusBadge size="xs" tone="success">Mismo cargo</StatusBadge>}
                    {c.coincide.grado && <StatusBadge size="xs" tone="success">Mismo grado</StatusBadge>}
                    {c.coincide.especialidad && <StatusBadge size="xs" tone="success">Misma especialidad</StatusBadge>}
                  </Group>
                  {c.ficha_cargada
                    ? <Text size="xs" c="dimmed">Ya tiene cargada la ficha «{c.ficha_cargada.archivo}».</Text>
                    : <Text size="xs" c="dimmed">{tiene ? `Ya tiene ${tiene}: se reemplaza.` : 'Todavía no tiene perfil.'}</Text>}
                </Stack>
              </Group>
            </Radio.Card>
          )
        })}
      </Stack>
    </Radio.Group>
  )
}
