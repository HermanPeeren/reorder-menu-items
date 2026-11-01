# Plan: the order of translated menus

## The problem

Translator Feedback places a translated menu item with `setLocation($parent, 'last-child')`:
under the translation of its source's parent, after whatever is there already. The items
are translated in no particular order, so the translated menus end up in that order and
not in the order of the source menu. On the User Guide, *Contributors* was often the
first item of a translated main menu instead of the last.

Some groups of the User Guide are meant to be alphabetical, and alphabetical in
German is not the order of the English titles. For those the source order is the wrong
target too: they have to be sorted by the translated titles.

## This fix

A task that runs once: for every translated main menu, the order of the source menu,
except for three alphabetical groups (see the README), and the list of field types in
the Fields articles in the same order. The new `lft` and `rgt` of the whole tree are
worked out in memory and written in one transaction, which is far quicker than moving the
items one by one through `Table\Nested::moveByReference()` (each move renumbers half the
table) and leaves no half-reordered menu behind.

## Later: in Translator Feedback

What Translator Feedback can take from this:

- **Place a new translation where its source is.** Instead of `last-child`: among the
  translated siblings, after the translation of the source's nearest preceding sibling
  that has one (`setLocation($thatSibling, 'after')`), or as `first-child` when there is
  none. Then the order is right from the start, whatever order things are translated in.
  `MenuOrderPlanner::followSource()` is the same rule for a whole group at once.
- **Alphabetical groups need a setting, not a list in code.** `AlphabeticalRule` is
  that setting: a parent item, an optional sticky child, and which children are sorted.
  In Translator Feedback it could be a menu item parameter on the source item (*"order
  the translations of my children alphabetically"*), read when a translation is placed:
  then the new item goes after the last translated sibling whose title sorts before
  its own (`TitleCollator`).
- **Lists in content that follow a menu.** `ListReorderer` reorders the `<li>`s of a list
  by the menu items they link to, without touching anything else. It needs the links to
  point at the translated menu items first (the translated links plugin).
- A repair task like this one stays useful for menus that were reordered by hand in the
  source after they were translated.
