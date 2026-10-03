'use client'

import { useRouter } from 'next/navigation'
import { Anchor, Group, SimpleGrid, Text } from '@mantine/core'
import {
  IconClockHour4, IconFileText, IconHeartbeat, IconShieldCheck, IconStethoscope, IconUserCheck,
} from '@tabler/icons-react'
import { StatCard } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useMiJornada } from '../hooks/useMiJornada'
import type { ContextoJornada } from '../services/miJornadaService'

const plural = (n: number, uno: string, varios: string) => `${n} ${n === 1 ? uno : varios}`

interface Props {
  /** La pantalla donde va: quien tiene dos roles ve aquí la jornada de esta. */
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
  const router = useRouter()
  const { data, isLoading } = useMiJornada(contexto)

  if (!data?.perfil) return null

  if (data.perfil === 'enfermeria') {
    return (
      <>
        <SimpleGrid cols={{ base: 1, xs: 3 }} spacing="md">
          <StatCard label="Por triar" value={data.hoy.por_triar} icon={IconHeartbeat} loading={isLoading}
            tone={data.hoy.por_triar ? 'warning' : undefined} hint="Turnos de hoy esperando sus signos" />
          <StatCard label="Triaje de salud ocupacional" value={data.hoy.triaje_sso} icon={IconShieldCheck}
            loading={isLoading} hint="Evaluaciones que esperan a Enfermería" />
          <StatCard label="Mis atenciones de hoy" value={data.hoy.mis_atenciones} icon={IconUserCheck} loading={isLoading} />
        </SimpleGrid>
        <Text size="xs" c="dimmed">
          Este mes: {plural(data.mes.atenciones, 'atención', 'atenciones')} y {plural(data.mes.triajes, 'triaje', 'triajes')}
          {data.mes.por_servicio[0] && ` · la más frecuente, ${data.mes.por_servicio[0].servicio.toLowerCase()}`}.
          {data.hoy.triaje_sso > 0 && (
            <> <Anchor size="xs" onClick={() => router.push(ROUTES.SALUD.ENFERMERIA_SSO)}>Ir a Atención SSO</Anchor></>
          )}
        </Text>
      </>
    )
  }

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
          <Anchor size="xs" onClick={() => router.push(ROUTES.SALUD.SSO)}>Ir a Solicitudes</Anchor>
        </Group>
      )}
    </>
  )
}
