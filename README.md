droidperm_web
=============

Android permissions server side PHP scoring service.
On HTTP request, connects to the App Store and queries application permissions
against local SQLite database for unusual permission requests made by app.
Intially this was meant to work with
[phil-brown / Permissions-Checker](https://github.com/phil-brown/Permissions-Checker).

Dependencies
-------------
- webserver with PHP 5.2
- PHP support for SQLite3

License
-------------
- Code I wrote: http://opensource.org/licenses/BSD-3-Clause
- Android Market PHP API: https://github.com/splitfeed/android-market-api-php
