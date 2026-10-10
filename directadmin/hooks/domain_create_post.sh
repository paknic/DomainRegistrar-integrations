#!/bin/sh
# DirectAdmin runs this as root after a domain is created, with $domain and $username in the environment.
# Registration runs in the background and always exits 0, so it can never fail or slow the domain creation.

PLUGIN_DIR=/usr/local/directadmin/plugins/paknic
PHP=/usr/local/bin/php
[ -x "$PHP" ] || PHP=php

if [ -n "$domain" ]; then
    nohup "$PHP" -q "$PLUGIN_DIR/scripts/register.php" "$domain" "$username" >>"$PLUGIN_DIR/data/paknic.log" 2>&1 &
fi

exit 0
