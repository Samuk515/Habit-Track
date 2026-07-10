# Habit Track

Habit Track is a web-based habit tracking system developed as a final year project for the Bachelor of Computer Application (BCA) program at D.A.V. College, Tribhuvan University.

## About

Habit Track lets users create habits, break them into subtasks, log daily activity, and automatically track current and longest streaks. Reminders and calendar events are generated from subtask activity, giving users a simple, unified view of their daily progress.

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP (PDO)
- **Database:** MySQL
- **Local server:** XAMPP
- **Version control:** Git & GitHub
- **Design:** Figma (desktop wireframes)
- **Diagramming:** draw.io, Excalidraw

## Features

### Implemented
- Secure user registration (CSRF protection, input validation, password hashing)
- Secure login (session-based auth, generic error messaging, session regeneration)
- Logout with full session and cookie cleanup
- Session-protected dashboard

### Planned
- Category and habit CRUD
- Subtask management
- Daily habit logging
- Automatic streak calculation (current and longest)
- Reminders linked to subtasks
- Calendar auto-populated from subtask activity
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
├── config/
│   └── db.php
├── includes/
│   ├── auth.php
│   ├── csrf.php
│   └── functions.php
├── public/
│   ├── assets/
│   │   └── css/
│   │       └── style.css
│   ├── register.php
│   ├── login.php
│   ├── logout.php
│   └── dashboard.php
└── sql/
    └── schema.sql
```

## Setup Instructions

1. Install [XAMPP](https://www.apachefriends.org/) and start Apache and MySQL.
2. Clone this repository into your XAMPP `htdocs` folder:
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs
   git clone https://github.com/Samuk515/Habit-Track.git habit-track
   ```
3. Create the database in phpMyAdmin (`http://localhost/phpmyadmin`) and run the schema from `sql/schema.sql`.
4. Update `config/db.php` with your local database credentials if different from the defaults.
5. Visit `http://localhost/habit-track/public/register.php` to create an account.

## Development Roadmap

This project follows an iterative development model:

- [x] **Iteration 1** — User Authentication
- [ ] **Iteration 2** — Habit and Category Management
- [ ] **Iteration 3** — Subtask and Logging
- [ ] **Iteration 4** — Streak Calculation
- [ ] **Iteration 5** — Reminders
- [ ] **Iteration 6** — Calendar Integration

## Author

**Samir Singh**
BCA, D.A.V. College — Tribhuvan University
