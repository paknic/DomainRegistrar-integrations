#!/bin/sh
# Run by DirectAdmin's plugin manager on install and update.
PLUGIN_DIR=/usr/local/directadmin/plugins/paknic

mkdir -p "$PLUGIN_DIR/data"
touch "$PLUGIN_DIR/data/paknic.log"
[ -f "$PLUGIN_DIR/data/config.json" ] || echo '{}' > "$PLUGIN_DIR/data/config.json"

# The settings page runs as diradmin; the hook runs as root. Only they may read the API secret.
chown -R diradmin:diradmin "$PLUGIN_DIR"
chmod 700 "$PLUGIN_DIR/data"
chmod 600 "$PLUGIN_DIR/data/config.json" "$PLUGIN_DIR/data/paknic.log"
chmod 755 "$PLUGIN_DIR/admin/index.html" "$PLUGIN_DIR/hooks/domain_create_post.sh" "$PLUGIN_DIR/scripts/"*.sh

echo "Paknic plugin installed. Open Admin > Extra Features > Paknic Domain Registration to configure it."
exit 0
