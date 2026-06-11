'use client'

import { useMemo }              from 'react'
import { Stack, Grid, Alert,
         Button, Text }         from '@mantine/core'
import { useDisclosure }        from '@mantine/hooks'
import { IconCheck,
         IconAlertCircle }      from '@tabler/icons-react'
import { useViaticoMutations }  from '../hooks/useViaticoMutations'
import { useCategoriasFactura,
         useLiquidacion }       from '../hooks/useViaticos'
import { ActividadesModal }     from './ActividadesModal'
import { FacturasModal }        from './FacturasModal'
import { LiquidacionActividadesCard } from './LiquidacionActividadesCard'
import { LiquidacionFacturasCard }    from './LiquidacionFacturasCard'
import { LiquidacionResumenHA }       from './LiquidacionResumenHA'
import type { ActividadData }   from './ActividadesModal'
import type { FacturaData }     from './FacturasModal'
import type {
  Viatico, CategoriaFactura,
  ActividadLiquidacion, FacturaViatico,
} from '@/types/api'

interface Props {
  viatico:   Viatico
  onSuccess: () => void
}

export function LiquidacionSection({ viatico, onSuccess }: Props) {
  const { data: categoriasData = [] } = useCategoriasFactura()
  const { data: liquidacionData }     = useLiquidacion(viatico.id)
  const { confirmarLiquidacion }      = useViaticoMutations()

  const [actModalAbierto,
    { open: abrirAct, close: cerrarAct }]   = useDisclosure(false)
  const [factModalAbierto,
    { open: abrirFact, close: cerrarFact }] = useDisclosure(false)

  const actividades: ActividadData[] = useMemo(() => {
    if (!liquidacionData?.actividades) return []
    return liquidacionData.actividades
      .map((a: ActividadLiquidacion) => ({
        fecha:        a.fecha        as string,
        hora_inicio:  (a.hora_inicio as string) ?? '',
        hora_fin:     (a.hora_fin    as string) ?? '',
        descripcion:  a.descripcion  as string,
        lugar:        a.lugar        as string,
      }))
  }, [liquidacionData])

  const facturas: FacturaData[] = useMemo(() => {
    if (!liquidacionData?.detalles_factura) return []
    return liquidacionData.detalles_factura
      .map((f: FacturaViatico) => ({
        categoria_factura_id: Number(f.categoria_factura_id),
        fecha_factura:        (f.fecha_factura  as string) ?? '',
        tipo_comprobante:     (f.tipo_comprobante as
          'factura'|'ticket'|'recibo'|'otro') ?? 'factura',
        numero_factura:  (f.numero_factura  as string) ?? '',
        numero_ticket:   (f.numero_ticket   as string) ?? '',
        ruc_proveedor:   (f.ruc_proveedor   as string) ?? '',
        nombre_proveedor: f.nombre_proveedor as string,
        detalle:         (f.detalle         as string) ?? '',
        monto:            Number(f.monto),
      }))
  }, [liquidacionData])

  // Cálculos H&A
  const montoAsignado = Number(viatico.monto_calculado ?? 0)
  const montoAnticipo = Number(viatico.monto_anticipo  ?? 0)
  const monto70       = Math.round(montoAsignado * 0.70 * 100) / 100
  const monto30       = Math.round(montoAsignado * 0.30 * 100) / 100
  const modalidad     = (viatico.modalidad_anticipo as string) ?? 'sin_anticipo'

  const idsViatico = (categoriasData as CategoriaFactura[])
    .filter(c => c.grupo === 'viatico')
    .map(c => Number(c.id))

  const totalHospAli = facturas
    .filter(f => idsViatico.includes(Number(f.categoria_factura_id)))
    .reduce((sum, f) => sum + (Number(f.monto) || 0), 0)

  const totalMovilizacion = facturas
    .filter(f => !idsViatico.includes(Number(f.categoria_factura_id)))
    .reduce((sum, f) => sum + (Number(f.monto) || 0), 0)

  const porcentajeHA = monto70 > 0
    ? Math.min(Math.round((totalHospAli / monto70) * 100), 100)
    : 0

  const justificadoCompleto = totalHospAli >= monto70

  const diferenciaDevolver = modalidad === 'sin_anticipo'
    ? 0
    : (totalHospAli >= montoAnticipo ||
       totalHospAli + totalMovilizacion >= montoAsignado)
      ? 0
      : Math.round((montoAnticipo - totalHospAli) * 100) / 100

  const puedeRegistrar = actividades.length > 0 && facturas.length > 0

  const handleRegistrar = async () => {
    await confirmarLiquidacion.mutateAsync(viatico.id)
    onSuccess()
  }

  return (
    <Stack gap="md">
      <LiquidacionResumenHA
        montoAsignado={montoAsignado}
        montoAnticipo={montoAnticipo}
        monto70={monto70}
        monto30={monto30}
        totalHospAli={totalHospAli}
        totalMovilizacion={totalMovilizacion}
        porcentajeHA={porcentajeHA}
        justificadoCompleto={justificadoCompleto}
        diferenciaDevolver={diferenciaDevolver}
        modalidad={modalidad}
      />

      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <LiquidacionActividadesCard
            actividades={actividades}
            onRegistrar={abrirAct}
            onEditar={abrirAct}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <LiquidacionFacturasCard
            facturas={facturas}
            categorias={categoriasData as CategoriaFactura[]}
            onRegistrar={abrirFact}
            onEditar={abrirFact}
          />
        </Grid.Col>
      </Grid>

      {!puedeRegistrar && (
        <Alert
          icon={<IconAlertCircle size={14} />}
          color="gray"
          variant="light"
        >
          <Text size="xs">
            Para registrar la liquidación debe completar tanto el{' '}
            <strong>informe de actividades</strong> como las{' '}
            <strong>facturas de respaldo</strong>.
          </Text>
        </Alert>
      )}

      <Button
        color="emerald"
        size="md"
        disabled={!puedeRegistrar}
        loading={confirmarLiquidacion.isPending}
        leftSection={<IconCheck size={16} />}
        onClick={handleRegistrar}
        fullWidth
      >
        Registrar liquidación
      </Button>

      <ActividadesModal
        opened={actModalAbierto}
        onClose={cerrarAct}
        viatico={viatico}
        onGuardar={() => {}}
        valorInicial={actividades}
      />

      <FacturasModal
        opened={factModalAbierto}
        onClose={cerrarFact}
        viatico={viatico}
        onGuardar={() => {}}
        valorInicial={facturas}
      />
    </Stack>
  )
}
