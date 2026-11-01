# Developing the reorder menu items plugin

## Layout

`src/` is the root of the installable package, laid out as it will be on a site:

```
src/
  reordermenuitems.xml                   the manifest; <files folder="plugins/task/reordermenuitems">
  script.php                             install script: Joomla 6.1 / PHP 8.3 checks, enables the plugin
  plugins/task/reordermenuitems/
    services/provider.php                the composition root
    forms/reorder.xml                    the task's parameters
    language/en-GB/                      plg_task_reordermenuitems(.sys).ini
    src/
      Extension/ReorderMenuItems.php     the task plugin: parameters in, exit code out
      Reorder/Reorderer.php              one run: plan every main menu, write, check, order the article lists
      Menu/MenuItem.php, MenuTree.php    #__menu as a tree; new orders in, new lft/rgt out
      Order/MenuOrderPlanner.php         the order one menu should have
      Order/AlphabeticalRule.php         the groups that are alphabetical, with the User Guide's three
      Order/TitleCollator.php            alphabetical in a language (intl, or a fallback)
      Html/ListReorderer.php             the <li>s of a list in the order of the menu items they link to
      Store/                             #__menu, #__associations and #__content behind MenuStore
      Content/AdminModelSaver.php        saves through a component's model (from translated-links)
tests/
  Unit/                                  PHPUnit, no Joomla, no database
  Support/                               trees written as outlines, a small User Guide, an in-memory store
build/build.php                          zips src/ into build/plg_task_reordermenuitems-<version>.zip
tools/                                   install-local.php, verify-order.php, phpstan-bootstrap.php
```

The namespace is `Yepr\Plugin\Task\ReorderMenuItems`.

## How the order is made

`MenuTree::renumber()` is the nested-set part. It takes, per parent, the new order of all
its children, walks the whole tree from the root with every other parent's children in
their present order, and returns the `lft` and `rgt` of every item whose numbers change.
So moving a group moves its whole subtree, and the root still spans the same numbers.

`MenuOrderPlanner` plans one menu. Under each parent it only orders that menu's own
children: under the root, where the top level of every menu hangs, the others keep their
places. `MenuTree::merge()` puts the plans of all main menus together (each menu's items
take the places its items held), and `renumber()` numbers the result in one go.

An item's source is the item of `mainmenu` in its `com_menus.item` association group.
Rules name items by their path in `mainmenu`, so they hold for the translations too.

## The development site

`/joomla` holds Joomla 6.2.0, git-ignored. It was installed into a database of its own
and then pointed at a copy of the User Guide's database (`dbprefix` `xlhje_`), so the
task runs on the real menus:

```bash
curl -sSL -o joomla.tar.gz https://github.com/joomla/joomla-cms/releases/download/6.2.0/Joomla_6.2.0-Stable-Full_Package.tar.gz
mkdir -p joomla && tar -xzf joomla.tar.gz -C joomla && rm joomla.tar.gz
php joomla/installation/joomla.php install -n --site-name="Reorder menu items dev" --admin-user=Admin --admin-username=yourusername --admin-password=yourpassword --admin-email=admin@example.invalid --db-type=mysqli --db-host=127.0.0.1:3307 --db-user=root --db-pass= --db-name=guide-reorder --db-prefix=jrm_
rm -rf joomla/installation
# import the dump of the User Guide into guide-reorder, then in joomla/configuration.php:
#   public $dbprefix = 'xlhje_';
composer install-local
```

Create a task (in *System > Scheduled Tasks*, or with a row in `xlhje_scheduler_tasks` of
type `reordermenuitems.reorder`, params `{"dry_run":1,"source_menu":1,"as_user":0}`), run
it and check:

```bash
php joomla/cli/joomla.php scheduler:run --id=<id>
php tools/verify-order.php
```

The task log is `joomla/administrator/logs/joomla_scheduler.php`.

## Commands

| Command | What it does |
|---|---|
| `composer test` | PHPUnit |
| `composer analyse` | PHPStan, level 8, against the Joomla in `/joomla` |
| `composer cs` | phpcs: PSR-12, with Joomla's tabs in `src/` |
| `composer cs-fix` | php-cs-fixer on tests, build and tools |
| `composer build` | the package zip in `build/` |
| `composer install-local` | build, then install into `/joomla` through Joomla's CLI |
| `composer verify` | `tools/verify-order.php` on `/joomla` |

## Releasing

Bump `<version>` in `src/reordermenuitems.xml`, commit, and push a tag `v<version>`. The
release workflow checks that tag and manifest agree, runs the checks, builds the package
and attaches it to a GitHub release.
