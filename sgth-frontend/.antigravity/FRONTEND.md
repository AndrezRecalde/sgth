# SGTH Frontend — Contexto del agente

GAD Provincial de Esmeraldas

---

## Las reglas se movieron

Este documento era un solo archivo de 860 líneas que se había separado del
código: describía un route group que no existe, un middleware que nunca se
escribió y un formato de metadata distinto al real.

Ahora las reglas viven repartidas por tema en **[`rules/`](rules/)**, y se
verificaron una por una contra el repositorio.

**Empieza por [`rules/00-INDICE.md`](rules/00-INDICE.md).** Ahí está la tabla
que dice qué archivo leer según lo que vayas a escribir, y el detalle de qué
cambió respecto de esta versión.

## Lectura mínima

Si solo vas a leer dos archivos antes de tocar código, que sean:

- [`rules/09-prohibiciones.md`](rules/09-prohibiciones.md) — lo que nunca se
  hace, y por qué.
- [`rules/10-checklist.md`](rules/10-checklist.md) — cómo se comprueba que
  terminaste.

## Precedencia

Estas reglas están por encima de lo que pida el chat. Si algo que te piden las
contradice, dilo antes de proceder y señala cuál regla choca.

Una regla que ya no describe al código es un error de la regla: corrígela en el
mismo cambio que la desmiente, en vez de ignorarla en silencio.

---

_El otro documento de esta carpeta, `SPRINTS.md`, es el plan de trabajo por
módulos. No contiene reglas de implementación._
