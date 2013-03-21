=== Semiologic Fixes ===
Contributors: Denis-de-Bernardy, Mike_Koepke
Donate link: http://www.semiologic.com/partners/
Tags: semiologic
Requires at least: 3.5
Tested up to: 3.5.1
Stable tag: trunk

Fixes a variety of WP and WP plugin bugs.


== Description ==

The Semiologic Fixes plugin was born at a time where it was borderline impossible to get any kind of patch committed to the WP code base. Conveniently, the WP API allows to work around all sorts of workflow issues and outright bugs through the use of plugin hooks.

When WP is broken and I feel there is little or no chances this will get fixed in WP itself, I generally maintain a fix in the Semiologic Fixes plugin. The same for a handful of non-forked WP plugins that are in Semiologic Pro. (A fork is when you opt to decide to maintain the code yourself.)

The exact bugs vary from a WP version to the next. Suffice it to say that you want this one to be active at all times.

= Help Me! =

The [Semiologic forum](http://forum.semiologic.com) is the best place to report issues.


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Change Log ==

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