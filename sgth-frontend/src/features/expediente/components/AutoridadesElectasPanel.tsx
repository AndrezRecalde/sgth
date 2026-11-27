'use client'

import React from 'react'
import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconBuildingBank, IconInfoCircle, IconUserCheck, IconUserPlus, IconUserShare } from '@tabler/icons-react'
import { DataState, SectionHeading, SgthTable } from '@/components/ui'
import { useAutoridadesElectas, useAutoridadesEntrantes } from '../hooks/useAutoridadesElectas'
import { usePuedePrepararAcciones } from '../hooks/usePuedePrepararAccion'
import { usePuedeRegistrarActoDeAutoridad } from '../hooks/usePuedeRegistrarActoDeAutoridad'
import type { AutoridadElecta } from '../services/autoridadElectaService'
import { getAutoridadElectaColumns } from './autoridadesElectas.columns'
import { getAutoridadEntranteColumns } from './autoridadesEntrantes.columns'
import { AccionPersonalDetalleDrawer } from './AccionPersonalDetalleDrawer'
import { CesacionDeAutoridadModal } from './CesacionDeAutoridadModal'
import { FinDelPeriodoModal } from './FinDelPeriodoModal'
import { LicenciaDelConsejoModal } from './LicenciaDelConsejoModal'
import { InicioDelPeriodoModal } from './InicioDelPeriodoModal'
import { SubrogacionPorLeyModal } from './SubrogacionPorLeyModal'
import { VacacionesDeAutoridadModal } from './VacacionesDeAutoridadModal'

/**
 * El Prefecto y el Viceprefecto en funciones y su período (diseño, 9.14; TH
 * 1–7; fases 4.1 y 4.1b). Su inicio, su cese y la licencia del Consejo se
 * registran desde «Nueva acción de personal», sin acción ni firmantes; aquí se
 * ve cuándo termina cada período, la ausencia en curso, quién subroga y lo que
 * sigue. Se corrige el fin del período que el sistema le fijó a quien ya
 * estaba, y se registra la subrogación por ley de una ausencia que no nació de
 * una licencia.
 *
 * Debajo, las que entran (2026-10-21): su período todavía no rige, y si el de
 * la autoridad que sale no termina a tiempo, el aviso va arriba de todo.
 */
export function AutoridadesElectasPanel() {
  const { data: autoridades = [], isLoading, error, refetch } = useAutoridadesElectas()
  const { data: entrantes = [], error: errorEntrantes } = useAutoridadesEntrantes()
  const puedePreparar = usePuedePrepararAcciones()
  const puedeActoAutoridad = usePuedeRegistrarActoDeAutoridad()
  const [aFijar, setAFijar] = React.useState<AutoridadElecta | null>(null)
  const [subrogar, setSubrogar] = React.useState(false)
  const [actoVisto, setActoVisto] = React.useState<number | null>(null)
  const [iniciar, setIniciar] = React.useState(false)
  const [reelegir, setReelegir] = React.useState<AutoridadElecta | null>(null)
  const [conLicencia, setConLicencia] = React.useState<AutoridadElecta | null>(null)
  const [conVacaciones, setConVacaciones] = React.useState<AutoridadElecta | null>(null)
  const [aCesar, setACesar] = React.useState<AutoridadElecta | null>(null)

  const columns = getAutoridadElectaColumns({
    /*
    | Desde D3 (2026-11-27) manda `registrar-acto-autoridad`, y no el permiso
    | de las acciones de personal: los actos de una autoridad son otra cosa y
    | se pueden quitar por separado. Lo de no tramitar lo propio sigue
    | valiendo, así que se cruzan los dos.
    */
    puedePreparar: (servidorId) => puedeActoAutoridad && puedePreparar(servidorId),
    onFijarFin: setAFijar,
    onVerActo: setActoVisto,
    onReelegir: setReelegir,
    onLicencia: setConLicencia,
    onVacaciones: setConVacaciones,
    onCesar: setACesar,
  })
  const columnasEntrantes = getAutoridadEntranteColumns()
  const relevosSinCuadrar = entrantes.filter((e) => e.aviso)
  // Con Prefecto y sin nadie más en funciones, la Viceprefectura está vacante:
  // tras la asunción o la renuncia, el Consejo designa al nuevo (2026-10-20).
  const sinViceprefecto = autoridades.some((a) => a.es_prefecto) && autoridades.every((a) => a.es_prefecto)
  /*
  | Y el aviso inverso, que es el que de verdad urge: sin Prefecto no hay
  | autoridad nominadora, y ninguna acción de personal —de nadie— se puede
  | suscribir. Pasa entre el cese de uno y la asunción del otro, y hasta ahora
  | la pantalla no lo decía en ninguna parte.
  |
  | Con la lista vacía no se pinta: ya lo dice el estado vacío de la tabla.
  */
  const sinPrefecto = autoridades.length > 0 && autoridades.every((a) => !a.es_prefecto)
  const asuncionEnTramite = autoridades.find((a) => a.asuncion)

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        A las autoridades electas no se les emite acción de personal: su período
        empieza con la credencial del CNE —o con la resolución del Consejo, si
        designa al Viceprefecto— y se cierra solo en su fecha. Se registran desde
        aquí el inicio del período, el de la reelegida, que empieza cuando termina
        el actual sin cortar su antigüedad, y la subrogación por ley del
        Viceprefecto, que la licencia del Prefecto de más de tres días prepara
        sola (COOTAD Art. 51).
      </Alert>

      {relevosSinCuadrar.map((e) => (
        <Alert
          key={e.movimiento_id}
          variant="light"
          color="red"
          icon={<IconAlertTriangle size={16} />}
          title={`Antes de que entre ${e.servidor.nombre}`}
        >
          {e.aviso}
        </Alert>
      ))}

      {sinPrefecto && (
        <Alert variant="light" color="red" icon={<IconAlertTriangle size={16} />} title="No hay Prefecto en funciones">
          {asuncionEnTramite?.asuncion ? (
            <>
              El Viceprefecto {asuncionEnTramite.servidor.nombre} asume hasta terminar el período (COOTAD
              Art. 51), pero su acto sigue{' '}
              {asuncionEnTramite.asuncion.codigo_registro ? 'sin surtir efecto' : 'en trámite'}. Complételo
              desde «Lo que sigue»: mientras tanto <b>ninguna acción de personal se puede suscribir</b>, porque
              es el Prefecto quien firma como autoridad nominadora.
            </>
          ) : (
            <>
              Ninguna acción de personal se puede suscribir mientras no lo haya: es quien firma como autoridad
              nominadora. Si el anterior cesó, registre la asunción definitiva del Viceprefecto (COOTAD Art. 51)
              o el inicio del período de quien entra.
            </>
          )}
        </Alert>
      )}

      {sinViceprefecto && (
        <Alert variant="light" color="amber" icon={<IconInfoCircle size={16} />} title="No hay Viceprefecto en funciones">
          Regístrelo desde «Nueva acción de personal» con el «Inicio del período». Si se eligió junto con el
          Prefecto, con la causal «Elección» y su credencial del CNE. Si la Viceprefectura quedó vacante, lo designa
          el Consejo Provincial por resolución, de una terna del Prefecto (COOTAD Art. 47 n): causal «Designación del
          Consejo Provincial», que termina con el período del Prefecto y no lleva acta de posesión.
        </Alert>
      )}

      {puedePreparar(null) && (
        <Group justify="flex-end">
          <Button variant="light" leftSection={<IconUserCheck size={16} />} onClick={() => setIniciar(true)}>
            Registrar inicio del período
          </Button>
          <Button variant="light" leftSection={<IconUserShare size={16} />} onClick={() => setSubrogar(true)}>
            Registrar subrogación por ley
          </Button>
        </Group>
      )}

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las autoridades electas"
        onRetry={refetch}
        empty={!autoridades.length}
        emptyProps={{
          icon: IconBuildingBank,
          title: 'No hay autoridades electas en funciones',
          description: 'Aquí aparecen el Prefecto y el Viceprefecto, con su período y lo que sigue a él.',
        }}
      >
        <SgthTable records={autoridades} columns={columns} idAccessor="contrato_id" minHeight={200} />
      </DataState>

      {errorEntrantes && (
        <Alert variant="light" color="red" icon={<IconAlertTriangle size={16} />}>
          No se pudieron cargar las autoridades que entran.
        </Alert>
      )}

      {entrantes.length > 0 && (
        <Stack gap="xs">
          <SectionHeading title="Autoridades que entran" icon={<IconUserPlus size={16} />} />
          <Text size="sm" c="dimmed">
            Su período todavía no empieza. El día de la posesión pasan a la tabla de arriba; la autoridad a la que
            suceden deja de estar en funciones al terminar el suyo.
          </Text>
          <SgthTable records={entrantes} columns={columnasEntrantes} idAccessor="movimiento_id" minHeight={120} />
        </Stack>
      )}

      <AccionPersonalDetalleDrawer
        opened={actoVisto !== null}
        movimientoId={actoVisto}
        onClose={() => setActoVisto(null)}
      />

      <InicioDelPeriodoModal opened={iniciar} onClose={() => setIniciar(false)} />

      <InicioDelPeriodoModal
        opened={!!reelegir}
        onClose={() => setReelegir(null)}
        reeleccion={reelegir?.servidor.id ? {
          servidorId: reelegir.servidor.id,
          nombre: reelegir.servidor.nombre,
          puestoId: reelegir.puesto_id,
          hasta: reelegir.hasta,
        } : null}
      />

      <LicenciaDelConsejoModal
        opened={!!conLicencia}
        onClose={() => setConLicencia(null)}
        autoridad={conLicencia}
      />

      <VacacionesDeAutoridadModal
        opened={!!conVacaciones}
        onClose={() => setConVacaciones(null)}
        autoridad={conVacaciones}
      />

      <CesacionDeAutoridadModal
        opened={!!aCesar}
        onClose={() => setACesar(null)}
        autoridad={aCesar}
      />

      <FinDelPeriodoModal opened={!!aFijar} onClose={() => setAFijar(null)} autoridad={aFijar} />
      <SubrogacionPorLeyModal opened={subrogar} onClose={() => setSubrogar(false)} />
    </Stack>
  )
}
