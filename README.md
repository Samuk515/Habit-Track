# Habit Track

Habit Track is a web-based habit tracking system developed as a final year project for the Bachelor of Computer Application (BCA) program at D.A.V. College, Tribhuvan University.

## About

Habit Track lets users organize habits into categories, break them into subtasks, log daily activity, and automatically track current and longest streaks. Reminders and calendar events are generated from subtask activity, giving users a simple, unified view of their daily progress.

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP 8.2
- **Email:** PHPMailer
- **Database:** MySQL
- **Containerization:** Docker & Docker Compose
- **Web server:** Apache (php:8.2-apache)
- **Version control:** Git & GitHub
- **Design:** Figma (desktop wireframes)
- **Diagramming:** draw.io, Excalidraw

## Features

### Implemented
- Secure user registration (CSRF protection, input validation, password hashing)
- Email verification for new accounts with Gmail/SMTP delivery, verification links, and six-digit OTP fallback
- Secure login (session-based auth, generic error messaging, session regeneration)
- Logout with full session and cookie cleanup
- Session-protected dashboard with habit overview and streak display
- Category CRUD with pagination and cascading deletes
- Habit CRUD with measurement types (boolean, count, duration, weight, distance, rating, steps, custom, money, time of day, score, volume, partial), target values, and frequency targets
- Subtask CRUD with optional flag, reordering, and logging
- Daily habit logging (dashboard toggle and subtask-level logging with value/unit)
- Automatic streak calculation (current and longest) on every log change
- Habit insights dashboard with weekly/monthly completion rates, missed days, streak KPIs, progress charts, and consistency ranking
- Bad habit progress tracking with value and notes
- Reminders linked to subtasks (once, daily, weekly) with pause/resume
- Calendar auto-populated from subtask activity with month grid and activity log
- Category-based expansion without altering the ER schema

## Database Design

The system is built on a locked, 9-entity ER diagram:

| Entity | Description |
|---|---|
| USER | Registered user accounts |
| EMAIL_VERIFICATION | Email verification tokens for new accounts |
| CATEGORY | User-defined habit categories |
| HABIT | Individual habits under a category |
| SUBTASK | Breakdown items within a habit |
| HABIT_LOG | Daily completion records |
| STREAK | Auto-calculated current and longest streak per habit |
| BAD_HABIT_PROGRESS | Progress tracking for habits marked "bad" |
| REMINDER | Reminders linked to subtasks |
| CALENDAR_EVENT | Calendar entries auto-generated from subtask activity |

> Future modules (Finance, Health, Goals) will be added as CATEGORY values with their own HABIT/SUBTASK entries — no new entities will be introduced.

## Project Structure

```
habit-track/
├── apache.conf
├── composer.json
├── docker-compose.yml
├── Dockerfile
├── index.php                  # Landing page and application entry point
├── landing.css
├── assets/
│   ├── css/
│   ├── images/
│   └── js/
├── includes/
│   ├── auth.php
│   ├── csrf.php
│   ├── db.php
│   ├── functions.php
│   └── logo.php
├── modules/
│   ├── auth/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── register.php
│   │   ├── resend-verification.php
│   │   └── verify-email.php
│   ├── calendar/
│   │   ├── Calendar.php
│   │   ├── Calendar.css
│   │   └── Calendar.js
│   ├── categories/
│   │   ├── categories.php
│   │   └── categories.css
│   ├── dashboard/
│   │   ├── dashboard.php
│   │   └── dashboard.css
│   ├── habits/
│   │   ├── habits.php
│   │   ├── habits.css
│   │   └── habits.js
│   ├── bad-habit-progress/
│   │   ├── bad-habit-progress.php
│   │   └── bad-habit-progress.css
│   ├── reminders/
│   │   ├── reminders.php
│   │   └── reminders.css
│   └── subtasks/
│       ├── subtasks.php
│       └── subtasks.css
├── sql/
│   └── schema.sql              # MySQL schema loaded on first database start
└── vendor/                     # Composer dependencies
```

## Setup Instructions

### Docker (recommended)

Prerequisites: [Docker Desktop](https://docs.docker.com/get-docker/) with Docker Compose.

1. Clone this repository:
   ```bash
   git clone https://github.com/Samuk515/Habit-Track.git
   cd Habit-Track
   ```
2. Start the application stack:
   ```bash
   docker compose up --build
   ```
3. Open the application at <http://localhost:8080>.
4. Create an account from the **Sign Up** link, or go directly to <http://localhost:8080/modules/auth/register.php>.

The stack includes:

| Service | Address | Purpose |
|---|---|---|
| Habit Track | <http://localhost:8080> | PHP application |
| phpMyAdmin | <http://localhost:8081> | Database administration |
| Mailpit | <http://localhost:8025> | Local email inbox |
| MySQL | `localhost:3307` | Database access from the host |

The MySQL database is initialized from `sql/schema.sql` the first time the database volume is created. To recreate the database from the schema, remove the existing volume before starting the stack again:

```bash
docker compose down -v
docker compose up --build
```

### Local PHP development

1. Install PHP 8.2+, MySQL 8+, Composer, and Apache.
2. Install the PHP dependency:
   ```bash
   composer install
   ```
3. Create the `habit_track_db` database and import `sql/schema.sql`.
4. Configure the database environment variables used by `includes/db.php`, then serve the repository root through Apache.

The Docker development credentials are `root` / `root`. Do not reuse these credentials in a production deployment.

## Email Verification

New users must verify their email before logging in. The verification email contains both a link and a six-digit OTP. To use the OTP, open the application's **Verify account with email OTP** page, enter the registration email and code, then log in.

For local development, Docker sends email to Mailpit. After registering, open <http://localhost:8025> and open the verification message. The OTP verification page is available at <http://localhost:8080/modules/auth/verify-email.php>.

For a non-Docker or production deployment, configure these environment variables in your server environment. When using Docker Compose, put them in a `.env` file next to `docker-compose.yml` so verification emails go to real inboxes instead of the local Mailpit inbox:

```bash
APP_URL=http://localhost:8080
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-smtp-user
SMTP_PASSWORD=your-smtp-password
MAIL_FROM=no-reply@example.com
MAIL_FROM_NAME="Habit Track"
```

`APP_URL` must be the URL that the recipient can open. For another user, do not leave it as `http://localhost:8080`; use the application's public HTTPS URL. Restart the web container after changing `.env` with `docker compose up -d --build web`.

`SMTP_SECURE` should be `tls` for a typical port 587 provider. For Mailpit, leave it empty and use port 1025. Never commit real SMTP credentials to the repository.

## Project Status

The core Habit Track application is complete, including authentication, Gmail/SMTP email delivery, OTP verification, habits, subtasks, logging, streaks, reminders, calendar integration, and dashboard insights.

### Next Steps

1. Deploy the Docker application to a server with a public HTTPS domain.
2. Set `APP_URL` to that public domain so verification links work for other users.
3. Configure production SMTP credentials in the server environment or an ignored `.env` file.
4. Replace the development MySQL and phpMyAdmin credentials before production use.
5. Back up the database and test registration, OTP verification, login, password handling, and account recovery on the deployed system.

## Development

PHP dependencies are managed with Composer. JavaScript and CSS are served directly by Apache; there is no frontend build step.

Useful commands:

```bash
# Start the application and follow logs
docker compose up

# Stop the application without deleting database data
docker compose down

# Check the PHP dependency tree
composer show
```

## Development Roadmap

This project follows an iterative development model:

- [x] **Iteration 1** — User Authentication
- [x] **Iteration 2** — Habit and Category Management
- [x] **Iteration 3** — Subtask and Logging
- [x] **Iteration 4** — Streak Calculation
- [x] **Iteration 5** — Reminders
- [x] **Iteration 6** — Calendar Integration

## Author

**Samir Singh**
BCA, D.A.V. College — Tribhuvan University
