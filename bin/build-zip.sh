#!/usr/bin/env bash
#
# Genera el ZIP instalable del plugin a partir del último commit (HEAD).
# Los archivos marcados con export-ignore en .gitattributes (pruebas y
# herramientas) no se incluyen; README.md y SKILL.md se quitan después.
# Requiere git y zip.
#
# Uso: bin/build-zip.sh            -> dist/crm-inmobiliario-wp-<versión>.zip

set -euo pipefail

cd "$(dirname "$0")/.."

if ! git diff --quiet HEAD -- . 2>/dev/null; then
	echo "Aviso: hay cambios sin confirmar; el ZIP solo incluye lo confirmado en HEAD." >&2
fi

VERSION=$(sed -n 's/^ \* Version:[[:space:]]*//p' crm-inmobiliario-wp.php | tr -d '\r')
mkdir -p dist
OUT="dist/crm-inmobiliario-wp-${VERSION}.zip"

git archive --format=zip --prefix=crm-inmobiliario-wp/ -o "$OUT" HEAD

# La documentación para desarrolladores se queda en el repositorio y en el
# ZIP de GitHub (necesario para importar SKILL.md), pero no en el plugin.
zip -q -d "$OUT" crm-inmobiliario-wp/README.md crm-inmobiliario-wp/SKILL.md

echo "Paquete generado: $OUT"
unzip -l "$OUT"
