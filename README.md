# Random draw (mod_randomdraw)

An activity module for Moodle that lets each student draw random numbers,
for example to assign them games or tasks to test. Numbers are drawn from a
teacher-defined range, and the plugin always picks from the numbers that have
been drawn least often so far, so the overall distribution stays as even as
possible (the difference between the most- and least-drawn number is never
greater than one).

## Features

- Teacher sets the number range (e.g. 1–20), the minimum number of draws a
  student must make for the activity to count as complete, and an optional
  maximum.
- Students draw with a single click and always see all their drawn numbers.
- Teachers see every student's numbers, their progress, and a live chart of
  how often each number has been drawn.
- Concurrency-safe: simultaneous draws are serialised with Moodle's lock API.
- Full backup and restore support, including student draws.
- Privacy API implemented (export and deletion of a user's draws).

## Requirements

- Moodle 4.1 or later (developed and tested on Moodle 5.2 with PostgreSQL 16; uses only the Moodle DML API, so MySQL/MariaDB are supported as well).
- Works with PostgreSQL and MySQL/MariaDB; only Moodle's DML API is used.

## Installation

1. Site administration → Plugins → Install plugins.
2. Upload the ZIP package and follow the on-screen upgrade steps.

No further configuration is needed.

## Usage

1. Add the "Random draw" activity to a course.
2. Set the number range and the minimum/maximum draws per student.
3. Students open the activity and press "Draw number".
4. The activity shows teachers all draws and the number distribution; the
   "Reset all data" button clears every draw.

## Development

The plugin ships with a PHPUnit test suite (`tests/`). Run it from the
Moodle root with `vendor/bin/phpunit --testsuite mod_randomdraw_testsuite`
after initialising the PHPUnit environment. A GitHub Actions workflow runs
moodle-plugin-ci against several Moodle versions and databases on every push.

## Translations

The plugin ships with English strings only, as required by Moodle
Marketplace. Translations (a German one is already prepared) are provided
through Moodle's AMOS translation toolkit.

## Support and bug reports

Please report bugs and feature requests at
https://github.com/MarkusMau/moodle-mod_randomdraw/issues

## Licence

Copyright 2026 Markus Maurer.

GNU GPL v3 or later. See LICENSE.
