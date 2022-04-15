# monolog2db
The handler writes a log record to a database

Why this is bad: your app will not to log if your database is down OR your app has an incorrect password to your database

Installation
--

Register the env variables
```shell
MYSQL_VERSION=8
MYSQL_HOST=mysql
MYSQL_PORT=3306
MYSQL_DATABASE=database_name
MYSQL_USERNAME=database_username
MYSQL_PASSWORD=database_password
LOG_FILE_FOR_UNEXPECTED_ERRORS=/tmp/php_unexpected_errors.log
MYSQL_TABLE_OPTIONALLY=optionally
```

Register the log handler
```yaml
services:
  YaPro\Monolog2db\LogRecordHandler:
        arguments:
            - '%env(MYSQL_HOST)%'
            - '%env(MYSQL_PORT)%'
            - '%env(MYSQL_DATABASE)%'
            - '%env(MYSQL_USERNAME)%'
            - '%env(MYSQL_PASSWORD)%'
            - '%env(LOG_FILE_FOR_UNEXPECTED_ERRORS)%'
            - '%env(MYSQL_TABLE_OPTIONALLY)%'
```

Configure the monolog ( example from a file config/packages/prod/monolog.yaml )
```yaml
monolog:
    handlers:
        main:
            type: service
            id: YaPro\Monolog2db\LogRecordHandler
        console:
            type: console
            process_psr_3_messages: false
            channels: ["!event", "!doctrine"]
```

Prepare database schema
```mysql
CREATE TABLE system_log (
    id INT AUTO_INCREMENT NOT NULL,
    project_name varchar(255) not null,
    source_name varchar(255) not null,
    level_name varchar(255) not null,
    message TEXT not null,
    datetime DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    http_request_id varchar(255) not null,
    channel varchar(255) not null,
    context LONGTEXT not null,
    extra LONGTEXT not null, 
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
```

Tests
------------
```sh
docker build -t yapro/monolog2db:latest -f ./Dockerfile ./
docker run --rm -v $(pwd):/app yapro/monolog2db:latest bash -c "cd /app \
  && composer install --optimize-autoloader --no-scripts --no-interaction \
  && /app/vendor/bin/phpunit /app/tests"
```

Dev
------------
```sh
docker build -t yapro/monolog2db:latest -f ./Dockerfile ./
docker run -it --rm -v $(pwd):/app -w /app yapro/monolog2db:latest bash
composer install -o
```
