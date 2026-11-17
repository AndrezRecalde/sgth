import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { SectionCard } from '@/components/ui'
import { dineroConMiles } from '@/lib/dinero'
import type { MasaSalarial } from '@/types/api'

/** Lo que se abre desde aquí en el cajón. */
export type CajonAparte = 'sin_remuneracion_en_la_fecha' | 'sin_remuneracion_registrada' | 'subrogaciones' | 'sin_vinculo'

interface Props {
  datos:   MasaSalarial
  onAbrir: (cajon: CajonAparte) => void
}

const plural = (n: number, una: string, varias: string) => `${n} ${n === 1 ? una : varias}`

function Renglon({ children, onVer }: { children: React.ReactNode; onVer: () => void }) {
  return (
    <Group justify="space-between" wrap="nowrap" gap="md">
      <Text size="sm">{children}</Text>
      <Button variant="subtle" size="compact-sm" onClick={onVer}>Ver</Button>
    </Group>
  )
}

/**
 * Lo que no entra en el total (D4, D5) y lo que falta completar para que el
 * total sea cierto. Sin nada de eso, no se dibuja.
 */
export function MasaSalarialAparte({ datos, onAbrir }: Props) {
  const { sin_remuneracion_en_la_fecha: sinLaFecha, subrogaciones } = datos.aparte
  const { vinculos_sin_remuneracion: sinRemuneracion, vinculos_sin_unidad: sinUnidad, servidores_sin_vinculo: sinVinculo } = datos.por_completar
  const fuera = sinLaFecha.personas > 0 || subrogaciones.casos > 0
  const porCompletar = sinRemuneracion > 0 || sinUnidad > 0 || (sinVinculo?.length ?? 0) > 0

  if (!fuera && !porCompletar) return null

  return (
    <Stack gap="md">
      {fuera && (
        <SectionCard title="Fuera del total" description="Se muestra aparte para no inflar ni duplicar la cifra.">
          <Stack gap="xs">
            {sinLaFecha.personas > 0 && (
              <Renglon onVer={() => onAbrir('sin_remuneracion_en_la_fecha')}>
                {plural(sinLaFecha.personas, 'persona está', 'personas están')} sin remuneración ese día, por licencia,
                comisión o suspensión: {dineroConMiles(sinLaFecha.mensual)} al mes que no se pagan. A quien las reemplaza sí se le suma.
              </Renglon>
            )}
            {subrogaciones.casos > 0 && (
              <Renglon onVer={() => onAbrir('subrogaciones')}>
                {plural(subrogaciones.casos, 'subrogación o encargo', 'subrogaciones y encargos')}:{' '}
                {dineroConMiles(subrogaciones.mensual)} al mes de diferencia con la remuneración propia.
              </Renglon>
            )}
          </Stack>
        </SectionCard>
      )}

      {porCompletar && (
        <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />} title="Datos por completar">
          <Stack gap="xs">
            {sinRemuneracion > 0 && (
              <Renglon onVer={() => onAbrir('sin_remuneracion_registrada')}>
                {plural(sinRemuneracion, 'vínculo no tiene', 'vínculos no tienen')} remuneración registrada y no suman.
              </Renglon>
            )}
            {(sinVinculo?.length ?? 0) > 0 && (
              <Renglon onVer={() => onAbrir('sin_vinculo')}>
                {plural(sinVinculo?.length ?? 0, 'servidor activo no tiene', 'servidores activos no tienen')} un vínculo vigente y no suman.
              </Renglon>
            )}
            {sinUnidad > 0 && (
              <Text size="sm">
                {plural(sinUnidad, 'vínculo no tiene', 'vínculos no tienen')} unidad: suman en el total, pero no en la vista por unidad.
              </Text>
            )}
          </Stack>
        </Alert>
      )}
    </Stack>
  )
}
