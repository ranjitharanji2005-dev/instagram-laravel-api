# Instagram Laravel API

Backend API for an Instagram Clone built using **Laravel, PHP, and MySQL**.

## Technologies Used

- PHP
- Laravel
- MySQL
- Laravel Sanctum
- Redis
- Google OAuth
- Two-Factor Authentication (2FA)

## Features

- User registration and login
- Google authentication
- Two-factor authentication
- User profiles
- Create and manage posts
- Like and comment on posts
- Follow and unfollow users
- Stories
- Direct messaging
- API authentication using Laravel Sanctum

## Installation

Clone the repository:

```bash
git clone https://github.com/ranjitharanji2005-dev/instagram-laravel-api.git
```

Move into the project folder:

```bash
cd instagram-laravel-api
```

Install PHP dependencies:

```bash
composer install
```

Copy the environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure your database settings in `.env`.

Run migrations:

```bash
php artisan migrate
```

Start the Laravel server:

```bash
php artisan serve
```

## Author

GitHub: https://github.com/ranjitharanji2005-dev