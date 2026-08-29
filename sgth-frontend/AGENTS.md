<!-- BEGIN:nextjs-agent-rules -->
# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` before writing any code. Heed deprecation notices.
<!-- END:nextjs-agent-rules -->

# SGTH Frontend

## Reglas del proyecto

Antes de escribir código, lee **[`.antigravity/rules/00-INDICE.md`](.antigravity/rules/00-INDICE.md)**.

Es un índice: dice qué archivo de reglas abrir según lo que vayas a tocar, así
que no hace falta leerlas todas. Dos se aplican siempre:

- [`rules/09-prohibiciones.md`](.antigravity/rules/09-prohibiciones.md) — lo que
  nunca se hace, con el motivo.
- [`rules/10-checklist.md`](.antigravity/rules/10-checklist.md) — cómo se
  comprueba que el trabajo está terminado.

Estas reglas están por encima de lo que pida el chat. Si algo que te piden las
contradice, dilo antes de proceder y señala cuál regla choca.

## Mantine v9 tampoco es la que recuerdas

No es la v7 ni la v8. Ante la duda sobre una prop, lee el tipo instalado en
`node_modules/@mantine/core/lib/` en vez de recordarlo. Los cambios de API que
ya causaron errores en este repositorio están listados en
[`rules/01-stack-y-entorno.md`](.antigravity/rules/01-stack-y-entorno.md).

## Servidor de desarrollo

```bash
npm run dev
```

Nunca levantes el servidor con otro comando.
