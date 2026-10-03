'use client'

import { useRouter } from 'next/navigation'
import { Button, Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconArrowRight } from '@tabler/icons-react'
import { SectionCard, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import type { PanoramaDispensario } from '../../services/panoramaService'

interface Props {
  panorama?: PanoramaDispensario
}

/** «1 atención», «3 atenciones». */
const plural = (n: number, uno: string, varios: string) => `${n} ${n === 1 ? uno : varios}`

/** Una fila «nombre … cifra» de una lista corta de tablero. */
function Fila({ nombre, valor, sufijo }: { nombre: React.ReactNode; valor: number; sufijo?: string }) {
  return (
    <Group justify="space-between" gap="xs" wrap="nowrap">
      <Group gap="xs" wrap="nowrap" miw={0}>{nombre}</Group>
      <Group gap={4} wrap="nowrap" align="baseline">
        <Text size="sm" fw={600}>{valor}</Text>
        {sufijo && <Text size="xs" c="dimmed">{sufijo}</Text>}
      </Group>
    </Group>
  )
}

/**
 * Las áreas que el tablero no veía: enfermería, los reposos médicos y salud
 * ocupacional (lo esencial; el detalle vive en su propio tablero).
 */
export function TableroAreas({ panorama }: Props) {
  const router = useRouter()
  const enf = panorama?.enfermeria
  const rep = panorama?.reposos
  const so  = panorama?.salud_ocupacional

  return (
    <SimpleGrid cols={{ base: 1, lg: 3 }} spacing="md">
      <SectionCard title="Enfermería" description={`${plural(enf?.total ?? 0, 'atención', 'atenciones')} en el período, por servicio.`}>
        {!enf?.por_servicio.length ? (
          <Text size="sm" c="dimmed">Sin atenciones de enfermería en el período.</Text>
        ) : (
          <Stack gap="xs">
            {enf.por_servicio.slice(0, 6).map((s) => (
              <Fila key={s.servicio} nombre={<Text size="sm" lineClamp={1}>{s.servicio}</Text>} valor={s.total} />
            ))}
          </Stack>
        )}
      </SectionCard>

      {/* El lado médico del ausentismo que mira Talento Humano. */}
      <SectionCard
        title="Reposos médicos"
        description={`${plural(rep?.certificados ?? 0, 'certificado', 'certificados')} · ${plural(rep?.dias ?? 0, 'día', 'días')} de reposo en el período.`}
      >
        {!rep?.diagnosticos.length ? (
          <Text size="sm" c="dimmed">Sin certificados de reposo en el período.</Text>
        ) : (
          <Stack gap="xs">
            {rep.diagnosticos.map((d) => (
              <Fila
                key={d.codigo}
                nombre={<>
                  <StatusBadge variant="outline" ff="monospace">{d.codigo}</StatusBadge>
                  <Text size="xs" lineClamp={1}>{d.descripcion}</Text>
                </>}
                valor={d.dias}
                sufijo={`${d.dias === 1 ? 'día' : 'días'} · ${plural(d.total, 'cert.', 'cert.')}`}
              />
            ))}
          </Stack>
        )}
      </SectionCard>

      <SectionCard
        title="Salud ocupacional"
        description="Hoy, sin importar el período."
        actions={
          <Button variant="subtle" size="xs" rightSection={<IconArrowRight size={14} />}
            onClick={() => router.push(ROUTES.SALUD.SSO_TABLERO)}>
            Ver tablero
          </Button>
        }
      >
        <Stack gap="xs">
          <Fila nombre={<Text size="sm">Evaluaciones por atender</Text>} valor={so?.por_atender ?? 0} />
          <Fila
            nombre={<Text size="sm">Pasadas de su fecha límite</Text>}
            valor={so?.vencidas ?? 0}
          />
          <Fila nombre={<Text size="sm">Retiros por evaluar</Text>} valor={so?.retiros ?? 0} />
          <Fila
            nombre={<Text size="sm">Plantilla sin evaluación vigente</Text>}
            valor={(so?.cobertura_vencida ?? 0) + (so?.cobertura_sin_evaluacion ?? 0)}
            sufijo={`de ${so?.plantilla ?? 0}`}
          />
        </Stack>
      </SectionCard>
    </SimpleGrid>
  )
}
