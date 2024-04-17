# [Start Snowtricks]

Snowtricks is a website created by Nicolas Emeriau. 
This website contains a blog about snowboard tricks. 

## Getting Started

Before using this website, follow this to get started:
* Clone the repo: 
```sh
`git clone git@github.com:NICOLASEMERIAU/snowtricks.git
```
* Install docker environment, make sure to have updated version of docker and docker-compose.
* Go to the folder "blog" and open a terminal
```sh
docker compose build --no-cache
```
```sh
docker compose up -d
```
```sh
docker exec -it www_docker_symfony bash
```
* Dans bash :
```sh
cd project
```
```sh
composer install
```

* Create your database and your first fixtures with orders from the container www_docker_symfony
```sh
php bin/console doctrine:database:create
```
```sh
php bin/console make:migration
```
```sh
php bin/console doctrine:migrations:migrate
```
```sh
php bin/console doctrine:fixtures:load
```
* Configure your mail parameters in the file .env et le service MailerService.php
* Let's go to http://localhost:8741/

## Bugs and Issues

Have a bug or an issue with this blog? [Open a new issue](https://github.com/NICOLASEMERIAU/snowtricks/issues/new) here on GitHub.

## Creator

The Blog was created by and is maintained by Nicolas Emeriau.

* https://github.com/NICOLASEMERIAU

## Copyright

Copyright 2024 Toolsvet
