# Lean Online Judge (LeanOJ)

An online judge for Lean 4 theorem proving, optimized for **Instant Verification**, zero-dependency deployment, and seamless integration with the Discuz! forum system.

## Key Features

*   **Instant Judge**: Submissions are verified synchronously using the **AXLE v4.28.0 API**. No background workers or queues are required.
*   **Zero-Dependency**: No local Lean 4, Mathlib, or sandboxing tools needed on the server.
*   **Centralized Logging**: All verification logs are stored directly in the SQLite database.
*   **Discuz! Integration**: Dynamic user resolution via the forum database; no redundant local users table.
*   **Version History**: Full problem statement and template history with rollback capabilities.

## Architecture

*   **Core**: PHP 8.4+, HTML5, Vanilla CSS.
*   **Database**: SQLite (`db.sqlite`).
*   **Verification Engine**: Synchronous calls to **AXLE API** (`lean-4.28.0` environment).
*   **Authentication**: Integrated with Discuz! BBS via `DiscuzBridge`.

## Deployment

LeanOJ is designed for "zero-configuration" deployment.

1.  **Setup Database**: Ensure `db.sqlite` exists and is initialized.
    ```bash
    sqlite3 db.sqlite < init_db.sql
    ```
2.  **Configure Nginx/Apache**: Point your web root to the project directory.
3.  **AXLE Access**: The system is pre-configured to use the public AXLE verification endpoint.

## Project Structure

*   `index.php`: The monolithic controller handling all routes and instant verification.
*   `src/DiscuzBridge.php`: Handles session synchronization with the Discuz! BBS.
*   `templates/`: Pure PHP templates for the UI.
*   `init_db.sql`: The base schema for initializing the project.

## Database Schema

The system uses a simple SQLite schema:
*   `problems`: Problem statements, Lean templates, and recursive dependencies.
*   `submissions`: User solutions, status (`PASSED`/`ERROR`), and full compiler logs.
*   `problem_revisions`: Temporal snapshots of problems for history/rollback.
