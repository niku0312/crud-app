# Skyward Notes

A minimal PHP + MySQL CRUD notebook designed for learning how to deploy a dynamic site to any cloud platform. It keeps the stack lightweight so you can focus on wiring up databases, environment variables, and runtime configuration.

## Features

- Create, read, update, and delete notes with timestamps.
- Clean responsive UI built with vanilla PHP templates and CSS.
- Simple PDO repository layer ready for scaling into services or controllers.
- `.env`-driven database configuration for cloud portability.
- SQL schema script for quick provisioning.

## Requirements

- PHP 8.1+ with the `pdo_mysql` extension enabled.
- MySQL 8 (or 5.7+) database instance.
- Composer is **not** required; everything runs on stock PHP.

## Quick start

1. **Install dependencies**
   - Ensure PHP and MySQL are installed locally or available in your cloud environment.
2. **Create a database**
   ```bash
   mysql -u root -p < schema.sql
   ```
   This creates a `notes_app` database and `notes` table.
3. **Configure environment variables**
   - Copy the sample file and adjust credentials:
     ```bash
     cp .env.example .env
     ```
   - Update the values to point at your MySQL instance (or set them directly in your hosting platform's dashboard).
4. **Serve the app locally**
   ```bash
   php -S localhost:8080 -t public
   ```
   Visit <http://localhost:8080> to start adding notes.

## Environment variables

| Key          | Description                         | Default       |
|--------------|-------------------------------------|---------------|
| `DB_HOST`    | MySQL host                          | `127.0.0.1`   |
| `DB_PORT`    | MySQL port                          | `3306`        |
| `DB_NAME`    | Database/schema name                | `notes_app`   |
| `DB_USER`    | Database user                       | `root`        |
| `DB_PASSWORD`| Database password                   | *(empty)*     |

Your cloud platform (Azure App Service, AWS Elastic Beanstalk, Render, etc.) should let you set these as application settings or secrets. The app automatically reads them at runtime.

## Deploying to the cloud

1. Upload or push the repository to your hosting provider.
2. Ensure the document root points to the `public/` folder so index.php is the entry point.
3. Provision a managed MySQL instance (or use services like PlanetScale, Azure Database for MySQL, RDS).
4. Import `schema.sql` into that database once.
5. Set the environment variables listed above.
6. Restart the app service. Health-check by curling the `/` route or opening it in a browser.

### Testing the deployment

- Add a note, edit it, and delete it to verify CRUD operations.
- Use your provider's log stream (e.g., `az webapp log tail`, `heroku logs --tail`) if errors occur.
- Enable HTTPS and configure a custom domain once everything works.

## Project structure

```
crud-app/
├── public/          # PHP entry point + static assets
├── src/             # Bootstrap + repository
├── schema.sql       # Database schema
├── .env.example     # Sample environment variables
└── README.md        # This guide
```

## Next steps

- Add authentication if you plan to expose the site publicly.
- Containerize with Docker (`php:8.2-apache`) for reproducible deployments.
- Hook into CI/CD to run `php -l` and integration tests before pushing.
