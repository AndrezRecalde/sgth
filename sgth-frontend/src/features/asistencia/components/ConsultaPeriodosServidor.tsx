'use client'

import { useState } from 'react'
import { Alert, Button, Group, NumberInput, Text } from '@mantine/core'
import {
  IconAlertTriangle,
  IconCalendarStats,
  IconInfoCircle,
  IconRefresh,
} from '@tabler/icons-react'
import {
  DataState,
  EmptyState,
  SgthTable,
  StatusBadge,
  Toolbar,
  confirmar,
  notificar,
} from '@/components/ui'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useContainedInput } from '@/hooks/useContainedInput'
import { generaVacaciones } from '@/lib/regimen'
import { ANIO_MAXIMO, ANIO_MINIMO } from './periodos.constants'
import { getPeriodosVacacionesColumns } from './periodosVacaciones.columns'
import { ResumenServidorStats } from './ResumenServidorStats'
import { usePeriodosMutations } from '../hooks/usePeriodosMutations'
import { usePeriodosVacaciones } from '../hooks/usePeriodosVacaciones'
import { useRecalculoCerrado } from '../hooks/useRecalculoCerrado'

interface Props {
  /** `gestionar-vacaciones`: sin él solo se consulta. */
  puedeGestionar: boolean
}

export function ConsultaPeriodosServidor({ puedeGestionar }: Props) {
  const contained = useContainedInput('sm')

  const [servidorId, setServidorId] = useState<number | null>(null)
  const [anio, setAnio] = useState(() => new Date().getFullYear())

  const { data: resumen, isLoading, error, refetch } = usePeriodosVacaciones(servidorId)
  const { generar } = usePeriodosMutations()
  const recalculo = useRecalculoCerrado(servidorId)

  const periodos     = resumen?.periodos ?? []
  const saldoTotal   = Number(resumen?.saldo_total ?? 0)
  const alertaLimite = resumen?.alerta_limite ?? false
  // El tope de su régimen: 60 en LOSEP, tres años de lo que genera en el
  // Código del Trabajo. Antes el aviso decía «límite LOSEP» a cualquiera.
  const tope         = resumen?.tope ?? null
  const excedente    = resumen?.excedente ?? 0

  const columnas = getPeriodosVacacionesColumns({
    tope,
    puedeRecalcular: puedeGestionar,
    onRecalcular: (periodo) => void recalculo.abrir(periodo),
  })

  const generarPeriodo = () => {
    if (!servidorId) return
    confirmar({
      title: 'Generar período de vacaciones',
      message: (
        <>
          Se generará el período <b>{anio}</b> para el servidor seleccionado.
        </>
      ),
      confirmLabel: 'Generar',
      onConfirm: () => generar.mutate({ servidorId, anio }),
    })
  }

  return (
    <>
      <Toolbar
        actions={
          puedeGestionar && (
            <Group gap="sm" wrap="wrap" align="flex-end">
              <NumberInput
                label="Año a generar"
                {...contained}
                min={ANIO_MINIMO}
                max={ANIO_MAXIMO}
                clampBehavior="strict"
                allowDecimal={false}
                allowNegative={false}
                w={130}
                value={anio}
                // Mantine entrega una cadena mientras se escribe y `''` al
                // borrar. Antes cualquier valor no numérico devolvía el campo
                // al año actual, así que borrarlo para reescribirlo lo hacía
                // saltar solo.
                onChange={(valor) => {
                  const numero = typeof valor === 'number' ? valor : Number(valor)
                  if (Number.isFinite(numero) && numero > 0) setAnio(numero)
                }}
              />
              <Button
                variant="light"
                leftSection={<IconRefresh size={16} />}
                disabled={!servidorId}
                loading={generar.isPending}
                onClick={generarPeriodo}
              >
                Generar período {anio}
              </Button>
            </Group>
          )
        }
      >
        <BuscarServidorSelect
          label="Servidor"
          value={servidorId}
          onChange={setServidorId}
          onSelect={(servidor) => {
            // Los regímenes sin vacaciones no tienen período: no es que el suyo
            // salga en cero, es que no les corresponde uno. El backend lo
            // rechaza igual; esto lo dice antes de intentarlo.
            if (!generaVacaciones(servidor.regimen_laboral)) {
              notificar.aviso(
                'Este régimen no genera vacaciones',
                'Un contrato de servicios profesionales no tiene jornada ni relación de dependencia, así que no le corresponde un período.',
              )
              setServidorId(null)
            }
          }}
        />
      </Toolbar>

      {!servidorId ? (
        <EmptyState
          icon={IconCalendarStats}
          title="Ningún servidor seleccionado"
          description="Busque a un servidor por nombre o cédula para ver sus períodos, su saldo y su tope de acumulación."
        />
      ) : (
        <>
          {resumen && !isLoading && !error && (
            <Alert
              icon={alertaLimite ? <IconAlertTriangle size={16} /> : <IconInfoCircle size={16} />}
              color={alertaLimite ? 'amber' : 'ocean'}
              variant="light"
              radius="lg"
            >
              <Group gap="sm" wrap="wrap">
                <Text size="sm">Saldo total disponible:</Text>
                <StatusBadge tone={alertaLimite ? 'warning' : 'success'} size="lg">
                  {saldoTotal.toFixed(1)} días
                </StatusBadge>
                {alertaLimite && tope !== null && (
                  <Text size="xs" c="amber" fw={500}>
                    {excedente > 0
                      ? `Pasa su tope de ${tope.toFixed(0)} días por ${excedente.toFixed(1)}: Talento Humano debe decidir si vence el excedente.`
                      : `Se acerca a su tope de ${tope.toFixed(0)} días: conviene que goce vacaciones pronto.`}
                  </Text>
                )}
              </Group>
            </Alert>
          )}

          <DataState
            loading={isLoading}
            error={error}
            empty={!periodos.length}
            errorTitle="No se pudo cargar el resumen de períodos"
            errorHint="No quiere decir que el servidor no tenga períodos: no se pudieron consultar."
            onRetry={() => void refetch()}
            skeletonRows={4}
            emptyProps={{
              icon: IconCalendarStats,
              title: 'Este servidor no tiene períodos generados',
              description: `Genere el período ${anio} para abrirle un saldo de vacaciones.`,
              action: puedeGestionar ? (
                <Button
                  variant="light"
                  leftSection={<IconRefresh size={16} />}
                  loading={generar.isPending}
                  onClick={generarPeriodo}
                >
                  Generar período {anio}
                </Button>
              ) : undefined,
            }}
          >
            <SgthTable records={periodos} columns={columnas} minHeight={150} />
            {resumen && <ResumenServidorStats periodos={periodos} resumen={resumen} />}
          </DataState>
        </>
      )}
    </>
  )
}
