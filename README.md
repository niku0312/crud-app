# Skyward Notes Deployment Guide (Beginner Friendly)

This README acts as a complete “paint-by-numbers” manual for taking the Skyward Notes CRUD app from the GitHub repo to a working server. Every command includes context explaining **what** it does, **why** you run it, and **how** the relevant service behaves. If you execute the steps in order, you will finish with a PHP site running on Apache, backed by MySQL, reachable through a custom hostname (for example, `skywardnotes.com` mapped in your hosts file).

---

## 0. How the app works (mental model)

- **Apache** (web server) listens on port 80, receives HTTP requests, and forwards PHP files to the PHP runtime.
- **PHP** executes the app code that lives in `/var/www/notes-app/public` and `/var/www/notes-app/src`. The bootstrap file opens a database connection.
- **MySQL** stores every note in the `notes_app` database. PHP talks to MySQL through PDO using credentials from a `.env` file.
- **Environment variables** in `.env` keep secrets (DB host/user/password) outside the code so you can change them without editing PHP files.

Keep that flow in mind—the rest of the instructions simply wire up each layer.

---

## 1. Prepare Ubuntu and install services

### 1.1 Update the OS

```bash
sudo apt update && sudo apt upgrade -y
```

- `apt update` refreshes the package list so you install the latest versions.
- `apt upgrade -y` applies pending updates (security fixes, bug fixes). `-y` auto-confirms prompts.

### 1.2 Install required packages

```bash
sudo apt install -y \
    apache2 \
    mysql-server \
    php php-mysql php-cli php-mbstring php-xml \
    git
```

- `apache2`: HTTP server that serves files from `/var/www`.
- `mysql-server`: database engine used by the app.
- `php`: interpreter; `php-mysql` is the PDO MySQL driver; `php-cli` lets you run PHP commands manually; `php-mbstring` and `php-xml` cover string/DOM features used by many apps.
- `git`: used to download this repository.

### 1.3 Start services now and on boot

```bash
sudo systemctl enable --now apache2 mysql
```

- `systemctl enable` registers the service so it auto-starts on reboot.
- `--now` starts it immediately.

### 1.4 Confirm they are running

```bash
systemctl status apache2
systemctl status mysql
```

Look for `Active: active (running)` in each output. Press `q` to exit the status view.

---

## 2. Download the application code

### 2.1 Move into Apache’s document root

```bash
cd /var/www
```

Apache serves files from `/var/www` by default, so you keep the app there for consistency.

### 2.2 Clone the repository

```bash
sudo git clone https://github.com/niku0312/crud-app.git notes-app
```

- `git clone` copies the GitHub repo onto the server.
- The final `notes-app` argument creates `/var/www/notes-app` as the destination folder; rename it if you prefer.

### 2.3 Give Apache ownership

```bash
sudo chown -R www-data:www-data /var/www/notes-app
```

- `www-data` is the user and group that Apache runs under on Ubuntu.
- `-R` applies ownership recursively so every file/folder inside `notes-app` belongs to Apache.
- Without this step the web server might fail to read `.env`, log files, or cache directories.

### 2.4 Understand the folder structure

```
/var/www/notes-app
├── public/        # Contains index.php (only folder exposed to browsers)
├── src/           # Reusable PHP code, including database bootstrap
├── schema.sql     # SQL script that builds the database schema
└── README.md      # This document
```

`public/` must be the Apache document root so sensitive files (like `.env` or `schema.sql`) are never served.

---

## 3. MySQL configuration (database + user)

### 3.1 Optional hardening script

```bash
sudo mysql_secure_installation
```

This interactive script lets you set a root password, remove anonymous accounts, and disable remote root login. Safe defaults: answer **Y** to everything unless you have a reason not to.

### 3.2 Create the database and user

```bash
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS notes_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'notes_user'@'localhost'
  IDENTIFIED BY 'Notes!App23';

GRANT ALL PRIVILEGES ON notes_app.* TO 'notes_user'@'localhost';
FLUSH PRIVILEGES;
SQL
```

What each statement does:

- `CREATE DATABASE`: builds the database that stores notes. `utf8mb4` handles all characters (emoji, multi-language text).
- `CREATE USER`: makes a dedicated login. Using `'localhost'` restricts access so the account only works from the same machine, limiting exposure.
- `GRANT`: gives that user full control over the `notes_app` schema (needed for CRUD operations).
- `FLUSH PRIVILEGES`: tells MySQL to reload privilege tables so the new user works immediately.

**Password policy reminder:** MySQL’s default MEDIUM policy requires a password with uppercase, lowercase, number, and special characters, minimum eight characters. Adjust your string accordingly.

### 3.3 Import the schema

```bash
mysql -u notes_user -p notes_app < /var/www/notes-app/schema.sql
```

- `-u notes_user -p` logs in as the application user (it will prompt for the password you set).
- `notes_app` selects the database.
- `< schema.sql` pipes the SQL file into MySQL; this creates the `notes` table and indexes.

### 3.4 Test that the credentials really work

```bash
mysql -u notes_user -p -e "SHOW TABLES;" notes_app
```

Seeing `notes` in the output confirms both the login and the schema import succeeded.

---

## 4. Create the `.env` file

The PHP bootstrap reads environment variables from `.env` to build the DSN (connection string). Place the file in the project root so it sits next to `public/` and `src/`.

```bash
sudo tee /var/www/notes-app/.env > /dev/null <<'ENV'
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=notes_app
DB_USER=notes_user
DB_PASSWORD=Notes!App23
ENV

sudo chown www-data:www-data /var/www/notes-app/.env
sudo chmod 640 /var/www/notes-app/.env
```

Explanation:

- `tee ... <<'ENV'` writes the content between `ENV` markers into the file (here-doc syntax). We run it with `sudo` because the destination folder is owned by `root`.
- `chown` hands the file to Apache’s user so it can read the secrets.
- `chmod 640` enforces permissions: owner can read/write, group can read, everyone else has no access. This keeps database passwords safe.

Remember: whenever you change `.env`, reload Apache (`sudo systemctl reload apache2`) so PHP picks up the new values. PHP processes do not automatically re-read `.env` mid-flight.

---

## 5. Apache virtual host setup

### 5.1 Create a dedicated site configuration

```bash
sudo tee /etc/apache2/sites-available/notes-app.conf > /dev/null <<'APACHE'
<VirtualHost *:80>
    ServerName skywardnotes.com
    DocumentRoot /var/www/notes-app/public

    <Directory /var/www/notes-app/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/notes-error.log
    CustomLog ${APACHE_LOG_DIR}/notes-access.log combined
</VirtualHost>
APACHE
```

Key points:

- `ServerName` tells Apache which host header should hit this config. You can add `ServerAlias www.skywardnotes.com` if you also map that name.
- `DocumentRoot` must be `public/` so nothing else is exposed.
- `<Directory>` block grants Apache permission to serve files from that folder and honors `.htaccess` if you ever add one (`AllowOverride All`).
- Custom log files keep your app’s logs separate from other sites.

### 5.2 Enable the site and clean up defaults

```bash
sudo a2ensite notes-app.conf        # enable your vhost
sudo a2dissite 000-default.conf     # disable the placeholder site
sudo a2enmod rewrite                # allow URL rewriting (handy later)
sudo systemctl reload apache2
```

### 5.3 Silence the “ServerName” warning (optional but tidy)

```bash
echo 'ServerName skywardnotes.com' | sudo tee -a /etc/apache2/apache2.conf
sudo systemctl reload apache2
```

Adding the directive globally ensures `apachectl -S` no longer complains about the server’s fully qualified domain name.

### 5.4 Confirm Apache sees the new site

```bash
sudo apachectl -S
```

Look for a line similar to:

```
*:80                   skywardnotes.com (/etc/apache2/sites-enabled/notes-app.conf:1)
```

If you only see `/var/www/html`, re-run the enable/disable commands and reload Apache.

---

## 6. Map your hostname (local or public)

### 6.1 Local-only testing with hosts files

If you are running inside VirtualBox or on a private LAN, manually map the hostname to the VM’s IP:

- **Ubuntu VM** (`/etc/hosts`):

  ```
  127.0.0.1 skywardnotes.com
  ```

  This makes the VM itself resolve the domain to localhost.

- **Windows host** (`C:\\Windows\\System32\\drivers\\etc\\hosts`):

  ```
  192.168.1.120 skywardnotes.com
  ```

  Replace `192.168.1.120` with the VM’s IP from `ip addr show`. Editing this file requires running Notepad as Administrator.

Only machines with the hosts entry will know how to reach `skywardnotes.com`; it is invisible to the wider internet.

### 6.2 Public DNS + HTTPS (future production)

- Register the domain, then create an **A record** pointing to your public server IP.
- Open port 80 (and 443 if you want HTTPS) on your firewall/router/cloud security group.
- Install Let’s Encrypt certificates for HTTPS:

  ```bash
  sudo apt install -y certbot python3-certbot-apache
  sudo certbot --apache -d skywardnotes.com -d www.skywardnotes.com
  ```

Certbot automatically edits the Apache config to redirect HTTP → HTTPS and creates a cron job to renew the certificate.

---

## 7. Functional verification

### 7.1 Test from the server

```bash
curl http://skywardnotes.com
```

Receiving HTML confirms Apache + PHP are serving the site. A 500 error means PHP threw an exception; a 404 means Apache is not pointing at `public/` yet.

### 7.2 Test from your host machine

- Open `http://skywardnotes.com` in a browser.
- Create a note, edit it, delete it. All actions should succeed.

### 7.3 Double-check the database

```bash
mysql -u notes_user -p -e "SELECT id, title, created_at FROM notes ORDER BY created_at DESC LIMIT 5;" notes_app
```

Seeing the rows confirms MySQL writes are working.

---

## 8. Why permissions matter (quick reference)

| Item | Command | Reason |
|------|---------|--------|
| Directory ownership | `sudo chown -R www-data:www-data /var/www/notes-app` | Allows Apache to read PHP files, `.env`, cache directories, and write logs/uploads without permission errors. |
| `.env` mode | `sudo chmod 640 /var/www/notes-app/.env` | Prevents other users on the system from reading your database password. |
| Apache logs | `ErrorLog ${APACHE_LOG_DIR}/notes-error.log` | Splits error messages per site, so debugging is easier if you host multiple apps. |

Permission tip: if you ever see “Permission denied” in Apache logs, re-check ownership (`ls -l /var/www/notes-app`) and ensure the file is readable by `www-data`.

---

## 9. Troubleshooting (cause → fix)

- **HTTP 404**: Run `sudo apachectl -S`. If you do not see `skywardnotes.com`, re-enable the site (`sudo a2ensite notes-app.conf`) and reload Apache. Ensure `DocumentRoot` is `/var/www/notes-app/public`.
- **HTTP 500 with `SQLSTATE[HY000] [1698] Access denied`**: MySQL rejected the credentials. Fix by logging in manually (`mysql -u notes_user -p`), resetting the password if needed, updating `.env`, then reloading Apache.
- **`curl` works but browser does not**: You probably forgot to edit the Windows hosts file or NAT/bridged networking is misconfigured. Confirm you can ping the VM IP from Windows.
- **Nothing loads and Apache is down**: Check status (`systemctl status apache2`). If it failed to start, read `/var/log/apache2/error.log` for syntax errors in the vhost file.
- **MySQL password policy errors**: Run `SHOW VARIABLES LIKE 'validate_password%';` to see requirements. Choose a stronger password or (less ideal) lower the policy in MySQL configuration.

Keep `tail -f /var/log/apache2/notes-error.log` open in another terminal while testing; you will immediately see stack traces when something goes wrong.

---

## 10. Updating the app later

When you push new commits to GitHub, pull them onto the server and reload Apache:

```bash
cd /var/www/notes-app
sudo -u www-data git pull
sudo systemctl reload apache2
```

Using `sudo -u www-data` keeps file ownership consistent. If the update includes database changes, rerun the relevant SQL on MySQL before reloading Apache.

---

With this detailed walkthrough, even a first-time Ubuntu user can install the exact services required, understand what each command changes, and safely expose Skyward Notes through a custom hostname. Keep this README handy as your single source of truth during future deployments or migrations.
