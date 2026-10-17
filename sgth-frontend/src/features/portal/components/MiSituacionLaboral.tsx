'use client'

import { DetailList, SectionCard } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { REGIMEN_LABELS } from '@/lib/regimen'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  servidor:     ServidorConRelaciones
  /** La etiqueta del nombramiento viene del perfil de la sesión, ya resuelta. */
  nombramiento: string | null | undefined
}

/**
 * El puesto y la unidad de hoy, en «Mi perfil». En el expediente esto vive en
 * la pestaña Laboral, con el historial de vínculos, que es de Talento Humano;
 * aquí basta la foto de hoy.
 */
export function MiSituacionLaboral({ servidor, nombramiento }: Props) {
  // Los años de servicio no van aquí: ya los da Identificación, debajo.
  const items = [
    {
      label: 'Puesto',
      value: servidor.contrato_vigente?.puesto?.cargo?.nombre ?? servidor.puesto?.cargo?.nombre,
      ancho: true,
    },
    {
      label: 'Unidad administrativa',
      value: servidor.contrato_vigente?.unidad_administrativa?.nombre
        ?? servidor.unidad_administrativa?.nombre,
      ancho: true,
    },
    { label: 'Régimen laboral', value: REGIMEN_LABELS[servidor.regimen_laboral ?? ''] ?? servidor.regimen_laboral },
    { label: 'Nombramiento', value: nombramiento },
    { label: 'Ingreso a la institución', value: formatFecha(servidor.fecha_ingreso_institucion) },
  ]

  return (
    <SectionCard title="Situación laboral">
      <DetailList items={items} />
    </SectionCard>
  )
}
