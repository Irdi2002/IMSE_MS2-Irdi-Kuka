# Inventory Management System

This repository contains a small PHP application used for demonstrations in the course *IMSE MS2*. It runs inside a Docker Compose stack that provides MySQL, MongoDB and the PHP web app.

## Docker Compose setup

1. Install **Docker** and **Docker Compose** on your machine.
2. Clone this repository and change into the project directory.
3. Start all containers:

   ```bash
   docker compose up --build
   ```
   
   The stack includes:

   - **MySQLDockerContainer** – MySQL database on port `6000` (root password `IMSEMS2`).
   - **MyAdminer** – Adminer UI for MySQL on <http://localhost:6080>.
   - **MyMongoDBContainer** – MongoDB server on port `27018` (user `Irdi`, password `Password1`).
   - **MyMongoDBExpress** – Mongo Express UI on <http://localhost:6081>.
   - **php-app** – Apache/PHP container exposing the application on <http://localhost:6060>.

Stop everything with `docker compose down`.

## Starting the PHP application

Once the containers are running, open your browser at:

```
http://localhost:6060/home.php
```

The home page provides links to view products, transfers and warehouses. It also offers actions to generate demo data or migrate the existing MySQL data to MongoDB.

## Migrating data to MongoDB or generating fake data

1. **Generate demo data** – On the home page click **"Generate Data"**. This executes `generate_data_using_faker.php` inside the container and fills the MySQL tables using the Faker library.
2. **Migrate data to MongoDB** – After data is available in MySQL, click **"Migrate Data to MongoDB"**. This runs `migrate_to_mongodb.php`, transfers the MySQL records into MongoDB and enables MongoDB mode for the application.

Both operations can also be triggered from the command line:

```bash
# Generate demo data
docker compose exec php-app-container php /var/www/html/generate_data_using_faker.php

# Migrate data
docker compose exec php-app-container php /var/www/html/migrate_to_mongodb.php
```

After migration you can browse the MongoDB collections via Mongo Express at <http://localhost:6081>.
