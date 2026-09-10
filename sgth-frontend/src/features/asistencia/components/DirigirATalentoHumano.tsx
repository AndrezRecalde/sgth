'use client'

import { Switch } from '@mantine/core'
import { confirmar } from '@/components/ui'
import { useFirmantesVigentes } from '@/features/expediente/hooks/useFirmantes'
import type { FirmanteVigente } from '@/types/api'

/*
| El interruptor que dirige el permiso al jefe de Talento Humano.
|
| Activarlo cambia quién firma, y eso no puede pasar en silencio: pide
| confirmación y, en el mismo diálogo, dice quién va a firmar. El nombre sale
| del mismo endpoint y de la misma regla con que el backend lo sella al
| registrar el permiso —el jefe vigente de la unidad de Talento Humano, o quien
| lo subrogue—, así que lo que se ve aquí es lo que sale en el papel.
|
| Apagarlo no pregunta nada: volver al jefe inmediato no sorprende a nadie.
|
| Va aparte de `PermisoModal` para que la consulta del firmante solo se haga con
| el formulario abierto, y para poder usarlo tal cual en el portal del servidor.
*/

interface Props {
  activo: boolean
  /** El servidor del permiso, para no ofrecerle la opción a quien dirige TH. */
  servidorId?: number
  onCambiar: (activo: boolean) => void
}

const nombreDe = (firmante?: FirmanteVigente) =>
  [firmante?.servidor?.apellido, firmante?.servidor?.nombre]
    .filter(Boolean)
    .join(' ')

export function DirigirATalentoHumano({ activo, servidorId, onCambiar }: Props) {
  const { data: firmantes = [], isLoading } = useFirmantesVigentes()

  const jefeTh = firmantes.find(
    (f) => f.rol_firma === 'responsable_talento_humano',
  )

  // Nadie firma su propio permiso. El backend lo rechaza con un 422; es mejor
  // no ofrecer la opción que dejar llegar al error.
  const esElPropioJefe =
    !!servidorId && !!jefeTh?.servidor && jefeTh.servidor.id === servidorId

  const pedirConfirmacion = () =>
    confirmar({
      title: 'Dirigir al jefe de Talento Humano',
      confirmLabel: 'Sí, dirigir a Talento Humano',
      message: (
        <>
          El permiso se dirigirá al <b>jefe de Talento Humano</b> y no al jefe
          inmediato de la unidad administrativa, que no figurará en el
          documento.
          <br />
          <br />
          {jefeTh?.resuelto ? (
            <>
              Firmará <b>{nombreDe(jefeTh)}</b>, {jefeTh.cargo}
              {jefeTh.subrogado && ' (por subrogación)'}.
            </>
          ) : jefeTh ? (
            <>
              Hoy no hay quien firme por Talento Humano:{' '}
              {jefeTh.motivo_sin_resolver} El permiso saldrá con el cargo y sin
              nombre.
            </>
          ) : (
            <>
              No se pudo consultar quién dirige Talento Humano. El sistema lo
              resolverá al registrar el permiso.
            </>
          )}
        </>
      ),
      // El interruptor solo se enciende aquí: cancelar lo deja como estaba.
      onConfirm: () => onCambiar(true),
    })

  return (
    <Switch
      label="Dirigir al jefe de Talento Humano"
      description={
        esElPropioJefe
          ? 'No disponible: el servidor seleccionado es el propio jefe de Talento Humano.'
          : 'Omite al jefe inmediato: el permiso lo firma quien dirija la unidad de Talento Humano.'
      }
      checked={activo}
      // Mientras carga, el diálogo no tendría a quién nombrar.
      disabled={isLoading || esElPropioJefe}
      onChange={(e) => {
        if (e.currentTarget.checked) {
          pedirConfirmacion()
        } else {
          onCambiar(false)
        }
      }}
    />
  )
}
