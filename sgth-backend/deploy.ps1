git add .
git commit -m "fix(dispensario): pendientes de triaje sin filtro de fecha

Antes solo mostraba pendientes del dia actual, pero un
turno puede quedar pendiente de dias anteriores y debe
seguir siendo visible hasta que se le tome el triaje
o se cancele."
git push
