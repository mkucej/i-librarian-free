[Top-level README](README.md)

### Mac OS X manual installation

If you are comfortable with Terminal and the command line, it is highly recommeded that you use [Homebrew](https://brew.sh/) or [MacPorts](https://www.macports.org/) to enable the use of UNIX based software on your Mac. In this case, the Linux instructions can be followed with paths modified as needed (Standard Macport installations live at /opt/local (e.g. /opt/local/var/www, not /var/www) while Brew lives at /usr/local )

The PHP extension Sodium will also be useful (php72-sodium, php73-sodium, or php74-sodium) as it is non-standard.

If you want a standalone installation, you will need to have an Apache + PHP stack installed. Details may vary depending on which PHP stack you are using. One option is to install [MAMP](https://www.mamp.info/en/mac/) but it comes with MySQL as well. To configure a MAMP based installation, the Linux instructions can be followed.

If you want to use the Apache server OS X comes with, then a new version of PHP is needed. Prior to Mac OS 10.10.1 (Yosemite), the default install of Mac OS included Apache and PHP built with the GD library. However, the PHP installed with Yosemite does not include GD, so you will need to install one that does: it is simplest to use the one line installation instructions at [http://php-osx.liip.ch/](http://php-osx.liip.ch/.). PHP also needs to be upgraded to 7.2 or above.

Warning: there are potential headaches with using the built-in Apache on 10.14+ and custom PHP modules due to Apple specific issues. Read through https://php-osx.liip.ch/ extensively (specifically [here](https://github.com/liip/php-osx/issues/249)).

Once PHP has been built successfully, edit /etc/apache2/httpd.conf using a text editor (e.g. TextEdit). You must make two changes:

* Enabling php, by removing the initial hash symbol from the line beginning "#LoadModule php5_module" (pre-yosemite), or adding a similar line with the path to wherever PHP has been installed, e.g.

    LoadModule php7_module /usr/local/php5-7.2.9-20180821-074958/libphp7.so

* The additional PHP extensions mentioned in the Linux instructions are provided as part of the LIIP build, except for Sodium. If you build PHP yourself, you should configure the compilation to include all of these extensions.

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

