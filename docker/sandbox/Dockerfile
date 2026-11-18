# The stand's OpenCart 4.1.0.4 image plus the uk-ua language files.
FROM base

# opencartbot/Ukrainian-Language-OpenCart-4 v1.0.7 («compatible with 4.1.0.0»). Only its language
# files are taken, nothing else from the archive is copied or run. Each uk-ua file is the en-gb file
# followed by the translation, so the ~10-17% of 4.1 keys it lacks fall back to English
# (OpenCart 4 has no fallback of its own and would print the key). The pack's one syntax slip
# (`'...'.;` in information/gdpr_success.php) is fixed on the way; a file that still does not
# parse stays English.
ARG UK_URL=https://github.com/opencartbot/Ukrainian-Language-OpenCart-4/releases/download/v1.0.7/ukrainian.ocmod.zip
ARG UK_SHA256=5438f169191aeea9ba57c7860f2715fa4492157b0ae4303e2259eb92a696e662
RUN set -eux; \
    curl -fsSL -o /tmp/uk.zip "$UK_URL"; \
    echo "$UK_SHA256  /tmp/uk.zip" | sha256sum -c -; \
    unzip -q /tmp/uk.zip 'admin/language/uk-ua/*' 'catalog/language/uk-ua/*' -d /tmp/uk; \
    for app in admin catalog; do \
      en=/opt/opencart/$app/language/en-gb; uk=/tmp/uk/$app/language/uk-ua; out=/opt/opencart/$app/language/uk-ua; \
      (cd "$en" && find . -type f -name '*.php') | while read -r f; do \
        mkdir -p "$out/$(dirname "$f")"; \
        { cat "$en/$f"; echo; [ ! -f "$uk/$f" ] || sed "0,/<?php/s///; s/'\\.;\$/';/" "$uk/$f"; } > "$out/$f"; \
        php -l "$out/$f" > /dev/null 2>&1 || { echo "uk-ua $app/$f does not parse, English kept"; cp "$en/$f" "$out/$f"; }; \
      done; \
      cp "$uk/uk-ua.png" "$out/uk-ua.png"; \
    done; \
    rm -rf /tmp/uk /tmp/uk.zip
