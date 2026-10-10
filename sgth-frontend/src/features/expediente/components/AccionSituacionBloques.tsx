'use client'

import { Grid, Text } from '@mantine/core'
import { DetailList, SectionHeading } from '@/components/ui'
import { BloqueDetalle } from './BloqueDetalle'
import { dinero } from '../utils/dinero'
import type { MovimientoPersonal } from '@/types/api'
import { formatFecha } from '@/lib/fecha'

interface Props {
  m: MovimientoPersonal
  /**
   * El único punto de edición del cajón. Se ancla al pie de la tarjeta de la
   * derecha; cuando esa tarjeta no existe —cesación, sanción— baja a la única
   * que hay, para que nunca quede una acción en borrador sin forma de
   * corregirla.
   */
  botonEditar: React.ReactNode
}

/**
 * Las dos columnas del documento impreso: de dónde viene el servidor y a dónde
 * queda.
 *
 * La actual quedó congelada al crear la acción, así que refleja dónde estaba el
 * servidor entonces — no dónde está hoy.
 */
export function AccionSituacionBloques({ m, botonEditar }: Props) {
  const esIngreso = m.clase === 'ingreso'
  // La subrogación y el encargo comparten bloque: los dos ejercen otro puesto
  // y cobran la diferencia. Qué es cada acción lo responde el backend.
  const esSubrogacion = m.clase === 'subrogacion' || m.clase === 'encargo'
  const propone = m.propone_situacion
  const ausencia = m.es_ausencia_temporal

  const diferencia = m.remuneracion_propuesta != null && m.remuneracion_origen != null
    ? Number(m.remuneracion_propuesta) - Number(m.remuneracion_origen)
    : null

  const nombres = [m.servidor?.nombre, m.servidor?.segundo_nombre].filter(Boolean).join(' ')
  const apellidos = [m.servidor?.apellido, m.servidor?.segundo_apellido].filter(Boolean).join(' ')

  return (
    <Grid>
      {/* Sin columna derecha —cesación, sanción— la actual ocupa el ancho
          completo en vez de dejar medio panel vacío. */}
      <Grid.Col span={{ base: 12, sm: propone || ausencia ? 6 : 12 }}>
        <BloqueDetalle hundido altoCompleto>
          <SectionHeading
            title={propone ? 'Situación actual' : 'Situación del servidor'}
            mb="xs"
          />
          {esIngreso ? (
            <Text size="sm" c="dimmed">
              Sin vínculo previo — este es el primer ingreso del servidor.
            </Text>
          ) : (
            <DetailList columnas={1} items={[
              { label: 'Apellidos', value: apellidos },
              { label: 'Nombres', value: nombres },
              { label: 'Cédula', value: m.servidor?.cedula },
              { label: 'Papeleta de votación', value: m.servidor?.numero_papeleta_votacion },
              { label: 'Unidad', value: m.unidad_origen?.nombre },
              { label: 'Puesto', value: m.puesto_origen?.cargo?.nombre },
              { label: 'R.M.U.', value: dinero(m.remuneracion_origen) },
              { label: 'Partida', value: m.partida_origen?.codigo },
            ]} />
          )}

          {/* Solo cuando no hay tarjeta a la derecha donde anclarlo. */}
          {!propone && !ausencia && botonEditar}
        </BloqueDetalle>
      </Grid.Col>

      {/* Una cesación no propone nada: termina el vínculo. Una comisión o
          una licencia dejan al servidor en su puesto, y lo que las define
          es el período. Reservar la columna para todas obligaba a
          rellenarla de guiones. */}
      {propone && (
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <BloqueDetalle altoCompleto>
            <SectionHeading
              title={esSubrogacion
                ? (m.clase === 'encargo' ? 'Puesto encargado' : 'Puesto subrogado')
                : 'Situación propuesta'}
              mb="xs"
            />
            <DetailList columnas={1} items={[
              { label: 'Unidad', value: m.unidad_destino?.nombre },
              { label: 'Puesto', value: m.puesto_destino?.cargo?.nombre },
              ...(esSubrogacion
                ? []
                : [{ label: 'Lugar de trabajo', value: m.lugar_trabajo }]),
              {
                label: 'Partida',
                // La de la acción manda; si Talento Humano no fijó
                // ninguna, rige la del puesto de destino.
                value: m.partida_presupuestaria?.codigo
                  ?? m.puesto_destino?.partida_presupuestaria?.codigo,
              },
              {
                label: esSubrogacion ? 'R.M.U. del puesto' : 'R.M.U. propuesta',
                value: dinero(m.remuneracion_propuesta),
              },
              // Lo que realmente se autoriza en una subrogación: no el
              // sueldo del puesto, sino la diferencia contra lo que el
              // servidor ya percibe (Art. 21 Reglamento LOSEP). Ambas
              // cifras quedaron congeladas al crear la acción, así que
              // esta resta es la que se aprobó, no la de hoy.
              ...(esSubrogacion
                ? [{
                    label: 'Diferencia a pagar',
                    value: diferencia != null && diferencia > 0 ? dinero(diferencia) : null,
                  }]
                : []),
            ]} />

            {botonEditar}
          </BloqueDetalle>
        </Grid.Col>
      )}

      {ausencia && (
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <BloqueDetalle altoCompleto>
            <SectionHeading title="Período de la ausencia" mb="xs" />
            <DetailList columnas={1} items={[
              { label: 'Desde', value: formatFecha(m.fecha_inicio) },
              {
                label: 'Hasta',
                value: m.fecha_fin ? formatFecha(m.fecha_fin) : 'Sin fecha de fin',
              },
              // A qué entidad va, desde la fase 2.3; las de antes lo dicen en
              // su explicación.
              ...(m.institucion_destino
                ? [{ label: 'Institución de destino', value: m.institucion_destino }]
                : [{ label: 'Destino', value: m.unidad_destino?.nombre }]),
              ...(m.para_estudios_o_eventos
                ? [{ label: 'Al volver', value: 'Debe servir un tiempo igual al de la comisión' }]
                : []),
            ]} />
            <Text size="xs" c="dimmed" mt="xs">
              El servidor conserva su puesto y su plaza; regresa al vencer
              el período.
            </Text>

            {botonEditar}
          </BloqueDetalle>
        </Grid.Col>
      )}
    </Grid>
  )
}
