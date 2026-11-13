ALTER TABLE students
    ADD COLUMN email VARCHAR(190) NULL AFTER student_id;

CREATE UNIQUE INDEX students_school_email_unique
    ON students (school_id, email);
