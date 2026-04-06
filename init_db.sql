DROP TABLE IF EXISTS problems;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS problem_revisions;

CREATE TABLE problems (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    statement TEXT,
    template TEXT,
    dependencies TEXT,
    creator_id INTEGER
);

CREATE TABLE submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem INTEGER NOT NULL,
    user INTEGER NOT NULL,
    source TEXT NOT NULL,
    time TEXT NOT NULL
);

CREATE TABLE problem_revisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    problem_id INTEGER NOT NULL REFERENCES problems(id),
    statement TEXT NOT NULL,
    template TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    time TEXT NOT NULL
);
