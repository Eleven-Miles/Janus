# Janus
## The Greek God of Change

Janus is an extension for the WP CLI tool to allow for quick and easy updates across wordpress and its plugins. This includes both free and some premium plugins including:

- Advanced Custom Fields Pro
- Gravity Forms (and add-ons)
- and many more to come!

## Features

- Update Wordpress and commit these change automatically upon completion.
- Update Plugins in bulk, with individual commits, for future cherry-picking.
- Update Themes in bulk with a single commit. In the future we may add individual commits here.
- Update Translations in bulk. In the future we may add individual commits here.

Overall this is a super handy tool that speeds up the task of updating a wordpress site and all of its intergral parts without the need to manually go through each one individually. It also enables us to work within the constraints of git and offer granular controll over the changes that go live.



## Tech

Janus uses a number of open source projects to work properly:

- [WP CLI](https://wp-cli.org/) - The basis for all of this functionality
- [Grafvity Forms CLI](https://www.gravityforms.com/add-ons/cli-add-on/) - An addon that provides support for updating Gravity forms and any related addon plugins.

## Installation

Dillinger requires [Composer](https://getcomposer.org/) to run.

To get our update packages to be included in the WP CLI you will need to add the following code to your functions.php:

```php
new ElevenMiles/Janus/CLI();
```

This will then give you access to a plethora of update commands:

```sh
wp update all # update all of the following in bulk
wp update wordpress # update wordpress to the latest release
wp update plugins # update each of the plugins with available updates to the latest version
wp update themes # update all themes to their latest versions
wp update translations # update translations
```
