# I, Librarian Instructions
## Contents
  - Automated installation using installers
  - Windows manual installation
  - [Linux manual installation](README-Linux.md)
  - [Mac OS X manual installation](README-Mac.md)
  - First use
  - Un-installation

### Automated installation using installers
You can [download](https://github.com/mkucej/i-librarian-free/releases) and execute installers for Windows 8, and 10 plus a DEB
package and a console installer for Ubuntu, Debian, and its derivatives. An installer
for Mac OS X is not available. These installers will install and/or configure Apache
and PHP for you. If you don't want that, follow the instructions below to install manually.

### Windows manual installation
  * A running *Apache 2.4+* and *PHP 7.2+* are required. Install them using a Windows installer like WAMP.
  * Edit Apache configuration file (httpd.conf). Append this at the end using Notepad and edit as needed:

```apache_conf
Alias /librarian "C:\I, Librarian\public"
<Directory "C:\I, Librarian\public">
    AllowOverride All
    # Allow access from this computer.
    Require local
    # Allow access from intranet computers.
    Require ip 10
    Require ip 172.16 172.17 172.18 172.19 172.20
    Require ip 172.21 172.22 172.23 172.24 172.25
    Require ip 172.26 172.27 172.28 172.29 172.30 172.31
    Require ip 192.168
    # Insert Allow from directives here to allow access from the internet.
    # "Require all granted" opens access to everybody.
</Directory>
```

  * You may wish to alter who has access (e.g. to allow access from more IP numbers or domain names) - see the
    Apache [Authentication and Authorization HOWTO](https://httpd.apache.org/docs/2.4/howto/auth.html) for details.
  * Restart the server.
  * Unzip *I, Librarian* distributable files into `C:\I, Librarian`.
  * You may change `C:\I, Librarian` to any directory where you want to have *I, Librarian*,
    including an external drive.
  * Now you can access your library in a browser at http://127.0.0.1/librarian
  * *Optional.* You can install LibreOffice and Tesseract OCR to enable importing Office files and OCR, respectively. 

### First use
* Note on security: These installation instructions allow access to your library only from local computer
  or an internal network.
* In order to start *I, Librarian*, open your web browser, and visit:
  [http://127.0.0.1/librarian](http://127.0.0.1/librarian)
* Replace `127.0.0.1` with your static IP, or qualified server domain name, if you have either one.
* Migrate your previous library, or create an account and head to `Administrator > Software details` to see if everything checks fine.
* You should also check `Administrator > Global settings` to run *I, Librarian* the way you want.

**Thank you for using *I, Librarian*!**

### Un-installation
* If you used the DEB package, execute the `uninstall.sh` un-installer.
* Otherwise un-install all programs that you installed solely to use *I, Librarian*.
* These may include Apache, Nginx, and PHP - using package managers like Apt, Homebrew, or Macports will make this task easier. **Note: You might have other programs using these. Only remove if sure.**
* Delete *I, Librarian* directory.

### Development
* From root project execute:
```
docker build . -t librarian-dev -f docker/Dockerfile 
docker run --rm --name libra-dev -p 8082:80 -v $(pwd)/app:/usr/share/i-librarian/app -v $(pwd)/classes:/usr/share/i-librarian/classes -v $(pwd)/public:/usr/share/i-librarian/public -it librarian-dev
```

This will startup a Docker instance __libra-dev__ ready to debug php from VS Code using the extension: _xdebug.php-debug_