# Poker Simulator

Poker Simulator is a simplified Texas Hold'em game where you play against AI bots. The project includes user authentication, persistent chip storage, animated gameplay, betting rounds, and match history.

## Features

- User registration and login
- Persistent chip balance
- Texas Hold'em gameplay against AI bots
- Animated cards and betting actions
- Hand history
- Automatic database creation
- Compatible with both Docker and XAMPP

## Running with Docker

1. Open a terminal in the project folder.

2. Build and start the containers:

```bash
docker compose up --build -d
```

3. Open your browser and access:

```text
http://localhost:8080
```

### Database Configuration

Docker uses the following database settings:

- Host: `db`
- Database: `poker_game`
- Username: `poker_user`
- Password: `poker_pass`
- External MySQL Port: `3307`

### Useful Commands

Start or rebuild:

```bash
docker compose up --build -d
```

View logs:

```bash
docker compose logs -f app
```

Stop containers:

```bash
docker compose down
```

Stop containers and remove the database volume:

```bash
docker compose down -v
```

## Running with XAMPP

1. Copy the project folder into the `htdocs` directory.

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

3. Open your browser and access:

```text
http://localhost/Poker_simulator
```

If your folder has a different name, replace `Poker_simulator` with the correct folder name in the URL.

### MySQL Port

If your MySQL server uses port **3307**, open:

```text
config/database.php
```

and change:

```php
$port = getenv('DB_PORT') ?: '3307';
```

### Default XAMPP Credentials

Username:

```text
root
```

Password:

```text
(empty)
```

## Automatic Database Setup

The application automatically creates the `poker_game` database and all required tables during the first execution.

If a database connection error occurs, verify:

- MySQL is running.
- The configured port is correct.
- The username and password match your local MySQL configuration.

## Gameplay

Poker Simulator is a simplified Texas Hold'em experience where you compete against AI-controlled opponents. The game includes user accounts, persistent chips, betting rounds, animated gameplay, and hand history while keeping the setup simple for local development with either Docker or XAMPP.
