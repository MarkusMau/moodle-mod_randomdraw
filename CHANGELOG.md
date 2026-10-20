# Changelog

## 1.0.0 (2026-10-20)

First public release.

- Students draw random numbers within a teacher-defined range.
- Frequency-balanced drawing: the least-drawn numbers are always picked
  first, so the overall distribution never deviates by more than one.
- Minimum and optional maximum number of draws per student.
- Teacher overview with per-student numbers and a distribution chart.
- Backup and restore, Privacy API, concurrency-safe draws via the lock API.
- PHPUnit test suite covering the draw logic, generator, privacy provider
  and backup/restore (docblock metadata is used so the tests run on
  Moodle 4.1 with PHPUnit 9 as well as on Moodle 5.x with PHPUnit 11).
