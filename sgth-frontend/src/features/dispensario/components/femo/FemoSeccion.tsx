import classes from './FemoSeccion.module.css'

interface Props {
  /** Letra de la sección en el formulario 028 del MSP: A, B, C… */
  letra: string
  titulo: string
  /** Aclaración breve. Para lo que el médico no puede deducir del título. */
  descripcion?: string
  /** Acción de la sección: agregar un antecedente, un examen, un diagnóstico. */
  accion?: React.ReactNode
  children: React.ReactNode
}

/**
 * Una sección de la ficha FEMO.
 *
 * La ficha reproduce el formulario 028 del MSP, que está organizado en
 * secciones con letra. Antes cada una se dibujaba a mano —el mismo bloque de
 * `Text` en versalita con un `letterSpacing` en línea, repetido catorce veces
 * en diez archivos— y la letra viajaba pegada al título como texto suelto
 * ("A. Datos generales").
 *
 * Separarla en su propio elemento no es cosmético: el médico llena esto con el
 * formulario impreso al lado y navega por letra. Que sea un marcador visible y
 * consistente es lo que hace que las dos cosas se puedan seguir en paralelo.
 */
export function FemoSeccion({
  letra, titulo, descripcion, accion, children,
}: Props) {
  return (
    <section className={classes.seccion}>
      <header className={classes.cabecera}>
        <span className={classes.letra} aria-hidden="true">{letra}</span>

        <div className={classes.titulos}>
          <h3 className={classes.titulo}>
            <span className="sgth-sr-only">Sección {letra}. </span>
            {titulo}
          </h3>
          {descripcion && (
            <span className={classes.descripcion}>{descripcion}</span>
          )}
        </div>

        {accion && <div className={classes.accion}>{accion}</div>}
      </header>

      {children}
    </section>
  )
}
