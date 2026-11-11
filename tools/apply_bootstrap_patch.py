# Update bootstrap.php

with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Structure definition
old_structure = """        'group_master_pc' => [
            'label' => 'Data Master',
            'icon' => 'layers',
            'order' => 40,
            'items' => [
                'pcs' => ['label' => 'Pendataan PC', 'icon' => 'pc', 'order' => 10, 'reg' => 'pcs'],
            ],
        ],
        'group_master_asset' => [
            'label' => 'Master Aset',
            'icon' => 'box',
            'order' => 41,
            'items' => [
                'asset_master_items' => ['label' => 'Master Barang', 'icon' => 'layers', 'order' => 10, 'reg' => 'asset_master_items'],
                'asset_groups' => ['label' => 'Master Komoditas', 'icon' => 'layers', 'order' => 20, 'reg' => 'asset_groups'],
                'asset_types' => ['label' => 'Master Kategori', 'icon' => 'layers', 'order' => 30, 'reg' => 'asset_types'],
                'asset_brands' => ['label' => 'Master Brand / Merk', 'icon' => 'box', 'order' => 40, 'reg' => 'asset_brands'],
                'asset_identifiers' => ['label' => 'Identifier Aset', 'icon' => 'qr', 'order' => 50, 'reg' => 'asset_identifiers'],
                'asset_specifications' => ['label' => 'Spesifikasi Aset', 'icon' => 'tool', 'order' => 60, 'reg' => 'asset_specifications'],
            ],
        ],
        'group_master_lokasi' => [
            'label' => 'Lokasi & Pengguna',
            'icon' => 'patrol',
            'order' => 42,
            'items' => [
                'asset_companies' => ['label' => 'Company', 'icon' => 'shield', 'order' => 10, 'reg' => 'asset_companies'],
                'asset_locations' => ['label' => 'Master Lokasi', 'icon' => 'patrol', 'order' => 20, 'reg' => 'asset_locations'],
                'master_pengguna' => ['label' => 'Master Pengguna', 'icon' => 'users', 'order' => 30, 'reg' => 'master_pengguna'],
            ],
        ],
        'group_master_maint' => [
            'label' => 'Pemeliharaan',
            'icon' => 'tool',
            'order' => 43,
            'items' => [
                'jobs' => ['label' => 'Master Jobdesk PM', 'icon' => 'layers', 'order' => 10, 'reg' => 'jobs'],
                'corrective_job_desks' => ['label' => 'Master Jobdesk Corrective', 'icon' => 'layers', 'order' => 20, 'reg' => 'corrective_job_desks'],
                'corrective_workshops' => ['label' => 'Master Bengkel', 'icon' => 'cog', 'order' => 30, 'reg' => 'corrective_workshops'],
            ],
        ],"""

new_structure = """        'group_master' => [
            'label' => 'Data Master',
            'icon' => 'layers',
            'order' => 40,
            'items' => [
                'pcs' => ['label' => 'Pendataan PC', 'icon' => 'pc', 'order' => 10, 'reg' => 'pcs'],
            ],
            'subgroups' => [
                'subgroup_asset' => [
                    'label' => 'Master Aset',
                    'icon' => 'box',
                    'order' => 20,
                    'items' => [
                        'asset_master_items' => ['label' => 'Master Barang', 'icon' => 'layers', 'order' => 10, 'reg' => 'asset_master_items'],
                        'asset_groups' => ['label' => 'Master Komoditas', 'icon' => 'layers', 'order' => 20, 'reg' => 'asset_groups'],
                        'asset_types' => ['label' => 'Master Kategori', 'icon' => 'layers', 'order' => 30, 'reg' => 'asset_types'],
                        'asset_brands' => ['label' => 'Master Brand / Merk', 'icon' => 'box', 'order' => 40, 'reg' => 'asset_brands'],
                        'asset_identifiers' => ['label' => 'Identifier Aset', 'icon' => 'qr', 'order' => 50, 'reg' => 'asset_identifiers'],
                        'asset_specifications' => ['label' => 'Spesifikasi Aset', 'icon' => 'tool', 'order' => 60, 'reg' => 'asset_specifications'],
                    ],
                ],
                'subgroup_lokasi' => [
                    'label' => 'Lokasi & Pengguna',
                    'icon' => 'patrol',
                    'order' => 30,
                    'items' => [
                        'asset_companies' => ['label' => 'Company', 'icon' => 'shield', 'order' => 10, 'reg' => 'asset_companies'],
                        'asset_locations' => ['label' => 'Master Lokasi', 'icon' => 'patrol', 'order' => 20, 'reg' => 'asset_locations'],
                        'master_pengguna' => ['label' => 'Master Pengguna', 'icon' => 'users', 'order' => 30, 'reg' => 'master_pengguna'],
                    ],
                ],
                'subgroup_maint' => [
                    'label' => 'Pemeliharaan',
                    'icon' => 'tool',
                    'order' => 40,
                    'items' => [
                        'jobs' => ['label' => 'Master Jobdesk PM', 'icon' => 'layers', 'order' => 10, 'reg' => 'jobs'],
                        'corrective_job_desks' => ['label' => 'Master Jobdesk Corrective', 'icon' => 'layers', 'order' => 20, 'reg' => 'corrective_job_desks'],
                        'corrective_workshops' => ['label' => 'Master Bengkel', 'icon' => 'cog', 'order' => 30, 'reg' => 'corrective_workshops'],
                    ],
                ],
            ],
        ],"""

assert old_structure in code, "Structure pattern not found"
code = code.replace(old_structure, new_structure, 1)

# 2. Update get_sidebar_menu_orders and save_sidebar_menu_orders
old_orders_code = """function get_sidebar_menu_orders(?PDO $pdo = null): array
{
    $structure = get_default_sidebar_structure();
    if (!$pdo) {
        try {
            $pdo = Database::pdo();
        } catch (Throwable $e) {
            return $structure;
        }
    }
    ensure_menu_orders_schema($pdo);

    try {
        $stmt = $pdo->query("SELECT item_key, parent_group, display_order FROM system_menu_orders");
        $savedOrders = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $savedOrders[$r['item_key']] = (int)$r['display_order'];
        }

        foreach ($structure as $gKey => &$gVal) {
            if (isset($savedOrders[$gKey])) {
                $gVal['order'] = $savedOrders[$gKey];
            }
            foreach ($gVal['items'] as $iKey => &$iVal) {
                if (isset($savedOrders[$iKey])) {
                    $iVal['order'] = $savedOrders[$iKey];
                }
            }
            unset($iVal);
            // Sort items inside group
            uasort($gVal['items'], function($a, $b) {
                return ($a['order'] <=> $b['order']);
            });
        }
        unset($gVal);

        // Sort groups
        uasort($structure, function($a, $b) {
            return ($a['order'] <=> $b['order']);
        });
    } catch (Throwable $e) {}

    return $structure;
}

function save_sidebar_menu_orders(PDO $pdo, array $orders): void
{
    ensure_menu_orders_schema($pdo);
    $stmt = $pdo->prepare("INSERT INTO system_menu_orders (item_key, parent_group, display_order, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE display_order = VALUES(display_order), updated_at = NOW()");
    $structure = get_default_sidebar_structure();
    
    foreach ($orders as $key => $ordVal) {
        $cleanKey = trim((string)$key);
        $orderInt = max(1, min(999, (int)$ordVal));
        if ($cleanKey === '') continue;

        $pGroup = '';
        if (str_starts_with($cleanKey, 'group_')) {
            $pGroup = 'root';
        } else {
            foreach ($structure as $gK => $gV) {
                if (isset($gV['items'][$cleanKey])) {
                    $pGroup = $gK;
                    break;
                }
            }
        }
        $stmt->execute([$cleanKey, $pGroup, $orderInt]);
    }
}"""

new_orders_code = """function get_sidebar_menu_orders(?PDO $pdo = null): array
{
    $structure = get_default_sidebar_structure();
    if (!$pdo) {
        try {
            $pdo = Database::pdo();
        } catch (Throwable $e) {
            return $structure;
        }
    }
    ensure_menu_orders_schema($pdo);

    try {
        $stmt = $pdo->query("SELECT item_key, parent_group, display_order FROM system_menu_orders");
        $savedOrders = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $savedOrders[$r['item_key']] = (int)$r['display_order'];
        }

        foreach ($structure as $gKey => &$gVal) {
            if (isset($savedOrders[$gKey])) {
                $gVal['order'] = $savedOrders[$gKey];
            }
            if (!empty($gVal['items']) && is_array($gVal['items'])) {
                foreach ($gVal['items'] as $iKey => &$iVal) {
                    if (isset($savedOrders[$iKey])) {
                        $iVal['order'] = $savedOrders[$iKey];
                    }
                }
                unset($iVal);
                uasort($gVal['items'], function($a, $b) {
                    return ($a['order'] <=> $b['order']);
                });
            }
            if (!empty($gVal['subgroups']) && is_array($gVal['subgroups'])) {
                foreach ($gVal['subgroups'] as $sgKey => &$sgVal) {
                    if (isset($savedOrders[$sgKey])) {
                        $sgVal['order'] = $savedOrders[$sgKey];
                    }
                    if (!empty($sgVal['items']) && is_array($sgVal['items'])) {
                        foreach ($sgVal['items'] as $sgiKey => &$sgiVal) {
                            if (isset($savedOrders[$sgiKey])) {
                                $sgiVal['order'] = $savedOrders[$sgiKey];
                            }
                        }
                        unset($sgiVal);
                        uasort($sgVal['items'], function($a, $b) {
                            return ($a['order'] <=> $b['order']);
                        });
                    }
                }
                unset($sgVal);
                uasort($gVal['subgroups'], function($a, $b) {
                    return ($a['order'] <=> $b['order']);
                });
            }
        }
        unset($gVal);

        // Sort groups
        uasort($structure, function($a, $b) {
            return ($a['order'] <=> $b['order']);
        });
    } catch (Throwable $e) {}

    return $structure;
}

function save_sidebar_menu_orders(PDO $pdo, array $orders): void
{
    ensure_menu_orders_schema($pdo);
    $stmt = $pdo->prepare("INSERT INTO system_menu_orders (item_key, parent_group, display_order, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE display_order = VALUES(display_order), updated_at = NOW()");
    $structure = get_default_sidebar_structure();
    
    foreach ($orders as $key => $ordVal) {
        $cleanKey = trim((string)$key);
        $orderInt = max(1, min(999, (int)$ordVal));
        if ($cleanKey === '') continue;

        $pGroup = '';
        if (str_starts_with($cleanKey, 'group_')) {
            $pGroup = 'root';
        } elseif (str_starts_with($cleanKey, 'subgroup_')) {
            foreach ($structure as $gK => $gV) {
                if (isset($gV['subgroups'][$cleanKey])) {
                    $pGroup = $gK;
                    break;
                }
            }
            if ($pGroup === '') $pGroup = 'group_master';
        } else {
            foreach ($structure as $gK => $gV) {
                if (isset($gV['items'][$cleanKey])) {
                    $pGroup = $gK;
                    break;
                }
                if (!empty($gV['subgroups'])) {
                    foreach ($gV['subgroups'] as $sgK => $sgV) {
                        if (isset($sgV['items'][$cleanKey])) {
                            $pGroup = $sgK;
                            break 2;
                        }
                    }
                }
            }
        }
        $stmt->execute([$cleanKey, $pGroup, $orderInt]);
    }
}"""

assert old_orders_code in code, "Old orders code pattern not found"
code = code.replace(old_orders_code, new_orders_code, 1)

# 3. Update sidebar rendering in render_header
old_render_block = """        $menuStructure = get_sidebar_menu_orders($pdo ?? null);
        foreach ($menuStructure as $gKey => $group) {
            $visibleItems = [];
            $isGroupOpen = false;
            foreach ($group['items'] as $itemKey => $item) {
                $canAccess = false;
                if ($isAdmin) {
                    $canAccess = true;
                } elseif (($item['reg'] ?? '') === 'admin_only') {
                    $canAccess = false;
                } elseif (!empty($item['reg'])) {
                    $canAccess = has_regulation($item['reg'], 'view', $user);
                } else {
                    $canAccess = true;
                }

                if ($canAccess) {
                    $visibleItems[$itemKey] = $item;
                    if ($curRoute === $itemKey || ($itemKey === 'asset_movements' && $curRoute === 'report_asset_mutations')) {
                        $isGroupOpen = true;
                    }
                }
            }

            if (empty($visibleItems)) {
                continue;
            }

            $gIcon = $svgIcons[$group['icon'] ?? 'layers'] ?? $svgLayers;
            echo '<details class="nav-accordion"' . ($isGroupOpen ? ' open' : '') . '>';
            echo '  <summary class="nav-item" title="' . e($group['label']) . '"><span class="nav-icon">' . $gIcon . '</span><span class="nav-label">' . e($group['label']) . '</span><span class="nav-chevron">&#x25b6;</span></summary>';
            echo '  <div class="nav-submenu">';
            foreach ($visibleItems as $itemKey => $item) {
                $isActive = ($curRoute === $itemKey || ($itemKey === 'asset_movements' && $curRoute === 'report_asset_mutations'));
                $itemIcon = $svgIcons[$item['icon'] ?? 'box'] ?? $svgBox;
                echo '<a class="nav-item' . ($isActive ? ' active' : '') . '" href="' . route_url($itemKey) . '"><span class="nav-icon">' . $itemIcon . '</span><span class="nav-label">' . e($item['label']) . '</span></a>';
            }
            echo '  </div>';
            echo '</details>';
        }"""

# Note in old_render_block, check if &#x25b6; is Unicode \u25b6 in file
idx_menu_start = code.find('$menuStructure = get_sidebar_menu_orders($pdo ?? null);')
idx_menu_end = code.find("echo '</nav>'; // End sidebar-nav")

render_chunk = code[idx_menu_start:idx_menu_end]

new_render_code = """$menuStructure = get_sidebar_menu_orders($pdo ?? null);
        foreach ($menuStructure as $gKey => $group) {
            $visibleItems = [];
            $visibleSubgroups = [];
            $isGroupOpen = false;

            // Direct items in group
            if (!empty($group['items']) && is_array($group['items'])) {
                foreach ($group['items'] as $itemKey => $item) {
                    $canAccess = false;
                    if ($isAdmin) {
                        $canAccess = true;
                    } elseif (($item['reg'] ?? '') === 'admin_only') {
                        $canAccess = false;
                    } elseif (!empty($item['reg'])) {
                        $canAccess = has_regulation($item['reg'], 'view', $user);
                    } else {
                        $canAccess = true;
                    }

                    if ($canAccess) {
                        $visibleItems[$itemKey] = $item;
                        if ($curRoute === $itemKey || ($itemKey === 'asset_movements' && $curRoute === 'report_asset_mutations')) {
                            $isGroupOpen = true;
                        }
                    }
                }
            }

            // Subgroups in group (e.g. Master Aset, Lokasi & Pengguna, Pemeliharaan)
            if (!empty($group['subgroups']) && is_array($group['subgroups'])) {
                foreach ($group['subgroups'] as $sgKey => $subgroup) {
                    $sgVisibleItems = [];
                    $isSgOpen = false;
                    if (!empty($subgroup['items']) && is_array($subgroup['items'])) {
                        foreach ($subgroup['items'] as $sgiKey => $sgiVal) {
                            $canAccess = false;
                            if ($isAdmin) {
                                $canAccess = true;
                            } elseif (($sgiVal['reg'] ?? '') === 'admin_only') {
                                $canAccess = false;
                            } elseif (!empty($sgiVal['reg'])) {
                                $canAccess = has_regulation($sgiVal['reg'], 'view', $user);
                            } else {
                                $canAccess = true;
                            }

                            if ($canAccess) {
                                $sgVisibleItems[$sgiKey] = $sgiVal;
                                if ($curRoute === $sgiKey || ($sgiKey === 'asset_movements' && $curRoute === 'report_asset_mutations')) {
                                    $isSgOpen = true;
                                    $isGroupOpen = true;
                                }
                            }
                        }
                    }
                    if (!empty($sgVisibleItems)) {
                        $visibleSubgroups[$sgKey] = [
                            'label' => $subgroup['label'],
                            'icon' => $subgroup['icon'] ?? 'box',
                            'open' => $isSgOpen,
                            'items' => $sgVisibleItems,
                        ];
                    }
                }
            }

            if (empty($visibleItems) && empty($visibleSubgroups)) {
                continue;
            }

            $gIcon = $svgIcons[$group['icon'] ?? 'layers'] ?? $svgLayers;
            echo '<details class="nav-accordion"' . ($isGroupOpen ? ' open' : '') . '>';
            echo '  <summary class="nav-item" title="' . e($group['label']) . '"><span class="nav-icon">' . $gIcon . '</span><span class="nav-label">' . e($group['label']) . '</span><span class="nav-chevron">&#x25b6;</span></summary>';
            echo '  <div class="nav-submenu">';

            // Render direct items (e.g. Pendataan PC)
            foreach ($visibleItems as $itemKey => $item) {
                $isActive = ($curRoute === $itemKey || ($itemKey === 'asset_movements' && $curRoute === 'report_asset_mutations'));
                $itemIcon = $svgIcons[$item['icon'] ?? 'box'] ?? $svgBox;
                echo '<a class="nav-item' . ($isActive ? ' active' : '') . '" href="' . route_url($itemKey) . '"><span class="nav-icon">' . $itemIcon . '</span><span class="nav-label">' . e($item['label']) . '</span></a>';
            }

            // Render subgroups (Master Aset, Lokasi & Pengguna, Pemeliharaan)
            foreach ($visibleSubgroups as $sgKey => $sg) {
                $sgIcon = $svgIcons[$sg['icon'] ?? 'box'] ?? $svgBox;
                echo '<details class="nav-subaccordion"' . ($sg['open'] ? ' open' : '') . '>';
                echo '  <summary title="' . e($sg['label']) . '"><span style="display:flex;align-items:center;gap:10px;"><span class="nav-icon">' . $sgIcon . '</span><span class="nav-label">' . e($sg['label']) . '</span></span><span class="nav-chevron">&#x25b6;</span></summary>';
                echo '  <div style="padding:2px 0 2px 8px;display:flex;flex-direction:column;gap:1px;">';
                foreach ($sg['items'] as $sgiKey => $sgi) {
                    $isActive = ($curRoute === $sgiKey || ($sgiKey === 'asset_movements' && $curRoute === 'report_asset_mutations'));
                    $sgiIcon = $svgIcons[$sgi['icon'] ?? 'box'] ?? $svgBox;
                    echo '<a class="nav-item' . ($isActive ? ' active' : '') . '" href="' . route_url($sgiKey) . '"><span class="nav-icon">' . $sgiIcon . '</span><span class="nav-label">' . e($sgi['label']) . '</span></a>';
                }
                echo '  </div>';
                echo '</details>';
            }

            echo '  </div>';
            echo '</details>';
        }

        """

code = code[:idx_menu_start] + new_render_code + code[idx_menu_end:]

with open('app/lib/bootstrap.php', 'w', encoding='utf-8') as f:
    f.write(code)

print("Updated bootstrap.php successfully")

