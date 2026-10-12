'use client'

import { useState } from 'react'
import { Alert, Button, SimpleGrid, Stack, Text } from '@mantine/core'
import { MonthPickerInput } from '@mantine/dates'
import { IconCalendarCheck, IconDownload, IconInfoCircle, IconListDetails, IconSend } from '@tabler/icons-react'
import dayjs from 'dayjs'
import {
  DataState, SectionHeading, SgthTable, StatCard, Toolbar, confirmar, notificar,
} from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { guardarArchivo } from '@/lib/archivo'
import { formatFechaHora } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { useEntregarNovedades, useNovedadesNomina } from '../hooks/useNovedadesNomina'
import { novedadesNominaService } from '../services/novedadesNominaService'
import { columnasNovedadesNomina } from './novedadesNomina.columns'

const MES_ACTUAL = dayjs().format('YYYY-MM')

/**
 * Las novedades para nómina del mes (fase 7.3a). Financiero paga en su propio
 * sistema: Talento Humano revisa lo que el SGTH deriva de los actos y se lo
 * entrega, y la entrega congela el mes. Lo que cambie después en él va como
 * ajuste en el mes siguiente.
 */
export function NovedadesNominaPanel() {
  const contained = useContainedInput('sm')
  const { hasPermiso } = useAuth()
  const puedeEntregar = hasPermiso('entregar-novedades-nomina')
  const [periodo, setPeriodo] = useState(MES_ACTUAL)
  const [descargando, setDescargando] = useState(false)
  const { data, isLoading, error, refetch } = useNovedadesNomina(periodo)
  const entregar = useEntregarNovedades()

  const novedades = data?.novedades ?? []
  const ajustes = data?.ajustes ?? []
  const cambios = data?.cambios_tras_la_entrega ?? []

  const descargar = async () => {
    setDescargando(true)
    try {
      guardarArchivo(await novedadesNominaService.excel(periodo), `novedades_nomina_${periodo}.xlsx`)
    } catch (e) {
      notificar.error('No se pudo descargar el Excel', getApiErrorMessage(e))
    } finally {
      setDescargando(false)
    }
  }

  const pedirEntrega = () => confirmar({
    title: `Entregar ${data?.etiqueta ?? 'el mes'} a Financiero`,
    message: (
      <>
        Se congelan las {novedades.length} novedades
        {ajustes.length > 0 ? ` y los ${ajustes.length} ajustes` : ''} del mes, y se le envían por correo al jefe
        de Gestión Financiera con el Excel. No se puede deshacer: lo que cambie después va como ajuste en el mes
        siguiente.
      </>
    ),
    confirmLabel: 'Entregar',
    onConfirm: () => entregar.mutate(periodo),
  })

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <>
            <Button
              variant="default"
              leftSection={<IconDownload size={16} />}
              loading={descargando}
              disabled={!data}
              onClick={descargar}
            >
              Descargar Excel
            </Button>
            {puedeEntregar && data?.puede_entregarse && (
              <Button leftSection={<IconSend size={16} />} loading={entregar.isPending} onClick={pedirEntrega}>
                Entregar a Financiero
              </Button>
            )}
          </>
        }
      >
        <MonthPickerInput
          label="Mes"
          valueFormat="MMMM [de] YYYY"
          value={`${periodo}-01`}
          onChange={(v) => { if (v) setPeriodo(dayjs(v).format('YYYY-MM')) }}
          {...contained}
          style={{ minWidth: 200 }}
        />
      </Toolbar>

      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Lo que el rol de pagos del mes tiene que saber, sacado de los actos registrados: quién entra y quién sale,
        los cambios de remuneración, los días sin remuneración, las subrogaciones y los encargos, las multas y lo que
        paga una restitución. Los montos son una referencia, en meses de treinta días: el valor definitivo lo
        establece Financiero en su sistema.
      </Alert>

      {data?.entrega && (
        <Alert variant="light" color="emerald" icon={<IconCalendarCheck size={16} />} title="Mes entregado">
          Lo entregó {data.entrega.entregada_por ?? 'Talento Humano'} el {formatFechaHora(data.entrega.entregada_en)}.{' '}
          {data.entrega.aviso === 'enviado'
            ? `El correo salió a ${data.entrega.destinatario}.`
            : 'El correo a Financiero no salió: hay que enviarle el Excel a mano.'}
        </Alert>
      )}

      {data && data.resumen.length > 0 && (
        <SimpleGrid cols={{ base: 1, xs: 2, md: 4 }}>
          {data.resumen.map((r) => (
            <StatCard
              key={r.concepto}
              label={r.etiqueta}
              value={r.cantidad}
              icon={IconListDetails}
              hint={`${r.monto < 0 ? '− ' : ''}$ ${Math.abs(r.monto).toFixed(2)} de referencia`}
            />
          ))}
        </SimpleGrid>
      )}

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las novedades para nómina"
        onRetry={refetch}
        empty={!novedades.length && !ajustes.length}
        emptyProps={{
          icon: IconListDetails,
          title: 'Sin novedades',
          description: `En ${data?.etiqueta ?? 'este mes'} no hay nada que el rol de pagos tenga que saber.`,
        }}
      >
        <Stack gap="md">
          {novedades.length > 0 && (
            <SgthTable records={novedades} columns={columnasNovedadesNomina} idAccessor="clave" minHeight={160} />
          )}

          {ajustes.length > 0 && (
            <>
              <Stack gap={4}>
                <SectionHeading title="Ajustes de meses entregados" mb={0} />
                <Text size="sm" c="dimmed">Lo que cambió en ellos después de entregarse: va en este mes.</Text>
              </Stack>
              <SgthTable
                records={ajustes}
                columns={columnasNovedadesNomina}
                idAccessor={(n) => `${n.mes_afectado}:${n.clave}`}
                minHeight={120}
              />
            </>
          )}
        </Stack>
      </DataState>

      {cambios.length > 0 && (
        <Alert variant="light" color="amber" icon={<IconInfoCircle size={16} />} title="Cambió después de la entrega">
          <Text size="sm">
            {cambios.length === 1 ? 'Una novedad cambió' : `${cambios.length} novedades cambiaron`} desde que se
            entregó el mes. Lo entregado no se reescribe: la diferencia va como ajuste en el próximo mes que se
            entregue.
          </Text>
        </Alert>
      )}
    </Stack>
  )
}
