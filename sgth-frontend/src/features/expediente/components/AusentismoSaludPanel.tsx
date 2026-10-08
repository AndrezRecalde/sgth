'use client'

import { Anchor, Group, Skeleton, Text } from '@mantine/core'
import { IconExternalLink } from '@tabler/icons-react'
import Link from 'next/link'
import { DataState, SectionCard } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAusentismoSalud } from '../hooks/useAusentismoSalud'

interface Props {
  servidorId: number
}

/**
 * Cuántas veces pidió permiso por enfermedad, como indicador de salud
 * ocupacional.
 *
 * Son **permisos, no días**: el sistema guarda cada permiso con su hora de
 * inicio y fin, no como un rango de días, y convertirlo exigiría asumir una
 * jornada. Y mide **episodios, no tiempo fuera**: una enfermedad de diez días
 * seguidos puede ser un solo permiso. De ahí que la cifra se rotule «permisos»
 * y nunca «ausencias».
 *
 * Los reposos del dispensario van aparte: desde el 2026-10-08 son
 * certificados médicos y no permisos, y esos sí traen sus días.
 *
 * Sin fechas ni motivos: el detalle vive en el módulo de Permisos, y la
 * observación de un permiso es texto libre que puede llevar un diagnóstico.
 */
export function AusentismoSaludPanel({ servidorId }: Props) {
  const { data, isLoading, error, refetch } = useAusentismoSalud(servidorId)

  return (
    <SectionCard
      title="Ausentismo por salud"
      actions={
        <Anchor
          component={Link}
          href={ROUTES.SGTH.ASISTENCIA_PERMISOS}
          size="sm"
        >
          <Group gap={4} wrap="nowrap">
            Ver en Permisos
            <IconExternalLink size={14} />
          </Group>
        </Anchor>
      }
    >
      {isLoading ? (
        <Skeleton height={44} radius="md" />
      ) : error ? (
        // Sin esto, un fallo pintaba «0 permisos por enfermedad».
        <DataState
          loading={false}
          error={error}
          errorTitle="No se pudo consultar el ausentismo"
          errorHint="No quiere decir que no tenga permisos por enfermedad: no se pudo consultar."
          onRetry={() => refetch()}
        />
      ) : (
        <>
          <Text size="xl" fw={700}>
            {data?.permisos ?? 0}
            <Text span size="sm" fw={400} c="dimmed" ml={6}>
              {data?.permisos === 1 ? 'permiso por enfermedad' : 'permisos por enfermedad'}
            </Text>
          </Text>
          <Text size="xl" fw={700} mt="xs">
            {data?.reposos ?? 0}
            <Text span size="sm" fw={400} c="dimmed" ml={6}>
              {data?.reposos === 1 ? 'reposo médico' : 'reposos médicos'}
              {!!data?.dias_reposo && ` · ${data.dias_reposo} ${data.dias_reposo === 1 ? 'día' : 'días'}`}
            </Text>
          </Text>
          <Text size="xs" c="dimmed" mt={4}>
            En los últimos {data?.meses ?? 12} meses. Los permisos cuentan cuántas
            veces se concedieron, no cuánto tiempo estuvo fuera; los reposos son
            los certificados del dispensario aprobados, con sus días calendario.
          </Text>
        </>
      )}
    </SectionCard>
  )
}
