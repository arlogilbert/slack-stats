# Slack Team Stats

[![MIT License][li]][ll]
[![Twitter][ti]][tl]

Have you ever wished that Slack had better stats? Perhaps you're managing a remote team on Slack and wish that you could see when people go online on Slack or see when people go offline on Slack? Well friend, want no more.

This is a small side project I built for my own needs, feel free to use it for any purpose as it is MIT licensed. If you see any changes that are important, feel free to improve it and submit a pull request.

## Dependencies & Requirements

This has been tested with Apache 2.4 with php-fpm running PHP 7.X latest stable. 

<span style="color:red">If you use a different version of Apache you will need to ensure your `.htaccess` file in the `apps` directory is updated accordingly, otherwise your password will be publicly available.</span>

## Installing

1. Import the `app/db/create.sql` into a database of your choosing and set up credentials
2. Install [composer][cl] if you don't already have it
3. `cd` into the `app` directory and run `composer install`
4. Create an app in Slack, assign the following user permissons:
* users:read
* users:read.email
* users.profile:read
* team:read 
5. Create a `.env` file in the `app` directory (see app/sampleEnv for the required variables) and replace the variables with yours
6. Create a crontab entry to run every 5 minutes such as `*/5 * * * * /PATH_TO/php /PATH_TO/app/cron.php`
7. Everything should be working!

[li]: https://img.shields.io/badge/license-MIT-brightgreen.svg
[ll]: app/LICENSE
[ti]: https://img.shields.io/twitter/url/https/arlogilbert.svg?style=social
[tl]: https://twitter.com/arlogilbert
[cl]: https://getcomposer.org/download/
