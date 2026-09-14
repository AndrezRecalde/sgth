/*
| Toda página del área autenticada pinta dentro de <PageShell>, también
| mientras carga o cuando el registro no existe (regla 05).
|
| La página casi nunca lo escribe: exporta `metadata` y devuelve una vista
| hermana (`<ExpedienteView />`). Por eso la regla sigue ese componente hasta
| su archivo y revisa cada `return` de la vista:
|
|   <PageShell>…           bien
|   <OtraVista />          se sigue a su vez (un esqueleto local, por ejemplo)
|   <Stack>, <Alert>…      mal: pierde el ancho de lectura y el ritmo vertical
|   null                   mal: la página queda en blanco
|
| Una página que no devuelve JSX —la que solo llama a `redirect()`— no pinta
| nada y pasa.
|
| Antes de la regla, tres vistas de detalle (ficha FEMO, convocatoria,
| plantilla) pintaban su carga sin PageShell y dos devolvían null si el
| registro no existía.
*/
import fs from 'node:fs'
import path from 'node:path'
import ts from 'typescript'

const PROFUNDIDAD_MAXIMA = 4

function parsear(archivo, texto) {
  return ts.createSourceFile(archivo, texto ?? fs.readFileSync(archivo, 'utf8'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX)
}

function resolverImport(desde, spec, raizSrc) {
  let base
  if (spec.startsWith('@/')) base = path.join(raizSrc, spec.slice(2))
  else if (spec.startsWith('.')) base = path.resolve(path.dirname(desde), spec)
  else return null
  for (const ext of ['.tsx', '.ts', '/index.tsx', '/index.ts']) {
    if (fs.existsSync(base + ext)) return base + ext
  }
  return null
}

/** La función (declaración, o const con flecha/función) con ese nombre, o la exportada por defecto. */
function buscarFuncion(sf, nombre) {
  for (const st of sf.statements) {
    if (ts.isFunctionDeclaration(st)) {
      const esDefault = st.modifiers?.some((m) => m.kind === ts.SyntaxKind.DefaultKeyword)
      if (nombre ? st.name?.text === nombre : esDefault) return st
    }
    if (nombre && ts.isVariableStatement(st)) {
      for (const d of st.declarationList.declarations) {
        if (ts.isIdentifier(d.name) && d.name.text === nombre && d.initializer
          && (ts.isArrowFunction(d.initializer) || ts.isFunctionExpression(d.initializer))) return d.initializer
      }
    }
  }
  return null
}

/** Las expresiones que devuelve la función, sin entrar en funciones anidadas. */
function devueltas(fn) {
  if (!fn.body) return []
  if (!ts.isBlock(fn.body)) return [fn.body]
  const out = []
  const visitar = (n) => {
    if (ts.isFunctionLike(n)) return
    if (ts.isReturnStatement(n)) {
      if (n.expression) out.push(n.expression)
      return
    }
    ts.forEachChild(n, visitar)
  }
  ts.forEachChild(fn.body, visitar)
  return out
}

function importDe(sf, nombre) {
  for (const st of sf.statements) {
    if (!ts.isImportDeclaration(st) || !st.importClause?.namedBindings) continue
    const nb = st.importClause.namedBindings
    if (ts.isNamedImports(nb) && nb.elements.some((e) => e.name.text === nombre)) return st.moduleSpecifier.text
  }
  return null
}

/**
 * Revisa lo que devuelve `fn` (declarada en `sf`). Devuelve la lista de
 * problemas como { archivo, linea, detalle }.
 */
function revisarFuncion(fn, sf, archivo, raizSrc, profundidad) {
  const problemas = []
  const linea = (n) => sf.getLineAndCharacterOfPosition(n.getStart()).line + 1

  const revisar = (expr) => {
    while (ts.isParenthesizedExpression(expr)) expr = expr.expression
    if (ts.isConditionalExpression(expr)) {
      revisar(expr.whenTrue)
      revisar(expr.whenFalse)
      return
    }
    if (expr.kind === ts.SyntaxKind.NullKeyword) {
      problemas.push({ archivo, linea: linea(expr), detalle: 'devuelve null: la página queda en blanco' })
      return
    }
    const tag = ts.isJsxElement(expr) ? expr.openingElement.tagName.getText()
      : ts.isJsxSelfClosingElement(expr) ? expr.tagName.getText()
        : ts.isJsxFragment(expr) ? '<>' : null
    if (tag === null) return // no es JSX: una llamada, un valor calculado
    if (tag === 'PageShell') return
    if (/^[A-Z]/.test(tag) && profundidad < PROFUNDIDAD_MAXIMA) {
      // Otro componente: local, o importado desde otro archivo.
      const local = buscarFuncion(sf, tag)
      if (local) {
        problemas.push(...revisarFuncion(local, sf, archivo, raizSrc, profundidad + 1))
        return
      }
      const spec = importDe(sf, tag)
      const destino = spec && resolverImport(archivo, spec, raizSrc)
      if (destino) {
        const sf2 = parsear(destino)
        const fn2 = buscarFuncion(sf2, tag)
        if (fn2) {
          problemas.push(...revisarFuncion(fn2, sf2, destino, raizSrc, profundidad + 1))
          return
        }
      }
    }
    problemas.push({ archivo, linea: linea(expr), detalle: `abre con <${tag === '<>' ? '' : tag}> en vez de <PageShell>` })
  }

  for (const expr of devueltas(fn)) revisar(expr)
  return problemas
}

export default {
  meta: {
    type: 'problem',
    docs: { description: 'Toda página del área autenticada pinta dentro de PageShell (regla 05).' },
    schema: [],
  },
  create(context) {
    const archivo = context.filename
    const raizSrc = path.join(context.cwd, 'src')
    return {
      'Program:exit'(programa) {
        const sf = parsear(archivo, context.sourceCode.text)
        const pagina = buscarFuncion(sf, null)
        if (!pagina) return
        for (const p of revisarFuncion(pagina, sf, archivo, raizSrc, 0)) {
          const donde = p.archivo === archivo ? `línea ${p.linea}` : `${path.relative(context.cwd, p.archivo).replace(/\\/g, '/')}:${p.linea}`
          context.report({
            node: programa,
            loc: { line: 1, column: 0 },
            message: `La página no pinta dentro de <PageShell>: ${donde} ${p.detalle}. Envuelva también la carga y el «no encontrado» (regla 05).`,
          })
        }
      },
    }
  },
}
