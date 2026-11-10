import { Stack, Text } from '@mantine/core'
import type { PuestoConRelaciones } from '@/types/api'

/*
| Las celdas de ocupación de la tabla de puestos: quién lo ocupa, con qué
| modalidad, y cuántas plazas llena (P9).
*/

const OCUPANTES_A_LA_VISTA = 2

/**
 * La modalidad en corto: la etiqueta completa («Contrato de Servicios
 * Ocasionales») no cabe en la columna y se cortaba en «Servicios…», que no
 * distingue al ocasional del profesional.
 */
const MODALIDAD_CORTA: Record<string, string> = {
  nombramiento_permanente:     'Permanente',
  nombramiento_provisional:    'Provisional',
  libre_nombramiento_remocion: 'Libre nombramiento',
  codigo_trabajo:              'Código del Trabajo',
  servicios_ocasionales:       'Ocasional',
  servicios_profesionales:     'Profesional',
  eleccion_popular:            'Elección popular',
}

/**
 * «Vacante», o quiénes lo ocupan y con qué modalidad: los dos primeros y
 * cuántos más. Sin la modalidad, «Analista de TIC» mezclaba dos permanentes y
 * un profesional, y no se notaba quién llenaba una plaza (P9).
 */
export function PuestoOcupantes({ puesto }: { puesto: PuestoConRelaciones }) {
  const ocupantes = (puesto.ocupantes ?? []).filter((o) => o.nombre)

  if (!ocupantes.length) {
    return <Text size="sm" c="dimmed">{puesto.ocupado ? 'Reservado o por regir' : 'Vacante'}</Text>
  }

  const resto = ocupantes.length - OCUPANTES_A_LA_VISTA

  return (
    <Stack gap={4}>
      {/* La modalidad va debajo del nombre: en la misma línea, el recorte de
          un nombre largo se la llevaba, y era lo que se quería ver. */}
      {ocupantes.slice(0, OCUPANTES_A_LA_VISTA).map((o) => (
        <div key={o.id}>
          <Text size="sm" lineClamp={1}>{o.nombre}</Text>
          {o.modalidad && (
            <Text size="xs" c="dimmed" lineClamp={1} title={o.modalidad}>
              {MODALIDAD_CORTA[o.tipo_nombramiento ?? ''] ?? o.modalidad}{o.es_reemplazo ? ', en reemplazo' : ''}
            </Text>
          )}
        </div>
      ))}
      {resto > 0 && <Text size="xs" c="dimmed">y {resto} más</Text>}
    </Stack>
  )
}

/** «2 de 5 ocupadas» en uno de plantilla; el de contrato no tiene plazas (P9). */
export function PuestoPlazas({ puesto: { tipo, plazas, plazas_ocupadas } }: { puesto: PuestoConRelaciones }) {
  if (tipo === 'contrato' || plazas == null) return <Text size="sm" c="dimmed">Sin plazas</Text>

  return <Text size="sm">{plazas_ocupadas ?? 0} de {plazas} ocupadas</Text>
}
