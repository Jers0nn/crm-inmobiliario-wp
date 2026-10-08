#!/usr/bin/env bash
#
# Genera el ZIP instalable del plugin a partir del último commit (HEAD).
# Los archivos marcados con export-ignore en .gitattributes (pruebas,
# herramientas, documentación para desarrolladores) no se incluyen.
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

echo "Paquete generado: $OUT"
unzip -l "$OUT"
