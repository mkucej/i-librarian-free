[Top-level README](README.md)

### Linux manual installation
1. If you did not use the DEB package, make sure you have installed these packages from repositories:
  - Either of these web servers is recommended (you may run *I, Librarian* with any web server)
    * **apache2 (may be named httpd)**
    * **nginx**
  - PHP (*I, Librarian* requires PHP 7.2+)
    * For Apache, **php** and **libapache2-mod-php**
    * For Nginx, **php-fpm** is recommended.
  - PHP Extensions
    * **php-sqlite3**: SQLite database for PHP.
    * **php-gd**, **php-curl**, **php-intl**, **php-xml**, **php-json**, **php-mbstring**, **php-zip**: Other required PHP extensions.
    * **php-ldap**: Required if using LDAP.
    * **php-sodium**: Encryption related requirement
  - External Utilities
    * **poppler-utils**: required for PDF indexing and for the built-in PDF viewer.
    * **ghostscript**: required for the built-in PDF viewer.
    * **tesseract-ocr**: optional OCR.
    * **libreoffice**: optional import of office files.

2. If you are installing from the tar.gz, login as `root` or use `sudo`, and extract files
  into a directory underneath the web server's root directory (e.g. `/var/www/librarian`). Example:

```bash
  tar -Jxf I-Librarian-*.tar.xz -C /var/www/librarian
```
3. Change the owner of the `data` sub-folder to the account that runs the web server. For Apache, this is usually `www-data`
and for Nginx, `nobody`. Example:

```bash
  chown -R www-data:www-data /var/www/librarian/data
```

4. Configure the web server appropriately:

 * Apache: Insert a setting like this example into the configuration file:

```apache_conf
Alias /librarian "/var/www/librarian/public"
<Directory "/var/www/librarian/public">
    AllowOverride All
    # Allow access from this computer
    Require local
    # Allow access from intranet computers
    Require ip 10
    Require ip 172.16 172.17 172.18 172.19 172.20
    Require ip 172.21 172.22 172.23 172.24 172.25
    Require ip 172.26 172.27 172.28 172.29 172.30 172.31
    Require ip 192.168
    # Insert Allow from directives here to allow access from the internet
    # "Require all granted" opens access to everybody
</Directory>
```

You may wish to alter who has access (e.g. to allow access from more IP numbers or domain names) - see the Apache [Authentication and Authorization HOWTO](https://httpd.apache.org/docs/2.4/howto/auth.html) for details.

 * Nginx: Add a block like this example to the `server` section:  (/var/www is assumed to be the root of the web server)

```nginx.conf
# if no directives, then access from all IPs is enabled
allow 127.0.0.1;
allow 10.0.0.0/8;
allow 172.16.0.0/16;
deny all;   # catch-all that denies everything else

location /library {
  # Ensures the URL `/library/` executes index.php
  index index.php;

  location ~ ^(.+\.php)(.*)$ {
    alias /var/www/library/public;
    fastcgi_split_path_info ^/library(.+\.php)(.*)$ ;
    fastcgi_pass   127.0.0.1:9000;
    include        fastcgi.conf;
    fastcgi_param PATH_INFO $fastcgi_path_info;

    # If you will be migrating data from version 4.10, then these settings are recommended to 
    # prevent FastCGI timeout problems in re-extracting the full text of all PDFs
    # They can be unset back to defaults after updating the database
    fastcgi_read_timeout 1200s;
    fastcgi_send_timeout 1200s;
  }

  # Maps the URL `/library/` to the correct file system location
  alias /var/www/library/public;
  try_files $uri $uri/ =404;
}
```

PHP-FPM must also be configured. Locate where the configuration files are (e.g. /etc/php or /etc/php74) and 

   1. Copy php.ini-production to php.ini (if it does not exist)  
   2. In php.ini, ensure cgi.fix_pathinfo=1 and configure important settings as needed (e.g. max_execution_time, max_input_time, memory_limit)  
   3. In php.ini, enable the installed extensions in the ``Dynamic Extensions`` section (e.g. extension=curl, no semicolon)  
   4. Double check and configure the file php-fpm.conf as desired  
   5. In the directory php-fpm.d, copy the example `www.conf.default` to `www.conf`  
   6. Edit and configure this new file which sets up the www pool  
     - `User` and `Group` should match the account running the PHP process  
     - `listen` should equal the setting in nginx.conf (e.g. listen = 127.0.0.1:9000)  

**A common source of problems is incorrect permissions and ownership - the web server and PHP must be able to read and execute the files**

5. Restart the web server (and also php-fpm if needed)

6. You can access your library in a browser at http://127.0.0.1/librarian

