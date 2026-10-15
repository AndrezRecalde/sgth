'use client'

import { Grid, Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { useIdentidadPublica } from '../hooks/useIdentidadPublica'
import type { MiCredencial } from '../services/tiposCredencial'
import classes from './CredencialDigital.module.css'
import { ImagenFotografia } from './ImagenFotografia'
import { LogoInstitucional } from './LogoInstitucional'

interface Props {
  credencial: MiCredencial
}

/**
 * La credencial digital (docs/diseno-comunicacion-social.md, 6.1 y 6.2): las
 * dos caras de la tarjeta CR80, con los datos de hoy del expediente. Lo que no
 * está registrado no se enseña, igual que en la impresa.
 */
export function CredencialDigital({ credencial }: Props) {
  const { data: identidad } = useIdentidadPublica()
  const d = credencial.datos
  const contacto = d.contacto_emergencia

  return (
    <Grid gap="lg" align="flex-start">
      <Grid.Col span={{ base: 12, sm: 6 }}>
        <section className={classes.tarjeta} aria-label="Anverso de la credencial">
          <div className={classes.cuerpo}>
            <LogoInstitucional alto={44} />
            {d.fotografia_id && (
              <ImagenFotografia fotografiaId={d.fotografia_id} alt={`Foto de ${d.nombres} ${d.apellidos}`} ancho={150} />
            )}
            <Stack gap={0} mt={4}>
              <Text fw={700} lh={1.2}>{d.nombres}</Text>
              <Text fw={700} lh={1.2} tt="uppercase">{d.apellidos}</Text>
            </Stack>
            {d.cargo && <Text size="sm" lh={1.25}>{d.cargo}</Text>}
            {d.unidad && <Text size="xs" c="dimmed" lh={1.25}>{d.unidad}</Text>}
            {d.cedula && <Text size="sm">C.I. {d.cedula}</Text>}
            {!credencial.vigente && <StatusBadge tone="danger" size="sm">{credencial.estado_etiqueta}</StatusBadge>}
          </div>
          <div className={classes.banda}>{identidad?.datos.nombre_corto || identidad?.datos.siglas || 'GADPE'}</div>
        </section>
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <section className={classes.tarjeta} aria-label="Reverso de la credencial">
          <div className={classes.cuerpo}>
            {credencial.qr && (
              <div className={classes.qr}>
                {/* Un data: URL que arma el backend: next/image no aporta nada aquí. */}
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={credencial.qr} alt="Código QR de verificación" />
              </div>
            )}
            <Text size="xs" c="dimmed">Escanee el código para verificar esta credencial.</Text>

            {d.valida_hasta && (
              <Stack gap={0}>
                <Text size="xs" c="dimmed" tt="uppercase">Válida hasta</Text>
                <Text fw={700}>{formatFecha(d.valida_hasta)}</Text>
              </Stack>
            )}
            {d.tipo_sangre && (
              <Stack gap={0}>
                <Text size="xs" c="dimmed" tt="uppercase">Tipo de sangre</Text>
                <Text fw={700}>{d.tipo_sangre}</Text>
              </Stack>
            )}
            {contacto && (
              <Stack gap={0}>
                <Text size="xs" c="dimmed" tt="uppercase">En caso de emergencia, avisar a</Text>
                <Text size="sm">
                  <b>{contacto.nombre}</b>{contacto.parentesco ? ` (${contacto.parentesco})` : ''}
                </Text>
                {contacto.telefono && <Text size="sm">{contacto.telefono}</Text>}
              </Stack>
            )}
          </div>
          <div className={classes.banda}>{identidad?.datos.sitio_web || identidad?.datos.siglas || 'GADPE'}</div>
        </section>
      </Grid.Col>
    </Grid>
  )
}
