# [Start BileMo]

BileMo is an API created by Nicolas Emeriau. 
This API contains access to BileMo products. 
This allows a seller to integrate BileMo products into their catalog.

## Getting Started

Before using this website, follow this to get started:
* Clone the repo: 
```sh
`git clone git@github.com:NICOLASEMERIAU/bilemo.git
```
* Install docker environment, make sure to have updated version of docker and docker-compose.
* Go to the folder "bilemo" and open a terminal
```sh
docker compose build --no-cache
```
```sh
docker compose up -d
```
```sh
docker exec -it www_docker_symfony_bilemo bash
```
* Dans bash :
```sh
cd project
```
```sh
composer install
```

* Create your database and your first fixtures with orders from the container www_docker_symfony_bilemo
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
* Activate jwt
```sh
php bin/console lexik:jwt:generate-keypair
```
* Then add in "Authorize" => "bearer TOKEN"
* Let's go to http://localhost:8741/api/doc

## Bugs and Issues

Have a bug or an issue with this blog? [Open a new issue](https://github.com/NICOLASEMERIAU/bilemo/issues/new) here on GitHub.

## Creator

The Blog was created by and is maintained by Nicolas Emeriau.

* https://github.com/NICOLASEMERIAU

## Copyright

Copyright 2024 Toolsvet
