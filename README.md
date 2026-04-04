# Lean Online Judge (LeanOJ)

A lightweight online judge for Lean 4 theorem proving.

## Features
-   **Lean 4 Integration**: Verifies theorem proofs using the Lean 4 compiler.
-   **AXLE API Support**: Offloads computationally expensive verification to the AXLE infrastructure.
-   **Recursive Dependencies**: Problems can depend on other problems. Theorems from dependency problems are automatically prepended to the current problem's context.
-   **Discuz! Integration**: Built-in bridge for authentication with Discuz! forums.

## Database Schema (SQLite)

```sql
CREATE TABLE problems (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    statement TEXT,
    template TEXT,
    dependencies TEXT, -- JSON array of problems(id)
    creator_id INTEGER -- UID from Discuz!
);

CREATE TABLE submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem INTEGER NOT NULL REFERENCES problems(id),
    user INTEGER NOT NULL, -- UID from Discuz!
    source TEXT NOT NULL,
    status TEXT NOT NULL,
    time TEXT NOT NULL
);

CREATE TABLE problem_revisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem_id INTEGER NOT NULL REFERENCES problems(id),
    statement TEXT,
    template TEXT,
    user_id INTEGER NOT NULL,
    time TEXT NOT NULL
);
```

## Setup & Environment
1.  Configure `.env`:
    ```
    AXLE_API_KEY=your_key_here
    DB_PATH=db.sqlite
    ```
2.  Ensure `www-data` has write access to `db.sqlite`.
3.  Run the worker: `php worker.php`.
