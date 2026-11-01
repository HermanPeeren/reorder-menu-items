# Reorder Menu Items (temporary)

A Joomla 6.1+ task plugin that gives the translated main menus of the User Guide
(guide.joomla.org) the order of the source menu.

Translator Feedback adds every translated menu item as the last child of its parent, in
the order the items happen to be translated. So the translated menus are not in the order
of the English one: on the top level *Contributors* often comes first instead of last.
This plugin fixes the order once, so the site can be published in more languages. The
same will come back later in Translator Feedback itself; see
[docs/plan.md](docs/plan.md).

## What it does

The plugin adds one task type to **System > Scheduled Tasks**, **Reorder menu items:
translated main menus**. For every translated main menu, a menu type
`mainmenu-<language>` (`mainmenu-de-de`, `mainmenu-fa-ir`, ...):

- **Under every parent, its children are put in the order of the items of `mainmenu`
  they are associated with.** An item that is associated with nothing in `mainmenu` stays
  after its siblings that are, and is listed as a warning.
- **Three groups are alphabetical instead**, in every language by its own titles:
  1. the children of *User Guide / Explanations*, with *Getting Started* on top;
  2. the children of *Admin Pages References*, with *Help Screens* on top;
  3. the field types under *Fields* (the items whose English title ends in "Field").
     *Fields and Field Groups* and *Field Parameters* stay before them, *Multilingual
     Sites* and *Template Overrides* after them. The list of field types in the article
     the *Fields* item opens is put in the same order.

  Only the children directly under those items: their own children keep the order of
  the source menu. Alphabetical means by the rules of the language (PHP's intl
  extension, when the server has it), ignoring case, accents and quotation marks:
  "Campo 'Editor'" sorts as "Campo Editor".
- With **Sort the source menu too** (on by default), the same three groups of `mainmenu`
  itself are put in alphabetical order. That moves *Scheduled Tasks* before *SEO*,
  *Admin Modules* to the top of the admin pages after *Help Screens*, and *Subform Field*
  between *SQL Field* and *Text Field*. Nothing else of `mainmenu` moves.

Other menus, the administrator menu and the top-level items of other menus between the
main menus keep their order.

#__menu is a nested set: the order of siblings is the order of their `lft`, and moving one
item renumbers everything after it. The task works out the whole new order first, gives
every item its new `lft` and `rgt` in one pass over the tree, and writes the ones that
change in one database transaction. It then reads the menu back and checks that the tree
is whole and in the new order. Nothing else of a menu item changes. The articles are
saved through com_content's model, so each has a version in its history with the note
"Reorder menu items: list in the order of the menu".

Running it again changes nothing.

## Using it

1. Take a backup of the site (Akeeba Backup), or at least of `#__menu` and `#__content`.
2. Install the package: `composer build` makes `build/plg_task_reordermenuitems-<version>.zip`,
   or take it from the [releases](https://github.com/HermanPeeren/reorder-menu-items/releases).
3. Create a task **Reorder menu items: translated main menus**. Keep **Dry run** on and run
   it. The task log lists every group of menu items that gets a new order, with the new
   order, and every article whose list changes.
4. Switch **Dry run** off and run it again. Clear the site cache.
5. Uninstall the plugin.

`php tools/verify-order.php <path-to-site>` checks the outcome on its own, read-only: the
nested set is whole, every translated menu follows `mainmenu`, the alphabetical groups are
alphabetical and the field type lists follow the menu.

On a copy of the User Guide of 1 November 2026 (10 main menus of 685 items) the run puts
110 groups in a new order, renumbers 6102 rows of #__menu and reorders the list in 9
articles, in about 9 seconds.

## Requirements

Joomla 6.1 or later (the plugin is loaded lazily, which Joomla 6.0 cannot do), PHP 8.3,
MySQL or MariaDB with InnoDB tables. The menu items need their Multilingual Associations,
as Translator Feedback makes them.

## Development

See [docs/development.md](docs/development.md).

## Licence

GNU General Public License version 3 or later; see [LICENSE](LICENSE).
