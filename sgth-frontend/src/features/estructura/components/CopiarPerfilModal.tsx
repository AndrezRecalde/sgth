'use client'

import { useState } from 'react'
import { Alert, Card, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { DataState, DetailList, ModalFooter, SgthModal } from '@/components/ui'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { textoContenido } from '../constants/manualPuestos'
import { useCopiarPerfil, useVistaPreviaCopia } from '../hooks/usePerfilesPuestos'
import type { VistaPreviaCopia } from '../services/perfilesPuestosService'
import { BuscarPuestoSelect } from './BuscarPuestoSelect'

interface Props {
  opened:   boolean
  onClose:  () => void
  puestoId: number
}

/**
 * Copiar el perfil de otro puesto (docs/diseno-manual-de-puestos.md, P6,
 * PR 15): así se describen los puestos que no tienen ficha en el manual (TH,
 * D10). Antes de copiar, el modal dice qué trae y qué reemplaza.
 */
export function CopiarPerfilModal({ opened, onClose, puestoId }: Props) {
  return (
    <SgthModal opened={opened} onClose={onClose} title="Copiar el perfil de otro puesto" size="lg">
      {/* Montado solo abierto: al volver a abrirlo empieza sin puesto elegido. */}
      {opened && <Contenido puestoId={puestoId} onClose={onClose} />}
    </SgthModal>
  )
}

function Contenido({ puestoId, onClose }: { puestoId: number; onClose: () => void }) {
  const [origenId, setOrigenId] = useState<number | null>(null)
  const vista = useVistaPreviaCopia(puestoId, origenId)
  const copiar = useCopiarPerfil(puestoId)
  const datos = origenId !== null ? vista.data : undefined

  return (
    <Stack gap="md">
      <Text size="sm" c="dimmed">
        Trae la misión, las relaciones, los requisitos, la capacitación, las actividades y las competencias de
        otro puesto, y reemplaza los de este. El cargo, la unidad, el grado y el subproceso siguen siendo los de
        este puesto.
      </Text>

      <BuscarPuestoSelect
        label="Copiar de"
        description="Un puesto con el perfil ya descrito, de preferencia del mismo rol."
        required
        value={origenId}
        onChange={(id) => { setOrigenId(id); copiar.reset() }}
        error={erroresDeCampo(copiar.error)?.origen_id}
      />

      {origenId !== null && (
        <DataState loading={vista.isLoading} error={vista.error} errorTitle="No se pudo leer el perfil de ese puesto" skeletonRows={3}>
          {datos && <VistaPrevia datos={datos} />}
        </DataState>
      )}

      <ModalFooter
        onCancel={onClose}
        onSubmit={() => { if (origenId !== null) copiar.mutate(origenId, { onSuccess: onClose }) }}
        submitLabel="Copiar y reemplazar"
        submitting={copiar.isPending}
        submitDisabled={!datos || datos.impedimento !== null}
      />
    </Stack>
  )
}

function VistaPrevia({ datos }: { datos: VistaPreviaCopia }) {
  const { origen, impedimento } = datos

  return (
    <Stack gap="sm">
      {impedimento && (
        <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>{impedimento}</Alert>
      )}

      <Card withBorder radius="md" p="sm">
        <Text size="sm" fw={500}>{origen.cargo ?? `Puesto ${origen.id}`}</Text>
        <Text size="xs" c="dimmed">{[origen.unidad, origen.grado ? `grado ${origen.grado}` : null].filter(Boolean).join(' · ') || '—'}</Text>
        <Text size="sm" mt="xs" lineClamp={4} c={origen.mision ? undefined : 'dimmed'}>
          {origen.mision ?? 'Sin misión.'}
        </Text>
      </Card>

      {/* Si no se puede copiar, el detalle de lo que se copiaría sobra. */}
      {!impedimento && <Detalle datos={datos} />}
    </Stack>
  )
}

function Detalle({ datos }: { datos: VistaPreviaCopia }) {
  const { copia, omitidas, ajuste_de_nivel: ajuste, reemplaza } = datos
  const fuera = [
    omitidas.actividades > 0 ? `${omitidas.actividades} ${omitidas.actividades === 1 ? 'actividad desactivada' : 'actividades desactivadas'}` : null,
    omitidas.competencias > 0 ? `${omitidas.competencias} ${omitidas.competencias === 1 ? 'competencia retirada' : 'competencias retiradas'} del diccionario` : null,
  ].filter((p): p is string => p !== null)

  return (
    <>
      <DetailList
        columnas={1}
        items={[
          { label: 'Se copia', value: textoContenido(copia) || 'Solo los textos del perfil.' },
          { label: 'Se reemplaza', value: textoContenido(reemplaza) || 'Nada: este puesto todavía no tiene perfil.' },
        ]}
      />

      {fuera.length > 0 && (
        <Text size="xs" c="dimmed">No se copian {fuera.join(' ni ')}.</Text>
      )}

      {ajuste && (
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          {ajuste.competencias === 1 ? 'Una competencia exigida' : `${ajuste.competencias} competencias exigidas`} en
          nivel {ajuste.de} {ajuste.competencias === 1 ? 'pasa' : 'pasan'} a {ajuste.a}, el que corresponde al grado
          de este puesto. Las que el otro puesto exige en otro nivel se quedan como están.
        </Alert>
      )}
    </>
  )
}
