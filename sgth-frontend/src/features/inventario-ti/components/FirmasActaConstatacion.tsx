'use client'

import { Stack, Switch, Text } from '@mantine/core'
import { DetailList, SectionCard } from '@/components/ui'
import { useConstatacionMutations } from '../hooks/useConstataciones'
import type { DetalleConstatacion } from '../services/constatacionesService'

interface Props {
  constatacion: DetalleConstatacion
  /** Quien gestiona el inventario corrige la participación mientras sigue en curso. */
  gestiona: boolean
}

/**
 * Quién firma el acta: quien constató, el jefe de TIC y, si participa en el
 * recorrido, el responsable de Activos Fijos y Bodega (5.2b). Este último sale
 * del organigrama y queda sellado al cerrar.
 */
export function FirmasActaConstatacion({ constatacion: c, gestiona }: Props) {
  const { activosFijos } = useConstatacionMutations(c.id)
  const enCurso = c.estado === 'en_curso'
  const f = c.firmante_activos_fijos

  return (
    <SectionCard title="Firmas del acta"
      description={c.participa_activos_fijos ? 'Participa Activos Fijos y Bodega: el acta lleva tres firmas.' : 'El acta lleva las dos firmas de TIC.'}
      actions={enCurso && gestiona ? (
        <Switch label="Participa Activos Fijos y Bodega" checked={c.participa_activos_fijos} disabled={activosFijos.isPending}
          onChange={(e) => activosFijos.mutate(e.currentTarget.checked)} />
      ) : undefined}>
      <DetailList columnas={3} items={[
        { label: 'Constató', value: <Firma nombre={c.iniciada_por} cargo="Unidad de Tecnologías de la Información" /> },
        { label: 'Revisó', value: <Firma nombre={null} cargo="Jefe de Tecnologías de la Información" /> },
        {
          label: 'Por Activos Fijos y Bodega',
          value: f
            ? <Firma nombre={f.nombre} cargo={f.cargo}
                nota={f.sellado ? 'Quedó así al cerrar la constatación.' : 'El jefe de la unidad hoy: queda sellado al cerrar.'} />
            : 'No participa',
        },
      ]} />
    </SectionCard>
  )
}

/** Sin nombre se firma a mano: el jefe de TIC siempre y un puesto vacante de Activos Fijos. */
function Firma({ nombre, cargo, nota }: { nombre: string | null; cargo: string | null; nota?: string }) {
  return (
    <Stack gap={0}>
      <Text size="sm" fw={500}>{nombre ?? 'Firma a mano'}</Text>
      {cargo && <Text size="xs" c="dimmed">{cargo}</Text>}
      {nota && <Text size="xs" c="dimmed">{nota}</Text>}
    </Stack>
  )
}
