'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Alert, Anchor, Button, Grid, Group, Stack, Switch, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconTruckDelivery } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DetailList, PageHeader, PageShell, SectionCard, SgthTable, notificar } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { plural } from '@/lib/plural'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useBien } from '@/features/inventario-ti/hooks/useBienes'
import { AgregarModeloModal } from '@/features/consumibles-ti/components/AgregarModeloModal'
import { AgregarSelect } from '@/features/consumibles-ti/components/AgregarSelect'
import { ElegirEquipoEntrega } from '@/features/consumibles-ti/components/ElegirEquipoEntrega'
import { columnasEntrega, type RenglonEntrega } from '@/features/consumibles-ti/components/entrega.columns'
import { useConsumibles, useMovimientoMutations } from '@/features/consumibles-ti/hooks/useConsumibles'
import { entregaSchema, type EntregaFormData } from '@/features/consumibles-ti/schemas/movimiento.schema'
import type { Consumible } from '@/features/consumibles-ti/services/consumiblesService'

const CAMPOS = ['fecha', 'servidor_retira_id', 'observacion']

/**
 * Entregar consumibles (fase 5.4): se elige el equipo —con el buscador, el
 * código o la etiqueta QR desde el celular— y aparecen los consumibles
 * compatibles con su saldo; se marca la cantidad de cada uno. Si falta uno,
 * se agrega su compatibilidad desde aquí. Un repuesto puede ir sin equipo.
 */
export function EntregarConsumiblesView({ bienId: inicial }: { bienId: number | null }) {
  const contained = useContainedInput()
  const [bienId, setBienId] = useState<number | null>(inicial)
  const [sinEquipo, setSinEquipo] = useState(false)
  const [cantidades, setCantidades] = useState<Record<number, number>>({})
  const [agregados, setAgregados] = useState<number[]>([])
  const [faltante, setFaltante] = useState<Consumible | null>(null)

  const conEquipo = !sinEquipo && !!bienId
  const { data: bien } = useBien(conEquipo && bienId ? bienId : 0)
  const { data: compatibles = [], isLoading: cargandoCompatibles } = useConsumibles({ bien_informatico_id: bienId ?? undefined }, conEquipo)
  const { data: activos = [] } = useConsumibles()
  const { entrega } = useMovimientoMutations()

  const { register, control, handleSubmit, setError, reset, formState: { errors } } = useForm<EntregaFormData>({
    resolver: zodResolver(entregaSchema),
    defaultValues: { fecha: hoyIso(), servidor_retira_id: null, observacion: '' },
  })

  const deEquipo = conEquipo ? compatibles : []
  const ids = new Set(deEquipo.map((c) => c.id))
  const renglones: (RenglonEntrega & { id: number })[] = [
    ...deEquipo.map((c) => ({ id: c.id, consumible: c, cantidad: cantidades[c.id] ?? 0, agregado: false })),
    ...activos.filter((c) => agregados.includes(c.id) && !ids.has(c.id))
      .map((c) => ({ id: c.id, consumible: c, cantidad: cantidades[c.id] ?? 0, agregado: true })),
  ]
  const enLista = new Set(renglones.map((r) => r.id))
  const repuestos = activos.filter((c) => !c.de_impresion && !enLista.has(c.id))
  // De impresión y que no sirve a este modelo: se le puede agregar la compatibilidad.
  const noCompatibles = conEquipo ? activos.filter((c) => c.de_impresion && !ids.has(c.id)) : []
  const marcados = renglones.filter((r) => r.cantidad > 0)

  const cambiarEquipo = (id: number | null) => {
    setBienId(id)
    setCantidades({})
    setAgregados([])
  }

  const enviar = (v: EntregaFormData) => {
    if (!marcados.length) {
      notificar.aviso('Nada que entregar', 'Marque la cantidad de al menos un consumible.')
      return
    }
    return entrega.mutateAsync({
      fecha: v.fecha,
      bien_informatico_id: conEquipo ? bienId : null,
      servidor_retira_id: v.servidor_retira_id,
      observacion: v.observacion.trim() || null,
      items: marcados.map((r) => ({ consumible_id: r.id, cantidad: r.cantidad })),
    }).then(() => {
      setCantidades({})
      setAgregados([])
      reset({ fecha: v.fecha, servidor_retira_id: null, observacion: '' })
    }).catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))
  }

  return (
    <PageShell>
      <PageHeader title="Entregar consumibles" backHref={ROUTES.TIC.CONSUMIBLES}
        description="Elija el equipo y marque cuántos de cada consumible compatible se entregan" />

      <Switch label="Repuesto sin equipo: va a un servidor, no a un equipo en particular" checked={sinEquipo}
        onChange={(e) => { setSinEquipo(e.currentTarget.checked); setCantidades({}); setAgregados([]) }} />

      {!sinEquipo && !bienId && <ElegirEquipoEntrega onElegir={cambiarEquipo} />}

      {conEquipo && (
        <SectionCard title={bien ? `Equipo ${bien.codigo_institucional}` : 'Equipo'}
          actions={<Button variant="subtle" size="xs" onClick={() => cambiarEquipo(null)}>Cambiar de equipo</Button>}>
          {bien && (
            <DetailList columnas={3} items={[
              { label: 'Equipo', value: <Anchor size="sm" component={Link} href={ROUTES.TIC.EQUIPO(bien.id)}>{[bien.tipo?.nombre, bien.marca?.nombre, bien.modelo].filter(Boolean).join(' · ')}</Anchor> },
              { label: 'Custodio', value: bien.custodio?.nombre ?? 'Sin custodio' },
              { label: 'Unidad', value: bien.unidad?.nombre ?? null },
            ]} />
          )}
          {bien?.estado_operativo === 'dado_de_baja' && (
            <Alert mt="md" color={SEMANTIC_COLOR.danger} variant="light">El equipo está dado de baja: ya no se le entregan consumibles.</Alert>
          )}
        </SectionCard>
      )}

      {(conEquipo || sinEquipo) && (
        <form noValidate onSubmit={handleSubmit(enviar)}>
          <Stack gap="lg">
            <SectionCard title="Consumibles" description={sinEquipo ? 'Agregue los repuestos que se entregan.' : 'Los compatibles con el modelo del equipo y los repuestos que agregue.'}>
              <Stack gap="md">
                <SgthTable records={renglones} minHeight={renglones.length ? 0 : 120} fetching={conEquipo && cargandoCompatibles}
                  columns={columnasEntrega({
                    onCantidad: (id, n) => setCantidades((c) => ({ ...c, [id]: n })),
                    onQuitar: (id) => setAgregados((a) => a.filter((x) => x !== id)),
                  })}
                  noRecordsText={sinEquipo ? 'Agregue un repuesto.' : 'Ningún consumible del catálogo está marcado para este modelo.'} />
                <Grid>
                  <Grid.Col span={{ base: 12, sm: conEquipo ? 6 : 12 }}>
                    <AgregarSelect label="Agregar un repuesto" placeholder="Teclado, cable, pasta térmica…"
                      data={repuestos.map((c) => ({ value: String(c.id), label: c.descripcion }))}
                      nothingFoundMessage="No hay más repuestos en el catálogo"
                      onElegir={(v) => setAgregados((a) => [...a, Number(v)])} />
                  </Grid.Col>
                  {conEquipo && (
                    <Grid.Col span={{ base: 12, sm: 6 }}>
                      <AgregarSelect label="¿Falta uno? Márquelo como compatible" placeholder="Tóner, tinta…"
                        data={noCompatibles.map((c) => ({ value: String(c.id), label: c.descripcion }))}
                        nothingFoundMessage="No hay más consumibles de impresión"
                        onElegir={(v) => setFaltante(noCompatibles.find((c) => String(c.id) === v) ?? null)} />
                    </Grid.Col>
                  )}
                </Grid>
              </Stack>
            </SectionCard>

            <SectionCard title="Quién retira">
              <Grid>
                <Grid.Col span={{ base: 12, sm: 8 }}>
                  <Controller name="servidor_retira_id" control={control} render={({ field }) => (
                    <BuscarServidorSelect label={conEquipo && bien?.custodio ? `Quién retira (si no es ${bien.custodio.nombre})` : 'Quién retira'}
                      required={!conEquipo || !bien?.custodio}
                      value={field.value} onChange={field.onChange} error={errors.servidor_retira_id?.message} />
                  )} />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  <Controller name="fecha" control={control} render={({ field }) => (
                    <DatePickerInput label="Fecha" valueFormat="DD/MM/YYYY" withAsterisk maxDate={hoyIso()}
                      error={errors.fecha?.message} {...contained}
                      value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
                  )} />
                </Grid.Col>
                <Grid.Col span={12}>
                  <Textarea label="Observación" placeholder="Opcional" autosize minRows={2}
                    error={errors.observacion?.message} {...contained} {...register('observacion')} />
                </Grid.Col>
              </Grid>
            </SectionCard>

            <Group justify="space-between">
              <Text size="sm" c="dimmed">
                {marcados.length ? `${marcados.reduce((s, r) => s + r.cantidad, 0)} en total: ${plural(marcados.length, 'consumible', 'consumibles')}` : 'Marque las cantidades.'}
              </Text>
              <Button type="submit" leftSection={<IconTruckDelivery size={16} />} loading={entrega.isPending}
                disabled={!marcados.length || bien?.estado_operativo === 'dado_de_baja'}>
                Registrar entrega
              </Button>
            </Group>
          </Stack>
        </form>
      )}

      <AgregarModeloModal consumible={faltante} onClose={() => setFaltante(null)}
        inicial={bien?.modelo ? { marca_id: bien.marca_id, modelo: bien.modelo } : undefined} />
    </PageShell>
  )
}
