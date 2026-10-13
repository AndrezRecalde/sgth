'use client'

import { Checkbox, Group, Stack, Text } from '@mantine/core'
import type { EquipoPrestable, ReglasPrestamo } from '../services/tipos'
import { aFecha, esFinDeSemana, isoDia } from '../utils/agenda'
import { BarraHorario, EscalaHoras } from './BarraHorario'
import { EstadoAhora } from './EstadoAhora'
import classes from './AgendaEquipos.module.css'

interface Props {
  equipos: EquipoPrestable[]
  /** Un día (vista por día) o los de la semana. */
  dias: string[]
  reglas: Pick<ReglasPrestamo, 'hora_apertura' | 'hora_cierre' | 'presta_fines_de_semana'>
  seleccion: number[]
  onSeleccionar: (id: number) => void
  /** Clic en un horario libre de un equipo. */
  onElegir: (equipo: EquipoPrestable, inicio: string) => void
}

const nombreDia = (dia: string) =>
  aFecha(dia).toLocaleDateString('es-EC', { weekday: 'short', day: '2-digit' })

/**
 * Cada equipo prestable con su estado ahora y su horario: la barra del día, o
 * cinco (o siete) barras chicas en la semana. Se marca para pedir varios de
 * una vez, o se hace clic en un horario libre para pedir ese.
 */
export function AgendaEquipos({ equipos, dias, reglas, seleccion, onSeleccionar, onElegir }: Props) {
  const porDia = dias.length === 1
  const hoy = isoDia(new Date())
  const { hora_apertura: apertura, hora_cierre: cierre } = reglas
  const cerrado = (dia: string) => !reglas.presta_fines_de_semana && esFinDeSemana(dia)

  return (
    <div>
      <div className={`${classes.fila} ${classes.cabecera}`}>
        <span className={classes.vacia} />
        {porDia ? (
          <EscalaHoras apertura={apertura} cierre={cierre} />
        ) : (
          <div className={classes.semana} style={{ '--dias': dias.length } as React.CSSProperties}>
            {dias.map((d) => (
              <span key={d} className={`${classes.diaSemana} ${d === hoy ? classes.hoy : ''}`}>{nombreDia(d)}</span>
            ))}
          </div>
        )}
      </div>

      {equipos.map((e) => {
        const fuera = e.ahora.estado === 'no_disponible'
        const vencido = e.ahora.estado === 'prestado' && e.ahora.vencido
        return (
          <div key={e.id} className={classes.fila}>
            <Group gap="sm" wrap="nowrap" align="flex-start">
              <Checkbox
                mt={4}
                checked={seleccion.includes(e.id)}
                disabled={fuera}
                onChange={() => onSeleccionar(e.id)}
                aria-label={`Elegir ${e.codigo_institucional}`}
              />
              <Stack gap={4} style={{ minWidth: 0 }}>
                <div>
                  <Text size="sm" fw={600}>{e.codigo_institucional} · {e.tipo}</Text>
                  <Text size="xs" c="dimmed" truncate>{[e.marca, e.modelo].filter(Boolean).join(' ')}</Text>
                </div>
                <EstadoAhora ahora={e.ahora} />
              </Stack>
            </Group>
            {porDia ? (
              <BarraHorario dia={dias[0]} bloques={e.bloques} apertura={apertura} cierre={cierre}
                inerte={fuera || cerrado(dias[0])} vencido={vencido} onElegir={(inicio) => onElegir(e, inicio)} />
            ) : (
              <div className={classes.semana} style={{ '--dias': dias.length } as React.CSSProperties}>
                {dias.map((d) => (
                  <BarraHorario key={d} dia={d} bloques={e.bloques} apertura={apertura} cierre={cierre} compacta
                    inerte={fuera || cerrado(d)} vencido={vencido} onElegir={(inicio) => onElegir(e, inicio)} />
                ))}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}
