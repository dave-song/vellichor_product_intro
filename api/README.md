# Waitlist API

## Setup

1. Copy `config.sample.php` to `config.php`.
2. Set your MySQL credentials and table name in `config.php`.
3. Create the `waitlist` table (or the name you set) with at least:

```sql
CREATE TABLE waitlist (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120)  DEFAULT NULL,
  email      VARCHAR(255)  NOT NULL,
  phone      VARCHAR(30)   DEFAULT NULL,
  created_at DATETIME      NOT NULL,
  UNIQUE KEY (email)
);
```

## Security

- All inserts use **PDO prepared statements** (parameterized queries). User input is never concatenated into SQL, which prevents SQL injection.
- Input is trimmed and length-limited before use.
- Email is validated with `filter_var(..., FILTER_VALIDATE_EMAIL)`.
- Table name from config is whitelisted to alphanumeric and underscore only.

For production, also consider: HTTPS, rate limiting, and adding `config.php` to `.gitignore`.
