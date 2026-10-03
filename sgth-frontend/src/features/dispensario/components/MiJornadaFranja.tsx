'use client'

import Link from 'next/link'
import { Anchor, Group, SimpleGrid, Text } from '@mantine/core'
import {
  IconClockHour4, IconFileText, IconStethoscope, IconUserCheck,
} from '@tabler/icons-react'
import { StatCard } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useMiJornada } from '../hooks/useMiJornada'
import type { ContextoJornada } from '../services/miJornadaService'

const plural = (n: number, uno: string, varios: string) => `${n} ${n === 1 ? uno : varios}`

interface Props {
  /** La pantalla donde va: quien tiene varios roles ve aquí la jornada de esta. */
  contexto: ContextoJornada
}

/**
 * «Mi jornada»: lo de quien atiende, encima de su propia cola de trabajo.
 *
 * Va aquí y no en una página aparte para no sumar un clic entre paciente y
 * paciente. Solo trae lo suyo: el tablero que compara profesionales es de la
 * jefatura.
 */
export function MiJornadaFranja({ contexto }: Props) {
  const { data, isLoading } = useMiJornada(contexto)

  // Mientras carga, el hueco que ocupará: antes no pintaba nada y al llegar
  // los datos empujaba hacia abajo lo de la pantalla —el buscador de cédula
  // incluido, con el clic de quien ya iba a escribir.
  if (isLoading) {
    return (
      <SimpleGrid cols={{ base: 1, xs: 2, md: 4 }} spacing="md">
        {Array.from({ length: 4 }, (_, i) => (
          <StatCard key={i} label="" value={0} icon={IconClockHour4} loading />
        ))}
      </SimpleGrid>
    )
  }

  if (!data?.perfil) return null

  // La de Enfermería va en el panel «Hoy en Enfermería», junto al buscador.
  if (data.perfil === 'enfermeria') return null

  const { hoy, pendientes, mes } = data
  const sinTriaje = hoy.esperando - hoy.listos

  return (
    <>
      <SimpleGrid cols={{ base: 1, xs: 2, md: 4 }} spacing="md">
        <StatCard label="Listos para pasar" value={hoy.listos} icon={IconClockHour4} loading={isLoading}
          hint={sinTriaje > 0 ? `${sinTriaje} más esperan el triaje` : 'Nadie espera el triaje'} />
        <StatCard label="Atendidos hoy" value={hoy.atendidos} icon={IconUserCheck} loading={isLoading}
          hint={hoy.no_presentados ? plural(hoy.no_presentados, 'no se presentó', 'no se presentaron') : undefined} />
        {/* Una consulta empezada y sin cerrar se pierde de vista al cambiar de
            paciente: se recuerda aquí. */}
        <StatCard label="Consultas sin cerrar" value={pendientes.borradores} icon={IconFileText} loading={isLoading}
          tone={pendientes.borradores ? 'warning' : undefined} hint="Empezadas y guardadas como borrador" />
        <StatCard label="Mi mes" value={mes.consultas} icon={IconStethoscope} loading={isLoading}
          hint={data.perfil === 'odontologo'
            ? `${plural(mes.pacientes, 'paciente', 'pacientes')} · ${plural(mes.procedimientos, 'procedimiento', 'procedimientos')}`
            : `${plural(mes.pacientes, 'paciente', 'pacientes')} · ${plural(mes.reposos, 'reposo', 'reposos')} (${plural(mes.dias_reposo, 'día', 'días')})`} />
      </SimpleGrid>
      {data.perfil === 'medico' && (pendientes.fichas_femo > 0 || pendientes.evaluaciones_listas > 0) && (
        <Group gap="xs">
          <Text size="xs" c="dimmed">
            Salud ocupacional: {plural(pendientes.fichas_femo, 'ficha FEMO en borrador', 'fichas FEMO en borrador')} ·{' '}
            {plural(pendientes.evaluaciones_listas, 'evaluación lista para iniciar', 'evaluaciones listas para iniciar')}.
          </Text>
          <Anchor size="xs" component={Link} href={ROUTES.SALUD.SSO}>Ir a Solicitudes</Anchor>
        </Group>
      )}
    </>
  )
}
