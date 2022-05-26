<?php

/*
 * Enter your custom paths below, if I, Librarian can't find them on it's own.
 */

// Path where the 'config' folder is, e.g. /etc/i-librarian.
$IL_CONFIG_PATH = getenv("CONFDIR");

// Path where the 'classes' and 'app' folders are, e.g. /usr/share/i-librarian.
$IL_PRIVATE_PATH =  getenv("INSTALLDIR");

// Path where the 'data' folder is, e.g. /var/lib/i-librarian.
$IL_DATA_PATH =  getenv("DATADIR");
