There are some plugin types where, even though they are allowed to be uninstalled, at least one is required:

* format
* theme

---
Most blocks can be deleted, but if they are one of the default blocks for site, course etc Moodle will throw a coding_exception any time it attempts to add the default blocks (e.g. during installation for the site defaults). There are various settings that can be set to change the default block list (e.g. defaultblocks_override). See config-dist.php.

Setting defaultblocks_override to an empty string doesn't appear to work.

Checked the code and this is using empty(), so needs a space in it if you don't want any blocks. Maybe worth reporting on the Tracker? This might be one of the places it would be more appropriate to use isset()

---
