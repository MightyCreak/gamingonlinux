# Gamingonlinux.com code

The awesome [Gamingonlinux](https://gamingonlinux.com) news site source code.


## Requirements

This site requires atleast PHP 5.6, Mysql 5.6 or MariaDB 10.0 and apache 2.4.  
It is also recommanded to have the following php extentions available: 

- Curl
- Mysql
- GD
- Json


## Setting up a dev enviroment

Setup apache to serve up PHP pages as with any other. Adjust `includes/config.php` as needed for your Mysql installation.  
Import the development SQL database from the stripped SQL file `includes/dev/db.sql`  

## Licensing

The GOL site source is MIT licensed, but we also use other scripts which use different licenses

- /includes/jscripts/select2/ (APACHE 2/GPL)
- /includes/jscripts/qTip2/ (MIT)
- /includes/jscripts/Pikaday/ (MIT/BSD)
- /includes/jscripts/fancybox/ (Creative Commons Attribution-NonCommercial 3.0)
- /includes/jscripts/jquery.form.min.js (MIT/GPL)
