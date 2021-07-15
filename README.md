# Slim Boilerplate
This is a basic boilerplate for a PHP Slim REST API.
Features include:
- SQLite Database wrapper, which includes
  - Customisable database initialisation scripts
  - Helper functions to perform SELECT, INSERT, UPDATE, DELETE operations using associative arrays
- Authentication
  - Passwordless login with 'magic links' [why?](https://kelvinvanamstel.medium.com/should-we-embrace-magic-links-and-leave-passwords-alone-c73db7007fc4)
  
## Installation

1. Install the dependencies using `composer install` in the main directory. [help!](https://getcomposer.org/doc/00-intro.md)
2. Create a new file in the root directory names 'env.php'
```
<?php

/*
  Set environment variables
*/

/* Recaptcha */
putenv("GOOGLE_RECAPTCHA_SECRET=...");

/* Email SMTP */
putenv("SMTP_HOST=...");
putenv("SMTP_USERNAME=...");
putenv("SMTP_PASSWORD=...");
putenv("SMTP_FROM_NAME=...");
putenv("SMTP_FROM_EMAIL=...");

/* UserID and session cookie names */
putenv("COOKIE_USERID_NAME=UserID");
putenv("COOKIE_SESS_NAME=SessionToken");
```
