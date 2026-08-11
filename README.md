# Habit Track

Habit Track is a web-based habit tracking system developed as a final year project for the Bachelor of Computer Application (BCA) program at D.A.V. College, Tribhuvan University.

## About

Habit Track lets users organize habits into categories, break them into subtasks, log daily activity, and automatically track current and longest streaks. Reminders and calendar events are generated from subtask activity, giving users a simple, unified view of their daily progress.

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP (PDO)
- **Database:** MySQL
- **Containerization:** Docker & Docker Compose
- **Web server:** Apache (php:8.2-apache)
- **Version control:** Git & GitHub
- **Design:** Figma (desktop wireframes)
- **Diagramming:** draw.io, Excalidraw

## Features

### Implemented
- Secure user registration (CSRF protection, input validation, password hashing)
- Secure login (session-based auth, generic error messaging, session regeneration)
- Logout with full session and cookie cleanup
- Session-protected dashboard with habit overview and streak display
- Category CRUD with pagination and cascading deletes
- Habit CRUD with measurement types (boolean, count, duration, weight, distance, rating, steps, custom, money, time of day, score, volume, partial), target values, and frequency targets
- Subtask CRUD with optional flag, reordering, and logging
- Daily habit logging (dashboard toggle and subtask-level logging with value/unit)
- Automatic streak calculation (current and longest) on every log change
- Bad habit progress tracking with value and notes
- Reminders linked to subtasks (once, daily, weekly) with pause/resume
- Calendar auto-populated from subtask activity with month grid and activity log
- Future category-based expansion (Finance, Health, Goals) without altering the ER schema

## Database Design

The system is built on a locked, 9-entity ER diagram:

| Entity | Description |
|---|---|
| USER | Registered user accounts |
| CATEGORY | User-defined habit categories |
| HABIT | Individual habits under a category |
| SUBTASK | Breakdown items within a habit |
| HABIT_LOG | Daily completion records |
| STREAK | Auto-calculated current and longest streak per habit |
| Bad_Habit_Progress | Progress tracking for habits marked "bad" |
| REMINDER | Reminders linked to subtasks |
| CALENDAR_EVENT | Calendar entries auto-generated from subtask activity |

> Future modules (Finance, Health, Goals) will be added as CATEGORY values with their own HABIT/SUBTASK entries — no new entities will be introduced.

## Project Structure

```
habit-track/
├── apache.conf
├── docker-compose.yml
├── Dockerfile
├── assets/
│   └── css/
│       └── style.css
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
│   │   └── register.php
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
│   └── schema.sql
└── public/
    ├── register.php
    ├── login.php
    ├── logout.php
    └── dashboard.php
```

## Setup Instructions

1. Install [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/).
2. Clone this repository:
   ```bash
   git clone https://github.com/Samuk515/Habit-Track.git habit-track
   cd habit-track
   ```
3. Start the stack:
   ```bash
   docker compose up --build
   ```
4. The app will be available at `http://localhost:8080`.
5. phpMyAdmin is available at `http://localhost:8081` (user: `root`, password: `root`).
6. The database is auto-initialized from `sql/schema.sql` on first run.
7. Visit `http://localhost/habit-track/public/register.php` to create an account.

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
