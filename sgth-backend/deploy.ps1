git add .
git commit -m "fix(core): incluir nombre_completo en la serializacion JSON del modelo User

PROBLEMA: el accessor getNombreCompletoAttribute() existia
pero no se incluia automaticamente al serializar el modelo
a JSON (no estaba en `$appends), causando que en cualquier
relacion (medico, emisor de certificado, etc) solo se viera
usuario_ti o email en vez del nombre completo real.

SOLUCION: atributo PHP 8 #[Appends(['nombre_completo'])]
afecta a TODOS los endpoints que serializan User, no solo
al dispensario."
git push
