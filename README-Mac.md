[Top-level README](README.md)

### Mac OS X manual installation

It is **HIGHLY** recommended that you use the package managers [Homebrew](https://brew.sh/) or [MacPorts](https://www.macports.org/) to enable the use of UNIX based software on your Mac. A Linux-like environment will be setup and the latest versions of the required software (web servers, PHP 7.2+, PHP extensions) can be easily downloaded and automatically compiled when/if necessary. The Linux instructions can then be followed with paths modified as needed (Standard Macport installations live at /opt/local (e.g. /opt/local/var/www, not /var/www) while Brew lives at /usr/local )

The PHP extension Sodium is essential (php72-sodium, php73-sodium, or php74-sodium); it is not usually part of a standard build of PHP on OS X.

----

If you want a standalone installation, you will need to have an Apache + PHP stack installed. Details may vary depending on which PHP stack you are using. Two options are [MAMP](https://www.mamp.info/en/mac/) and [XAMPP](https://xampp.site/) but they come with MySQL as well. To configure these installations, the Linux instructions can be followed. But missing PHP extensions will need to be downloaded (or compiled, like Sodium). Source for Sodium can be found at [PECL](https://pecl.php.net/).

----

If you want to use the Apache server OS X comes with, then a new version of PHP is needed. Below 10.15, the installed PHP is 7.1 or below - PHP 7.2 is a minimum. However, the PHP installed with 10.15 does not include some necessary extensions, so again you will need to compile something, e.g. Sodium. [PHPBrew](https://github.com/phpbrew/phpbrew) may be useful.

Warning: there are potential headaches with using the built-in Apache on 10.14+ and custom PHP modules due to Apple code signing issues. Read through https://php-osx.liip.ch/ extensively (specifically [here](https://github.com/liip/php-osx/issues/249)). That premade build of PHP does not include Sodium either if you want to try it out.

Once PHP has been built successfully, edit /etc/apache2/httpd.conf using a text editor (e.g. TextEdit). You must make two changes:

* Enabling php, by removing the initial hash symbol from the line beginning "#LoadModule php5_module" (pre-yosemite), or adding a similar line with the path to wherever PHP has been installed, e.g.

    LoadModule php7_module /usr/local/php5-7.2.9-20180821-074958/libphp7.so

* Adding a new Directory directive, by inserting: 

```apache_conf
Alias /librarian /Users/yourusername/librarian/public
<Directory /Users/Yourusername/librarian/public>
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

* Don't forget to change "yourusername" to your actual user name. You can find out your user name by typing `whoami` in Terminal.  
* You may wish to alter who has access (e.g. to allow access from more IP numbers or domain names) - see the Apache [Authentication and Authorization HOWTO](https://httpd.apache.org/docs/2.4/howto/auth.html) for details.  
* Restart Apache, by typing `sudo apachectl restart` in Terminal  
* Install LibreOffice, Tesseract OCR, Ghostscript, and Poppler.  
* Download *I, Librarian* source for Linux and double-click the file to extract its contents. Rename the extracted directory to 'librarian' and move it to your folder.  

* Make sure that the directory is accessible to *Others*. Use the `Get Info` dialog of the *Sites* directory to change permissions for *Everyone* to access and read (alternatively, run `chmod o+r ~/Sites/` at the terminal). You also need to make sure *Everyone* has **Execute** permissions for your home directory.
* Change the owner of the `data` sub-folder to the Apache user (_www for the default install). You can do this at the Terminal: `chown -R _www ~/librarian/data`.)
* Open your web browser and go to [http://127.0.0.1/librarian](http://127.0.0.1/librarian).

