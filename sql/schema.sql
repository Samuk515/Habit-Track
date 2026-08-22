-- USER table
CREATE TABLE USER (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CATEGORY table
CREATE TABLE CATEGORY (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_name VARCHAR(100) NOT NULL,
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE
);

-- HABIT table
CREATE TABLE HABIT (
  habit_id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  habit_name VARCHAR(100) NOT NULL,
  habit_nature ENUM('good', 'bad') NOT NULL DEFAULT 'good',
  measurement_type ENUM('boolean', 'count', 'duration', 'weight', 'distance', 'rating', 'percentage', 'steps', 'custom', 'money', 'time_of_day', 'score', 'volume', 'partial') NOT NULL DEFAULT 'boolean',
  target_value INT NULL,
  target_type ENUM('daily', 'twice a week', 'weekly') NOT NULL DEFAULT 'daily',
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES CATEGORY(category_id) ON DELETE CASCADE
);

-- HABIT_LOG table (note: references HABIT table)
CREATE TABLE HABIT_LOG (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  habit_id INT NOT NULL,
  subhabit_id INT NULL,
  log_date DATE NOT NULL,
  value INT NULL,
  unit VARCHAR(30) NULL,
  status ENUM('done','skipped','partial') NOT NULL DEFAULT 'done',
  notes VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (habit_id) REFERENCES HABIT(habit_id) ON DELETE CASCADE,
  FOREIGN KEY (subhabit_id) REFERENCES SUBTASK(subtask_id) ON DELETE SET NULL,
  UNIQUE KEY unique_habit_per_day (habit_id, log_date)
);

-- STREAK table (references HABIT table)
CREATE TABLE STREAK (
  streak_id INT AUTO_INCREMENT PRIMARY KEY,
  habit_id INT NOT NULL UNIQUE,
  current_streak INT NOT NULL DEFAULT 0,
  longest_streak INT NOT NULL DEFAULT 0,
  FOREIGN KEY (habit_id) REFERENCES HABIT(habit_id) ON DELETE CASCADE
);

-- SUBTASK table (references HABIT table)
CREATE TABLE SUBTASK (
  subtask_id INT AUTO_INCREMENT PRIMARY KEY,
  habit_id INT NOT NULL,
  subtask_name VARCHAR(150) NOT NULL,
  description VARCHAR(255),
  is_optional TINYINT(1) NOT NULL DEFAULT 0,
  order_no INT NOT NULL DEFAULT 0,
  FOREIGN KEY (habit_id) REFERENCES HABIT(habit_id) ON DELETE CASCADE
);

-- Bad_Habit_Progress table (references HABIT_LOG table)
CREATE TABLE Bad_Habit_Progress (
  progress_id INT AUTO_INCREMENT PRIMARY KEY,
  log_id INT NOT NULL,
  log_date DATE NOT NULL,
  value INT NULL,
  notes VARCHAR(255),
  FOREIGN KEY (log_id) REFERENCES HABIT_LOG(log_id) ON DELETE CASCADE
);

-- REMINDER table (references SUBTASK table)
CREATE TABLE IF NOT EXISTS REMINDER (
    reminder_id INT AUTO_INCREMENT PRIMARY KEY,
    subtask_id INT NOT NULL,
    reminder_time TIME NOT NULL,
    reminder_type ENUM('once', 'daily', 'weekly') NOT NULL DEFAULT 'daily',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (subtask_id) REFERENCES SUBTASK(subtask_id) ON DELETE CASCADE
);

-- CALENDAR_EVENT table (references SUBTASK and HABIT_LOG tables)
CREATE TABLE IF NOT EXISTS CALENDAR_EVENT (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    subtask_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    ref_id INT NULL,
    FOREIGN KEY (subtask_id) REFERENCES SUBTASK(subtask_id) ON DELETE CASCADE,
    FOREIGN KEY (ref_id) REFERENCES HABIT_LOG(log_id) ON DELETE CASCADE
);
