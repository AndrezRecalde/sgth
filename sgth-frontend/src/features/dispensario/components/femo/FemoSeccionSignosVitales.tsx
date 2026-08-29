'use client'

import { Alert } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { FemoSeccion } from './FemoSeccion'
import classes from './FemoSeccionSignosVitales.module.css'

/** Orden del formulario 028: antropometría, luego constantes vitales. */
const CONSTANTES: { campo: string; etiqueta: string; unidad?: string }[] = [
  { campo: 'peso_kg', etiqueta: 'Peso', unidad: 'kg' },
  { campo: 'talla_cm', etiqueta: 'Talla', unidad: 'cm' },
  { campo: 'perimetro_abdominal_cm', etiqueta: 'P. abdominal', unidad: 'cm' },
  { campo: 'imc', etiqueta: 'IMC' },
  { campo: 'temperatura_c', etiqueta: 'Temperatura', unidad: '°C' },
  { campo: 'presion_sistolica', etiqueta: 'P. sistólica', unidad: 'mmHg' },
  { campo: 'presion_diastolica', etiqueta: 'P. diastólica', unidad: 'mmHg' },
  { campo: 'frecuencia_cardiaca', etiqueta: 'F. cardíaca', unidad: 'lpm' },
  { campo: 'frecuencia_respiratoria', etiqueta: 'F. respiratoria', unidad: 'rpm' },
  { campo: 'saturacion_oxigeno', etiqueta: 'Saturación O₂', unidad: '%' },
  { campo: 'glucosa', etiqueta: 'Glucosa', unidad: 'mg/dL' },
]

interface Props {
  constantesData: Record<string, number | null>
}

/**
 * Sección B del formulario 028.
 *
 * No se edita aquí: los signos vitales los toma Enfermería en Atención SSO y
 * llegan ya registrados. Por eso se presentan en un panel hundido, con la cifra
 * como protagonista y la unidad al lado — se leen de un vistazo y no se
 * confunden con los campos que el médico sí debe llenar.
 */
export function FemoSeccionSignosVitales({ constantesData }: Props) {
  const sinRegistro = CONSTANTES.every(
    ({ campo }) => constantesData[campo] === null || constantesData[campo] === undefined,
  )

  return (
    <FemoSeccion
      letra="E"
      titulo="Constantes vitales y antropometría"
      descripcion="Registrados por Enfermería en Atención SSO — solo lectura"
    >
      {sinRegistro ? (
        <Alert
          color="amber"
          variant="light"
          radius="lg"
          icon={<IconAlertTriangle size={18} />}
          title="Sin signos vitales"
        >
          Enfermería todavía no registra los signos vitales de esta solicitud.
          La ficha se puede seguir llenando, pero el dictamen debería esperar a
          que estén tomados.
        </Alert>
      ) : (
        <div className={classes.panel}>
          <dl className={classes.rejilla}>
            {CONSTANTES.map(({ campo, etiqueta, unidad }) => {
              const valor = constantesData[campo]
              const vacio = valor === null || valor === undefined

              return (
                <div key={campo} className={classes.dato}>
                  <dt className={classes.etiqueta}>{etiqueta}</dt>
                  <dd
                    className={`${classes.valor} ${vacio ? classes.sinDato : ''}`}
                    style={{ margin: 0 }}
                  >
                    {vacio ? '—' : valor}
                    {!vacio && unidad && (
                      <span className={classes.unidad}> {unidad}</span>
                    )}
                  </dd>
                </div>
              )
            })}
          </dl>
        </div>
      )}
    </FemoSeccion>
  )
}
