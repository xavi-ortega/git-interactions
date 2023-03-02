# Git Interactions

Git Interactions empowers all developers to follow team guidelines and best practices.
Given the metrics, given the weaknesses and strengths of the team.

## Installation

Run the following commands:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan passport:install
npm run dev
```

### To consider
You will need a GitHub access token, and provide it in the `.env`

```dotenv
GITHUB_ACCESS_TOKEN="secret_token"
````

If you want the reports to be run in background, you may need to set database as queue connection in the `.env` file

```dotenv
QUEUE_CONNECTION=database
```

If you want to enable websocket server, you may need to set pusher as broadcast driver in the `.env` file

```dotenv
BROADCAST_DRIVER=pusher
```

## Usage

Open 3 terminals, and run

```bash
php artisan serve
```

```bash
php artisan websockets:serve
```

```bash
php artisan queue:work
```

Just throw a git repository url and start doing research.
