'use client'

import { Stack, Text } from '@mantine/core'
import { SectionHeading, StatusBadge } from '@/components/ui'
import type { CondicionDeclarada } from '../services/contextoConsultaService'

interface Props {
  condicion: CondicionDeclarada | null | undefined
}

/**
 * La discapacidad y la enfermedad catastrófica que constan en el Expediente,
 * del servidor o de su familiar, en solo lectura.
 *
 * Las registra Talento Humano con su respaldo (carné CONADIS, certificado),
 * porque tienen efectos laborales. El médico las ve para atender —prioridad,
 * interacciones al recetar—, pero no las cambia: lo que él encuentre va a los
 * antecedentes de la historia clínica, que es su registro.
 *
 * Sin nada declarado no se pinta: un «ninguna» aquí se leería como «sano», y
 * solo quiere decir que el Expediente no tiene registros.
 */
export function SeccionCondicionDeclarada({ condicion }: Props) {
  if (!condicion) return null

  const { discapacidades, enfermedades } = condicion
  const hayAlgo = discapacidades.length > 0 || enfermedades.length > 0
    || condicion.discapacidad_sin_detalle || condicion.enfermedad_sin_detalle
  if (!hayAlgo) return null

  return (
    <>
      <SectionHeading title="Declarado en el Expediente" />
      <Stack gap={4}>
        {discapacidades.map((d, i) => (
          <Text key={`d-${i}`} size="xs">
            {d.etiqueta}
            {d.porcentaje != null && ` · ${d.porcentaje} %`}
            {d.grado && ` (${d.grado.toLowerCase()})`}
          </Text>
        ))}
        {condicion.discapacidad_sin_detalle && (
          <StatusBadge size="xs" tone="warning">Discapacidad sin detalle</StatusBadge>
        )}
        {enfermedades.map((e, i) => (
          <Text key={`e-${i}`} size="xs">
            {e.nombre}
            {e.codigo_cie10 && (
              <Text span size="xs" c="dimmed" ff="monospace"> {e.codigo_cie10}</Text>
            )}
          </Text>
        ))}
        {condicion.enfermedad_sin_detalle && (
          <StatusBadge size="xs" tone="warning">Enfermedad catastrófica sin detalle</StatusBadge>
        )}
        <Text size="xs" c="dimmed">
          Lo registra Talento Humano. Si encuentra otra cosa, anótela como antecedente.
        </Text>
      </Stack>
    </>
  )
}
