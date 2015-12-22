There are some plugin types where, even though they are allowed to be uninstalled, at least one is required:

* format
* theme

---
Most blocks can be deleted, but if they are one of the default blocks for site, course etc Moodle will throw a coding_exception any time it attempts to add the default blocks (e.g. during installation for the site defaults). There are various settings that can be set to change the default block list (e.g. defaultblocks_override). See config-dist.php.

Setting defaultblocks_override to an empty string doesn't appear to work.

Checked the code and this is using empty(), so needs a space in it if you don't want any blocks. Maybe worth reporting on the Tracker? This might be one of the places it would be more appropriate to use isset()

Also, on first install, blocks_add_default_system_blocks() is called, which, in addition to adding the settings and navigation blocks, also attempts to add: admin_bookmarks, private_files, online_users, badges, calendar_month, calendar_upcoming, course_overview

---

Some plugin types prevent uninstallation entirely:

* gradingform
* cachestore
* webservice

This seems a bit odd and it might be worth investigating and put an improvement into the Tracker to make it more specific.

---

The unit test suite can't be run as the database setup installs all the default course and site blocks, and this can't be overridden from the config.php file (as unit testing appears only to pick up certain specific values from this).

Also, many tests rely on optional plugin code (which is fair enough as they're designed to be run over the full codebase, not a subset), so even if we could manage to install the testing databases successfully, the unit tests would still fail in many places.
