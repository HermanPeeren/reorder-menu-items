<?php

/**
 * Check the menus of a site: is the nested set whole, and are the translated main
 * menus in the order the task gives them?
 *
 *   php tools/verify-order.php            # the site in ./joomla
 *   php tools/verify-order.php ../site    # another Joomla, by its configuration.php
 *
 * Read only. It shares no code with the plugin: it reads the tables with PDO and
 * checks the outcome its own way, so a mistake in the plugin is not repeated here.
 * Exit code 0 when everything holds, 1 when something does not; each problem is
 * printed.
 */

declare(strict_types=1);

$site = $argv[1] ?? \dirname(__DIR__) . '/joomla';

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No configuration.php in {$site}.\n");
    exit(1);
}

\define('_JEXEC', 1);
require $site . '/configuration.php';

$config = array_map('strval', get_object_vars(new JConfig()));

[$host, $port] = array_pad(explode(':', $config['host'], 2), 2, '3306');
$pdo           = new PDO(
    \sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $config['db']),
    $config['user'],
    $config['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$prefix = $config['dbprefix'];

/**
 * @return list<array<string, mixed>>
 */
$select = static function (string $sql) use ($pdo): array {
    $statement = $pdo->query($sql);

    if ($statement === false) {
        throw new RuntimeException('Query failed: ' . $sql);
    }

    /** @var list<array<string, mixed>> */
    return $statement->fetchAll();
};

$problems = [];
$fail     = static function (string $message) use (&$problems): void {
    $problems[] = $message;
};

// The tree.
$rows = [];

foreach ($select("SELECT id, parent_id, lft, rgt, level, menutype, title, path, link FROM {$prefix}menu") as $row) {
    $rows[(int) $row['id']] = $row;
}

$children = [];

foreach ($rows as $id => $row) {
    if ((int) $row['parent_id'] !== 0) {
        $children[(int) $row['parent_id']][] = $id;
    }
}

foreach ($children as &$ids) {
    usort($ids, static fn (int $a, int $b): int => (int) $rows[$a]['lft'] <=> (int) $rows[$b]['lft']);
}

unset($ids);

// 1. Nested set: walking the tree in lft order gives back exactly the stored numbers.
$counter = 0;
$walk    = static function (int $id) use (&$walk, &$counter, $children, $rows, $fail): void {
    if ((int) $rows[$id]['lft'] !== $counter) {
        $fail(\sprintf('menu item %d: lft is %d, expected %d', $id, $rows[$id]['lft'], $counter));
    }

    $counter++;

    foreach ($children[$id] ?? [] as $child) {
        if ((int) $rows[$child]['level'] !== (int) $rows[$id]['level'] + 1) {
            $fail(\sprintf('menu item %d: level %d under level %d', $child, $rows[$child]['level'], $rows[$id]['level']));
        }

        $walk($child);
    }

    if ((int) $rows[$id]['rgt'] !== $counter) {
        $fail(\sprintf('menu item %d: rgt is %d, expected %d', $id, $rows[$id]['rgt'], $counter));
    }

    $counter++;
};

$walk(1);

if ($counter !== 2 * \count($rows)) {
    $fail(\sprintf('%d menu items are not reachable from the root', \count($rows) - $counter / 2));
}

// 2. The order of the translated main menus.
$sourceOf = [];
$groups   = [];

foreach ($select("SELECT id, `key` FROM {$prefix}associations WHERE context = 'com_menus.item'") as $row) {
    $groups[$row['key']][] = (int) $row['id'];
}

foreach ($groups as $ids) {
    $sources = array_values(array_filter($ids, static fn (int $id): bool => isset($rows[$id]) && $rows[$id]['menutype'] === 'mainmenu'));

    if (\count($sources) === 1) {
        foreach ($ids as $id) {
            if ($id !== $sources[0]) {
                $sourceOf[$id] = $sources[0];
            }
        }
    }
}

// The alphabetical groups, by path in mainmenu, with the child that stays on top.
$sticky = [
    'user-guide'            => 'user-guide/getting-started',
    'help-pages-references' => 'help-pages-references/help-screens',
    'user-guide/fields'     => null,
];
$menus    = [];
$checked  = 0;
$language = [];

foreach ($rows as $id => $row) {
    if (preg_match('/^mainmenu(-[a-z]{2,3}-[a-z0-9]{2,4})?$/', $row['menutype'])) {
        $menus[$row['menutype']] = true;
    }
}

foreach ($select("SELECT menutype, MIN(language) AS language FROM {$prefix}menu WHERE menutype LIKE 'mainmenu%' GROUP BY menutype") as $row) {
    $language[$row['menutype']] = $row['language'];
}

foreach (array_keys($menus) as $menutype) {
    $isSource = $menutype === 'mainmenu';
    $collator = new Collator(str_replace('-', '_', $language[$menutype] ?? 'en-GB'));
    $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
    $source = static fn (int $id): ?array => $isSource ? $rows[$id] : (isset($sourceOf[$id]) ? $rows[$sourceOf[$id]] : null);

    foreach ($children as $parentId => $ids) {
        $group = array_values(array_filter($ids, static fn (int $id): bool => $rows[$id]['menutype'] === $menutype));

        if (\count($group) < 2) {
            continue;
        }

        $parentSource = $parentId === 1 ? null : $source($parentId);
        $rule         = $parentSource !== null && \array_key_exists($parentSource['path'], $sticky) ? $parentSource['path'] : null;
        $checked++;

        for ($i = 1; $i < \count($group); $i++) {
            $a  = $group[$i - 1];
            $b  = $group[$i];
            $sa = $source($a);
            $sb = $source($b);

            if ($sa === null || $sb === null) {
                continue;
            }

            if ($rule === null) {
                if (!$isSource && (int) $sa['lft'] > (int) $sb['lft']) {
                    $fail(\sprintf('%s: "%s" comes before "%s", unlike in mainmenu', $menutype, $rows[$a]['title'], $rows[$b]['title']));
                }

                continue;
            }

            if ($sb['path'] === $sticky[$rule]) {
                $fail(\sprintf('%s: "%s" is not on top', $menutype, $rows[$b]['title']));
            }

            $sorted = static fn (array $s): bool => $s['path'] !== $sticky[$rule]
                && ($rule !== 'user-guide/fields' || preg_match('/\sField$/', $s['title']));

            if ($sorted($sa) && $sorted($sb)) {
                $quotes = ['"', "'", '`', '«', '»', '‹', '›', '„', '‚', '“', '”', '‘', '’'];

                if ($collator->compare(str_replace($quotes, '', $rows[$a]['title']), str_replace($quotes, '', $rows[$b]['title'])) > 0) {
                    $fail(\sprintf('%s: "%s" comes before "%s", not alphabetical', $menutype, $rows[$a]['title'], $rows[$b]['title']));
                }
            } elseif (!$isSource && !$sorted($sa) && !$sorted($sb) && (int) $sa['lft'] > (int) $sb['lft']) {
                $fail(\sprintf('%s: "%s" comes before "%s", unlike in mainmenu', $menutype, $rows[$a]['title'], $rows[$b]['title']));
            }
        }

        // 3. The list of field types in the article the Fields item opens.
        if ($rule === 'user-guide/fields' && preg_match('/[?&]id=(\d+)/', $rows[$parentId]['link'], $match)) {
            $statement = $pdo->prepare("SELECT CONCAT(introtext, `fulltext`) AS text FROM {$prefix}content WHERE id = ?");
            $statement->execute([(int) $match[1]]);
            $text = (string) $statement->fetchColumn();
            $rank = [];

            foreach ($group as $index => $id) {
                $rank[$id] = $index;

                if (isset($sourceOf[$id])) {
                    $rank[$sourceOf[$id]] ??= $index;
                }
            }

            preg_match_all('#<ul\b[^>]*>(.*?)</ul>#is', $text, $lists);
            $found = false;

            foreach ($lists[1] as $list) {
                preg_match_all('#<li\b.*?Itemid=(\d+)#is', $list, $itemids);
                $ranks = array_map(static fn (string $itemid): ?int => $rank[(int) $itemid] ?? null, $itemids[1]);

                if (\count($ranks) < 2 || \in_array(null, $ranks, true)) {
                    continue;
                }

                $found = true;
                $order = $ranks;
                sort($order);

                if ($ranks !== $order) {
                    $fail(\sprintf('%s: the list in article %d is not in the order of the menu', $menutype, $match[1]));
                }
            }

            if (!$found) {
                $fail(\sprintf('%s: article %d has no list of the field types', $menutype, $match[1]));
            }
        }
    }
}

printf("%d menu items, %d main menus, %d groups of siblings checked.\n", \count($rows), \count($menus), $checked);

foreach ($problems as $problem) {
    echo '  ', $problem, "\n";
}

echo $problems === [] ? "All in order.\n" : \count($problems) . " problem(s).\n";

exit($problems === [] ? 0 : 1);
