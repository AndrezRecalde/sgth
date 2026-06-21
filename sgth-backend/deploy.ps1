git add .
git commit -m "fix(dispensario): eliminar eager load de relacion inexistente historiaClinica en TriajeController

El metodo store() cargaba with('historiaClinica') pero
AgendaMedica nunca tuvo esa relacion definida (es codigo
residual). resolverHistoriaClinicaId() ya resuelve el id
con consultas directas a HistoriaClinica, no necesita
la relacion eager-loaded."
git push
