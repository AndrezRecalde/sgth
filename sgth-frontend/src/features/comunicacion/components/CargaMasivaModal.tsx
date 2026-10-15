'use client'

import { Button, Group, Progress, Stack, Text } from '@mantine/core'
import { CountBadge, ModalFooter, SgthModal, SgthTable } from '@/components/ui'
import { useCargaMasiva } from '../hooks/useCargaMasiva'
import type { InformeCarga } from '../services/tipos'
import { ElegirFotosLote } from './ElegirFotosLote'
import { columnasInformeCarga } from './informeCarga.columns'

interface Props {
  opened: boolean
  onClose: () => void
}

function Informe({ informe, verbo }: { informe: InformeCarga; verbo: 'cargarían' | 'cargaron' }) {
  return (
    <Stack gap="xs">
      <Group gap="md">
        <Group gap={6}><CountBadge destacado>{informe.resumen.se_cargan}</CountBadge><Text size="sm">se {verbo}</Text></Group>
        <Group gap={6}><CountBadge>{informe.resumen.se_omiten}</CountBadge><Text size="sm">{verbo === 'cargaron' ? 'no se cargaron' : 'se omitirían'}</Text></Group>
      </Group>
      <SgthTable records={informe.archivos} columns={columnasInformeCarga} idAccessor="archivo" height={320} minHeight={120} />
    </Stack>
  )
}

/**
 * La carga masiva (fase 1.2), en tres pasos: elegir las fotos, revisar qué
 * pasaría con cada una —sin subir nada— y cargar en tandas.
 */
export function CargaMasivaModal({ opened, onClose }: Props) {
  const carga = useCargaMasiva()
  const cargando = carga.avance !== null

  const cerrar = () => {
    if (cargando) return
    carga.reiniciar()
    onClose()
  }

  const footer = carga.resultado ? (
    <ModalFooter onCancel={cerrar} cancelLabel="Cerrar" sinPrincipal />
  ) : carga.revision ? (
    <ModalFooter
      onCancel={cerrar}
      leftSection={!cargando && <Button variant="default" onClick={carga.reiniciar}>Empezar de nuevo</Button>}
      onSubmit={carga.cargar}
      submitLabel={`Cargar ${carga.revision.resumen.se_cargan} ${carga.revision.resumen.se_cargan === 1 ? 'foto' : 'fotos'}`}
      submitting={cargando}
      submitDisabled={carga.revision.resumen.se_cargan === 0}
    />
  ) : (
    <ModalFooter
      onCancel={cerrar}
      onSubmit={carga.revisar}
      submitLabel="Revisar el lote"
      submitting={carga.revisando}
      submitDisabled={carga.archivos.length === 0}
    />
  )

  return (
    <SgthModal opened={opened} onClose={cerrar} title="Carga masiva de fotos" size="xl"
      closeOnClickOutside={!cargando} closeOnEscape={!cargando} withCloseButton={!cargando}>
      <Stack gap="md">
        {carga.resultado ? (
          <Informe informe={carga.resultado} verbo="cargaron" />
        ) : carga.revision ? (
          <>
            {cargando && carga.avance && (
              <Stack gap={4}>
                <Text size="sm">Subiendo {carga.avance.hechas} de {carga.avance.total}…</Text>
                <Progress value={carga.avance.total ? (carga.avance.hechas / carga.avance.total) * 100 : 0} animated />
              </Stack>
            )}
            <Informe informe={carga.revision} verbo="cargarían" />
          </>
        ) : (
          <ElegirFotosLote
            cantidad={carga.archivos.length}
            rechazados={carga.rechazados}
            reemplazar={carga.reemplazar}
            onReemplazar={carga.setReemplazar}
            onAgregar={carga.agregar}
            onQuitarTodas={carga.reiniciar}
          />
        )}
      </Stack>
      {footer}
    </SgthModal>
  )
}
