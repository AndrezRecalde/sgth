import { SimpleGrid, Stack, Text } from '@mantine/core'
import { SectionCard } from '@/components/ui'
import type { MetricasMes } from '../services/tableroService'

interface Props {
  mes: MetricasMes
  alcance: 'todos' | 'propios'
  porConfirmar: number
}

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

function Cifra({ etiqueta, valor, detalle }: { etiqueta: string; valor: string; detalle?: string }) {
  return (
    <Stack gap={0}>
      <Text size="xs" c="dimmed">{etiqueta}</Text>
      <Text size="lg" fw={700}>{valor}</Text>
      {detalle && <Text size="xs" c="dimmed">{detalle}</Text>}
    </Stack>
  )
}

const horas = (h: number) => `${h.toLocaleString('es-EC', { maximumFractionDigits: 1 })} h`

/**
 * Cómo va el mes: lo que entró, lo que se resolvió y cuánto tardó, en horas
 * hábiles y sin contar la espera del usuario, como el SLA.
 */
export function CifrasDelMes({ mes, alcance, porConfirmar }: Props) {
  const nombreMes = MESES[new Date().getMonth()]

  return (
    <SectionCard
      title={`Lo que va de ${nombreMes}`}
      description={alcance === 'todos' ? 'Toda la mesa de ayuda.' : 'Sus tickets: los que tiene o tuvo a su nombre.'}
    >
      <SimpleGrid cols={{ base: 2, sm: 3, lg: 6 }} spacing="md">
        <Cifra etiqueta="Abiertos" valor={String(mes.abiertos)} />
        <Cifra etiqueta="Resueltos" valor={String(mes.resueltos)} detalle={porConfirmar ? `${porConfirmar} por confirmar` : undefined} />
        <Cifra etiqueta="Dentro del plazo" valor={mes.cumplimiento_sla === null ? '—' : `${mes.cumplimiento_sla} %`} />
        <Cifra etiqueta="Tiempo de resolución" valor={mes.horas_resolucion === null ? '—' : horas(mes.horas_resolucion)} detalle="Promedio, horas hábiles" />
        <Cifra etiqueta="Satisfacción" valor={mes.satisfaccion === null ? '—' : `${mes.satisfaccion.toLocaleString('es-EC')} / 5`} />
        <Cifra etiqueta="Encuestas" valor={String(mes.encuestas)} />
      </SimpleGrid>
    </SectionCard>
  )
}
