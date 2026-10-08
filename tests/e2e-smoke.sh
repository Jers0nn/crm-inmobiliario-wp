#!/usr/bin/env bash
#
# Prueba de humo de extremo a extremo por HTTP contra un WordPress de
# desarrollo con el plugin activo. Inicia sesión, crea, valida, busca y
# elimina un contacto a través de los formularios reales, y comprueba los
# nonces y permisos.
#
# Uso:  tests/e2e-smoke.sh http://localhost:8080 usuario_admin contraseña [usuario_sin_permisos contraseña]
#
# Úsalo SOLO en un sitio de pruebas: crea y elimina un contacto.

set -u

URL="${1:?Falta la URL del sitio}"
USER="${2:?Falta el usuario administrador}"
PASS="${3:?Falta la contraseña}"
USER2="${4:-}"
PASS2="${5:-}"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
JAR="$TMP/cookies"
PASSES=0
FAILS=0

ok()   { PASSES=$((PASSES + 1)); echo "  ✔ $1"; }
fail() { FAILS=$((FAILS + 1)); echo "  ✘ $1"; }
check() { if eval "$1"; then ok "$2"; else fail "$2"; fi; }

# login JAR USER PASS
login() {
	curl -s -c "$1" -b "$1" -o /dev/null "$URL/wp-login.php"
	curl -s -c "$1" -b "$1" -o /dev/null -w '%{http_code}' \
		--data-urlencode "log=$2" --data-urlencode "pwd=$3" \
		-d 'wp-submit=Acceder&testcookie=1' "$URL/wp-login.php"
}

# Extrae el valor de _wpnonce de un HTML.
nonce_of() { grep -o 'name="_wpnonce" value="[a-f0-9]*"' "$1" | head -1 | sed 's/.*value="//; s/"$//'; }

echo "Acceso anónimo"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$URL/wp-admin/admin.php?page=crmi")
check '[ "$CODE" = "302" ]' "sin sesión se redirige al acceso (HTTP $CODE)"

echo "Inicio de sesión"
CODE=$(login "$JAR" "$USER" "$PASS")
check '[ "$CODE" = "302" ]' "inicio de sesión del administrador (HTTP $CODE)"

echo "Pantallas"
for PAGE in crmi crmi-contacts crmi-properties crmi-followups crmi-settings; do
	CODE=$(curl -s -b "$JAR" -o "$TMP/page.html" -w '%{http_code}' "$URL/wp-admin/admin.php?page=$PAGE")
	check '[ "$CODE" = "200" ] && grep -q "crmi-wrap" "$TMP/page.html"' "$PAGE responde 200"
	check '! grep -qiE "(Fatal error|Warning:|Notice:|Deprecated:)" "$TMP/page.html"' "$PAGE sin errores PHP visibles"
done
check 'grep -q "crm-inmobiliario-wp/assets/css/admin.css" "$TMP/page.html"' "los estilos del plugin se cargan"

echo "Alta de contacto"
NAME="E2E Prueba $RANDOM$RANDOM"
curl -s -b "$JAR" -o "$TMP/form.html" "$URL/wp-admin/admin.php?page=crmi-contacts&action=new"
NONCE=$(nonce_of "$TMP/form.html")
check '[ -n "$NONCE" ]' "el formulario contiene un nonce"

curl -s -b "$JAR" -o "$TMP/invalid.html" \
	--data-urlencode "name=$NAME" --data-urlencode 'email=esto-no-es-correo' \
	-d "_wpnonce=$NONCE&id=0&crmi_save=1&status=nuevo&source=web" \
	"$URL/wp-admin/admin.php?page=crmi-contacts&action=new"
check 'grep -q "no es una dirección de correo válida" "$TMP/invalid.html"' "correo no válido muestra error"
check 'grep -q "value=\"$NAME\"" "$TMP/invalid.html"' "el formulario conserva lo escrito tras el error"

HEADERS=$(curl -s -b "$JAR" -o /dev/null -D - \
	--data-urlencode "name=$NAME" --data-urlencode 'email=e2e@example.com' --data-urlencode 'phone=600 123 456' \
	--data-urlencode 'notes=Línea 1
Línea 2' \
	-d "_wpnonce=$NONCE&id=0&crmi_save=1&status=cualificado&source=portal" \
	"$URL/wp-admin/admin.php?page=crmi-contacts&action=new")
LOCATION=$(echo "$HEADERS" | grep -i '^location:' | tr -d '\r' | sed 's/^[Ll]ocation: //')
ID=$(echo "$LOCATION" | grep -o 'id=[0-9]*' | head -1 | cut -d= -f2)
check 'echo "$LOCATION" | grep -q "crmi_msg=saved" && [ -n "$ID" ]' "guardar redirige a la ficha con aviso (id=$ID)"

curl -s -b "$JAR" -o "$TMP/edit.html" "$URL/wp-admin/admin.php?page=crmi-contacts&action=edit&id=$ID&crmi_msg=saved"
check 'grep -q "Registro guardado correctamente" "$TMP/edit.html" && grep -q "e2e@example.com" "$TMP/edit.html"' "la ficha muestra los datos guardados"

echo "Edición"
NONCE=$(nonce_of "$TMP/edit.html")
CODE=$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' \
	--data-urlencode "name=$NAME" -d "_wpnonce=$NONCE&id=$ID&crmi_save=1&status=negociacion&source=portal" \
	"$URL/wp-admin/admin.php?page=crmi-contacts&action=edit&id=$ID")
check '[ "$CODE" = "302" ]' "la edición se guarda (HTTP $CODE)"

echo "Búsqueda y filtros"
curl -s -b "$JAR" -o "$TMP/list.html" -G --data-urlencode "s=$NAME" -d 'page=crmi-contacts&status=negociacion' "$URL/wp-admin/admin.php"
check 'grep -q "$NAME" "$TMP/list.html" && grep -q "1 registro" "$TMP/list.html"' "la búsqueda con filtro encuentra el contacto editado"
curl -s -b "$JAR" -o "$TMP/list2.html" -G --data-urlencode "s=$NAME" -d 'page=crmi-contacts&status=nuevo' "$URL/wp-admin/admin.php"
check 'grep -q "0 registros" "$TMP/list2.html"' "el filtro por otro estado lo excluye"

echo "Seguimiento vinculado"
curl -s -b "$JAR" -o "$TMP/fu.html" "$URL/wp-admin/admin.php?page=crmi-followups&action=new&contact_id=$ID"
check 'grep -q "<option value=\"$ID\" selected=" "$TMP/fu.html"' "el contacto llega preseleccionado"
FNONCE=$(nonce_of "$TMP/fu.html")
FHEAD=$(curl -s -b "$JAR" -o /dev/null -D - --data-urlencode "title=Llamar a $NAME" \
	-d "_wpnonce=$FNONCE&id=0&crmi_save=1&contact_id=$ID&property_id=0&due_date=2020-01-01&status=pendiente" \
	"$URL/wp-admin/admin.php?page=crmi-followups&action=new")
FID=$(echo "$FHEAD" | grep -i '^location:' | grep -o 'id=[0-9]*' | head -1 | cut -d= -f2)
check '[ -n "$FID" ]' "se crea un seguimiento vinculado (id=$FID)"
curl -s -b "$JAR" -o "$TMP/dash.html" "$URL/wp-admin/admin.php?page=crmi"
check 'grep -q "Llamar a $NAME" "$TMP/dash.html" && grep -q "vencido" "$TMP/dash.html"' "el panel lo muestra como vencido"

echo "Seguridad"
CODE=$(curl -s -b "$JAR" -o "$TMP/bad.html" -w '%{http_code}' \
	--data-urlencode "name=Intruso" -d "_wpnonce=00000000&id=0&crmi_save=1" \
	"$URL/wp-admin/admin.php?page=crmi-contacts&action=new")
check '[ "$CODE" = "403" ]' "un nonce falso se rechaza (HTTP $CODE)"
CODE=$(curl -s -b "$JAR" -o /dev/null -w '%{http_code}' "$URL/wp-admin/admin-post.php?action=crmi_delete&entity=contacts&id=$ID")
check '[ "$CODE" = "403" ]' "borrar sin nonce se rechaza (HTTP $CODE)"

if [ -n "$USER2" ]; then
	JAR2="$TMP/cookies2"
	login "$JAR2" "$USER2" "$PASS2" >/dev/null
	CODE=$(curl -s -b "$JAR2" -o /dev/null -w '%{http_code}' "$URL/wp-admin/admin.php?page=crmi-contacts")
	check '[ "$CODE" = "403" ]' "un usuario sin capacidad no accede (HTTP $CODE)"
	DEL=$(grep -o "admin-post.php?action=crmi_delete[^\"]*id=$ID[^\"]*" "$TMP/list.html" | head -1 | sed 's/&#038;/\&/g; s/&amp;/\&/g')
	CODE=$(curl -s -b "$JAR2" -o /dev/null -w '%{http_code}' "$URL/wp-admin/$DEL")
	check '[ "$CODE" = "403" ]' "un usuario sin capacidad no puede borrar ni con el enlace del admin (HTTP $CODE)"
fi

echo "Borrado"
for PAIR in "followups:$FID" "contacts:$ID"; do
	ENTITY=${PAIR%%:*}
	RID=${PAIR##*:}
	curl -s -b "$JAR" -o "$TMP/l.html" "$URL/wp-admin/admin.php?page=crmi-$ENTITY&action=edit&id=$RID"
	DEL=$(grep -o "admin-post.php?action=crmi_delete[^\"]*" "$TMP/l.html" | head -1 | sed 's/&#038;/\&/g; s/&amp;/\&/g')
	LOC=$(curl -s -b "$JAR" -o /dev/null -D - "$URL/wp-admin/$DEL" | grep -i '^location:' | tr -d '\r')
	check 'echo "$LOC" | grep -q "crmi_msg=deleted"' "se elimina $ENTITY #$RID con enlace protegido"
done
curl -s -b "$JAR" -o "$TMP/gone.html" "$URL/wp-admin/admin.php?page=crmi-contacts&action=edit&id=$ID"
check 'grep -q "no existe" "$TMP/gone.html"' "el contacto ya no existe"

echo
echo "$PASSES correctas, $FAILS fallidas."
[ "$FAILS" -eq 0 ]
