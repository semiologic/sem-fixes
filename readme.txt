=== Semiologic Tweaks and Fixes ===
Contributors: Denis-de-Bernardy, Mike_Koepke
Donate link: https://www.semiologic.com/donate/
Tags: semiologic
Requires at least: 3.8
Tested up to: 4.3
Stable tag: trunk

Fixes a variety of WP and WP plugin bugs.


== Description ==

The Semiologic Fixes plugin provides tweaks and fixes to WordPress and the WordPress experience.  Some of these are quirkiness of WP itself.
Others are a difference in opinion of functionality that should or should not be present in WordPress (like emoji support).
These tweaks and fixes are implemented to make the site faster or improve the overall usability.

*Suffice it to say that you want this one to be active at all times.*

= Post Revisions =

Yikes! The post and page revisions get out of control if left unchecked.   If you don't specically set the 'WP_POST_REVISIONS" to false or a number to limit them to, sem-fixes sets a limit of 5.


= Help Me! =

The [Semiologic Support Page](https://www.semiologic.com/support/) is the best place to report issues.


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Change Log ==

= 3.0.1 =

- Disable the temp security module

= 3.0 =

- Temporaily Add basic security module
- Get rid of emoji support added 4.2.  Not every site is a blog, WP Core Team!
- Simply purge old tweaks from years ago.  Keep only necessary tweaks
- Allow WordPress Address and Site Address fields in Settings->General to be editable
- WP 4.3 compat
- Tested against PHP 5.6


= 2.8 =

- Performance improvements as some admin-only functionality was loaded for the front end.
- Move hashcash tweaking code to Hashcash reloaded plugin
- Dropped the inclusion of the libxml2 module
- Code refactoring

= 2.7 =

- WP 4.0 compat

= 2.6 =

- TADV toolbar settings got completely removed.  Redo toolbars, enabled plugins to work with TDAV 4.0+ and TinyMCE 4.0.

= 2.5 =

- Move widget PHP enable code and shortcode enable from sem-reloaded theme to this plugin

= 2.4.1 =

- Use more full proof WP version check to alter plugin behavior instead of relying on $wp_version constant.

= 2.4 =

- Post revision limiting was broken allowing unlimited revisions
- Remove date rules when using /%year%/%monthnum%/%postname%/ permalink structure
- Code refactoring
- WP 3.9 compat


= 2.3.1 =

- Fix license text

= 2.3 =

- TinyMCE configuration has been dropped from the plugin due to endless tinyMCE updates.   The [TinyMCE Advanced](http://wordpress.org/plugins/tinymce-advanced/) plugin should be directly installed to handled the appropriate add-on versions needed.
  The current toolbar setup will be initialized in the tinyMCE settings.
- Added custom wp_redirect function to better handle browser 301 caching glitches.
- Disable automatic WordPress updating
- Added back some post/page revision limiting.
- WP 3.8 compat

= 2.2 =

- WP 3.6 compat
- PHP 5.4 compat
- Removed post revision crippling and defer to WP settings for controlling revision control

= 2.1.5 =

- Yet another svn commit issue with new files

= 2.1.4 =

- TinyMCE popup js file backwards compatibility with pre-WP 3.5.  Broke this in the 2.1.3 release

= 2.1.3 =

- Update TinyMCE popup js file

= 2.1.2 =

- Fix plugin versioning

= 2.1.1 =

- Fixed unknown index warning

= 2.1 =

- Removed fixes now built into WordPress
- Add additional buttons to the Visual Editor
- Updated TinyMCE plugins
- WP 3.5 compat
- Update deprecated WP functions
- Cleaned up php lint items


= 2.0.4 =

- Improve disabling curl ssl verification.

= 2.0.3 =

- Drop TinyMCE media button (removed in WP 3.1)

= 2.0.2 =

- Fix curl w/ ssl
- P in WP Dangit fixes

= 2.0.1 =

- WP 3.0.1 compat

= 2.0 =

- WP 3.0 compat
- Don't break CDATA tags when fixing wpautop

= 1.9.6 =

- Further rewrite rule optimizations
- Add activate/deactivate handlers
- Prevent users from breaking their sites by editing the WP url
- Avoid using broken WP functions

= 1.9.5 =

- Add a couple of WP 2.9-related fixes
- More lib XML2 fixes

= 1.9.4 =

- Trim the junk added in TinyMCE by the buggy Skype plugin for FF
- WP 2.9 compat
- Play well with php code in posts

= 1.9.3 =

- Merge external libs into the plugin

= 1.9.2 =

- Fix typo / HTML validation

= 1.9.1 =

- Fix a race condition with the Semiologic theme

= 1.9 =

- Updated for WP 2.8 / Sem Pro 6.0