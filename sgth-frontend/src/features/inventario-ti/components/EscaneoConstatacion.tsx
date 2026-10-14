'use client'

import { useState } from 'react'
import { Alert, Button, Group, Stack, Switch, Text, TextInput } from '@mantine/core'
import { IconArrowsMove, IconCirclePlus, IconScan } from '@tabler/icons-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { SectionCard } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { RESULTADO_CONSTATACION } from '../constants/constatacion'
import { useConstatacionMutations } from '../hooks/useConstataciones'
import { escaneoSchema, type EscaneoFormData } from '../schemas/constatacion.schema'
import type { ResultadoEscaneo } from '../services/constatacionesService'
import { LectorQr } from './LectorQr'

interface Props {
  constatacionId: number
  /** El código que no está en el inventario: se registra aquí mismo. */
  onRegistrarEnSitio: (codigo: string) => void
}

/**
 * Escanear en la constatación: la cámara del celular o el código escrito a
 * mano, y la tarjeta del último equipo con lo que se puede hacer —moverlo
 * aquí si estaba registrado en otra parte, o registrarlo si no está—.
 */
export function EscaneoConstatacion({ constatacionId, onRegistrarEnSitio }: Props) {
  const contained = useContainedInput()
  const { escanear, mover } = useConstatacionMutations(constatacionId)
  const [camara, setCamara] = useState(false)
  const [ultimo, setUltimo] = useState<(ResultadoEscaneo & { mensaje: string }) | null>(null)
  const { register, handleSubmit, reset, formState: { errors } } = useForm<EscaneoFormData>({
    resolver: zodResolver(escaneoSchema),
    defaultValues: { codigo: '' },
  })

  const leer = (codigo: string) => escanear.mutateAsync(codigo).then((r) => { setUltimo(r); reset({ codigo: '' }) }).catch(() => undefined)
  const fila = ultimo?.fila
  const tono = fila ? RESULTADO_CONSTATACION[fila.resultado].tono : 'warning'

  return (
    <SectionCard title="Escanear" description="Lea la etiqueta QR con la cámara o escriba el código institucional.">
      <Stack gap="md">
        <Switch label="Usar la cámara" checked={camara} onChange={(e) => setCamara(e.currentTarget.checked)} />
        {camara && <LectorQr onLeer={leer} />}

        <form noValidate onSubmit={handleSubmit((v) => leer(v.codigo))}>
          <Group align="flex-end" gap="sm" wrap="nowrap">
            <TextInput label="Código del equipo" placeholder="Ej.: 001774" style={{ flex: 1 }} autoComplete="off"
              error={errors.codigo?.message} {...contained} {...register('codigo')} />
            <Button type="submit" variant="light" leftSection={<IconScan size={16} />} loading={escanear.isPending} h={48}>
              Registrar
            </Button>
          </Group>
        </form>

        {ultimo && !ultimo.encontrado && (
          <Alert color={SEMANTIC_COLOR.warning} variant="light" title={`${ultimo.codigo}: no está en el inventario`}>
            <Stack gap="xs">
              <Text size="sm">Si el equipo está aquí y tiene ese código, regístrelo ahora: quedará en esta ubicación y en la constatación.</Text>
              <Group><Button size="xs" variant="light" leftSection={<IconCirclePlus size={14} />} onClick={() => onRegistrarEnSitio(ultimo.codigo)}>Registrarlo aquí</Button></Group>
            </Stack>
          </Alert>
        )}

        {fila && (
          <Alert color={SEMANTIC_COLOR[tono]} variant="light"
            title={`${fila.codigo_institucional} · ${ultimo?.repetido ? 'ya estaba escaneado' : RESULTADO_CONSTATACION[fila.resultado].etiqueta.toLowerCase()}`}>
            <Stack gap="xs">
              <Text size="sm">{fila.equipo || '—'}{fila.custodio ? ` · ${fila.custodio}` : ' · sin custodio'}</Text>
              {fila.resultado === 'otra_ubicacion' && (
                fila.movido
                  ? <Text size="sm">Ya quedó en esta ubicación.</Text>
                  : (
                    <>
                      <Text size="sm">{fila.ubicacion_registrada ? `Estaba registrado en ${fila.ubicacion_registrada}.` : 'No tenía ubicación registrada.'}</Text>
                      {fila.item_id && (
                        <Group>
                          <Button size="xs" variant="light" leftSection={<IconArrowsMove size={14} />} loading={mover.isPending}
                            onClick={() => fila.item_id && mover.mutate(fila.item_id, {
                              onSuccess: () => setUltimo((u) => (u?.fila ? { ...u, fila: { ...u.fila, movido: true } } : u)),
                            })}>
                            Moverlo aquí
                          </Button>
                        </Group>
                      )}
                    </>
                  )
              )}
              {fila.resultado === 'de_baja' && <Text size="sm">El equipo figura dado de baja: no debería estar en servicio.</Text>}
            </Stack>
          </Alert>
        )}
      </Stack>
    </SectionCard>
  )
}
