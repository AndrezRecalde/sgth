'use client'

import Link from 'next/link'
import { Anchor, Group, Progress, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconCamera, IconCameraOff, IconUsers } from '@tabler/icons-react'
import { SectionCard, StatCard } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import type { TableroFotografias as Datos } from '../services/tableroService'
import { CifraEnlace } from './CifraEnlace'

const porcentaje = (parte: number, total: number) => (total ? Math.round((parte / total) * 100) : 0)

/**
 * Las fotos en el Inicio: cuántos activos tienen la suya y, por unidad, a
 * cuáles les falta. Sin foto no hay credencial.
 */
export function TableroFotografias({ datos }: { datos: Datos }) {
  return (
    <Stack gap="md">
      <SimpleGrid cols={{ base: 1, xs: 3 }} spacing="md">
        <StatCard label="Servidores activos" value={datos.activos} icon={IconUsers} />
        <StatCard
          label="Con foto"
          value={datos.con_foto}
          icon={IconCamera}
          hint={`${porcentaje(datos.con_foto, datos.activos)} % de los activos`}
        />
        <CifraEnlace
          label="Sin foto"
          value={datos.sin_foto}
          icon={IconCameraOff}
          tone={datos.sin_foto ? 'warning' : 'success'}
          hint="Sin foto no tienen credencial"
          href={ROUTES.COMUNICACION.FOTOGRAFIAS_SIN_FOTO()}
        />
      </SimpleGrid>

      <SectionCard title="Fotos por unidad" description="Primero las que más fotos deben. Cada una abre a quienes les falta.">
        {datos.por_unidad.length === 0 ? (
          <Text size="sm" c="dimmed">Todavía no hay servidores activos.</Text>
        ) : (
          <Stack gap="sm">
            {datos.por_unidad.map((u) => {
              const completa = u.con_foto === u.activos
              // «Otras N unidades» y «Sin unidad» no tienen a dónde llevar.
              const enlazable = u.unidad_id !== null && !completa

              return (
                <Stack key={`${u.unidad_id ?? u.unidad}`} gap={4}>
                  <Group justify="space-between" gap="xs" wrap="nowrap">
                    {enlazable ? (
                      <Anchor component={Link} href={ROUTES.COMUNICACION.FOTOGRAFIAS_SIN_FOTO(u.unidad_id ?? undefined)}
                        size="sm" lineClamp={1}>
                        {u.unidad}
                      </Anchor>
                    ) : (
                      <Text size="sm" lineClamp={1}>{u.unidad}</Text>
                    )}
                    <Text size="sm" fw={600} style={{ whiteSpace: 'nowrap' }}>{u.con_foto} de {u.activos}</Text>
                  </Group>
                  <Progress
                    value={porcentaje(u.con_foto, u.activos)}
                    size="sm"
                    radius="xl"
                    color={SEMANTIC_COLOR[completa ? 'success' : 'info']}
                    aria-label={`${u.unidad}: ${u.con_foto} de ${u.activos} con foto`}
                  />
                </Stack>
              )
            })}
          </Stack>
        )}
      </SectionCard>
    </Stack>
  )
}
