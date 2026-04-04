# Lean Online Judge (Lean OJ)

The Lean Online Judge is a specialized platform for hosting and evaluating coding problems written in the **Lean 4** theorem prover. It provides a collaborative environment for problem creation, submission tracking, and automated evaluation.

---

## 🏗️ Backend Services
The system operates as a set of interconnected services on the Linux server:

1.  **`php8.4-fpm.service`**: The core web backend that serves the application interface and handles the RESTful API / UI routing.
2.  **`leanoj-worker.service`**: A persistent background runner (`worker.php`) that polls for new submissions and coordinates the lean compilation process.
3.  **`isolate.service`**: Manages the `cgroup` hierarchy used by the Isolate sandbox. This ensures that every Lean 4 submission is executed in a secure environment with strict resource limits (RAM/CPU).

---

## 🗄️ Database Schema
The application uses a centered SQLite database. The current schema is as follows:

```sql
CREATE TABLE local_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    path TEXT UNIQUE NOT NULL, -- Restricted to /var/www/leanoj/local_files/
    creator_id INTEGER NOT NULL, -- UID from Discuz!
    status TEXT NOT NULL DEFAULT 'PASSED',
    log TEXT
);

CREATE TABLE local_file_revisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    local_file_id INTEGER NOT NULL REFERENCES local_files(id),
    content TEXT NOT NULL,
    user_id INTEGER NOT NULL, -- UID from Discuz!
    time TEXT NOT NULL
);

CREATE TABLE problems (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    statement TEXT NOT NULL,
    template TEXT NOT NULL,
    dependencies TEXT, -- JSON array of local_files(id)
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
    statement TEXT NOT NULL,
    template TEXT NOT NULL,
    user_id INTEGER, -- UID from Discuz!
    time TEXT NOT NULL
);
```

---

## 📜 Multi-Level Revision History
Lean OJ features a robust version tracking system for both domain-specific data and server-side files:

### 1. Problem Statements & Templates
- **Revision Log**: Every modification to a problem's statement or Lean template creates a snapshot in `problem_revisions`.
- **Version Comparison**: Users can view side-by-side diffs (powered by `jsdiff`) to see exactly what changed.
- **Rollback**: Anyone can instantly revert a problem to any historical state.

### 2. Local Dependencies
- **Filesystem Tracking**: Dependencies are stored as physical `.lean` files in `/var/www/leanoj/local_files/`.
- **Content Versioning**: Every edit to a local file's content or path creates a snapshot in `local_file_revisions`. Snapshots capture the **previous** state before an overwrite occurs.
- **Creator Management**: The user who registered a file (the Creator) can edit content directly in the browser and manage the file's path.
- **Admin Oversight**: Admins can manage any file, even if they aren't the creator, and prune historical snapshots.
- **Rollback**: Logged-in users can revert a physical file to any previous content stored in the database history (requires creator or admin rights for some files).

---

## 🔐 Permissions & Ownership
- **Problem Ownership**: When a user creates a problem, they are designated as the owner.
- **Local Dependency Creator**: Every file registered has a `creator_id`.
- **Admin Access Only**: 
    - Full system control (Delete any problem, submission, or revision).
    - Modify restricted problem fields like **Title**.
- **Creator/Admin Rights**: 
    - Manage **Local Dependencies** (Register, Edit, and Rollback linked files).
- **Public/Logged-in Rights**: 
    - Can edit any problem's statement and template.
    - Can delete their own problems and their own submissions.

---

## 🏛️ Architecture Decisions

To provide immediate (synchronous) validation when a user registers a Local Dependency, and to thoroughly eliminate cached compilation artifacts when a file is deleted, the web backend requires elevated privileges typically isolated to the `worker`.
- **Decision:** Rather than building a complex asynchronous web-queue for dependency verification, we use a secure `sudoers` whitelist.
- **Implementation:** The `www-data` web user is granted `NOPASSWD` execution rights exclusively for two tightly-scoped scripts:
  - `leanoj_verify_local.sh`: Wraps `isolate` to synchronously compile `.lean` files during the HTTP POST request.
  - `leanoj_sweep_artifacts.sh`: Safely purges `.olean` files from the root-owned `.lake/build` directory upon file deletion.
- **Rationale:** This provides immediate user feedback on syntax errors during file registration without exposing generalized `root` access or overly complicating the background worker's queue design.

---

## 🚀 AXLE API Integration
Lean OJ utilizes the **AXLE API** (`https://axle.axiommath.ai/api/v1/`) for high-performance Lean 4 verification.

### 🔑 Authentication
The application requires an `AXLE_API_KEY` in the `.env` file. Requests include this key as a Bearer token in the `Authorization` header.

### 🛠️ Tools Used
The following AXLE tools are integrated into the core workflows:

#### 1. `check`
Used for **Template** and **Dependency** validation (in `index.php` and `worker.php`).
- **Purpose**: Verifies that a Lean file compiles correctly without checking specific proofs.
- **Example Payload**:
  ```json
  {
    "content": "-- Lean code here",
    "environment": "lean-4.28.0",
    "ignore_imports": true,
    "timeout_seconds": 120
  }
  ```

#### 2. `verify_proof`
Used for **Submission** grading (in `worker.php`).
- **Purpose**: Verifies a student's proof against the problem's formal template.
- **Example Payload**:
  ```json
  {
    "formal_statement": "theorem exercise_1 ...",
    "content": "proof ...",
    "environment": "lean-4.28.0",
    "ignore_imports": true,
    "timeout_seconds": 120
  }
  ```

### ⚙️ Default Environment
Unless otherwise specified, the application uses **`lean-4.28.0`** as the default environment for all API calls to ensure consistency between the web interface and the production verification results.
