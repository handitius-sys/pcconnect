# Helper to update bootstrap.php and regulations.php with nested subgroups

# Let's read bootstrap.php
with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    b_content = f.read()

# 1. Update get_default_sidebar_structure in bootstrap.php
old_structure_target = """function get_default_sidebar_structure(): array
{
    return [
        'group_transaksi' => [
            'label' => 'Transaksi Aset',
            'icon' => 'box',
            'order' => 10,
            'items' => [
                'asset_items' => ['label' => 'Unit Aset', 'icon' => 'box', 'order' => 10, 'reg' => 'asset_items'],
                'asset_loans' => ['label' => 'Peminjaman Aset', 'icon' => 'loan', 'order' => 20, 'reg' => 'asset_loans'],
                'asset_mutations' => ['label' => 'Mutasi Aset', 'icon' => 'move', 'order' => 30, 'reg' => 'asset_mutations'],
            ],
        ],
        'group_maintenance' => [
            'label' => 'Maintenance Aset',
            'icon' => 'tool',
            'order' => 20,
            'items' => [
                'maintenance' => ['label' => 'Preventive Maintenance', 'icon' => 'tool', 'order' => 10, 'reg' => 'maintenance'],
                'tickets' => ['label' => 'Aset Corrective', 'icon' => 'ticket', 'order' => 20, 'reg' => 'tickets'],
            ],
        ],
        'group_reports' => [
            'label' => 'Reports',
            'icon' => 'chart',
            'order' => 30,
            'items' => [
                'reports' => ['label' => 'Report Preventive Maintenance', 'icon' => 'chart', 'order' => 10, 'reg' => 'reports'],
                'corrective_repairs' => ['label' => 'Report Corrective Maintenance', 'icon' => 'chart', 'order' => 20, 'reg' => 'corrective_repairs'],
                'asset_movements' => ['label' => 'Report Mutasi Aset', 'icon' => 'chart', 'order' => 30, 'reg' => 'asset_movements'],
                'report_asset_loans' => ['label' => 'Report Peminjaman Aset', 'icon' => 'chart', 'order' => 40, 'reg' => 'report_asset_loans'],
                'report_asset_items' => ['label' => 'Report Unit Aset', 'icon' => 'chart', 'order' => 50, 'reg' => 'report_asset_items'],
            ],
        ],
        'group_master_pc' => [
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
        ],
        'group_setup' => [
            'label' => 'Pengaturan & Setup',
            'icon' => 'cog',
            'order' => 50,
            'items' => [
                'labels' => ['label' => 'QR Label Unit', 'icon' => 'qr', 'order' => 10, 'reg' => 'labels'],
                'users' => ['label' => 'Akses Users', 'icon' => 'users', 'order' => 20, 'reg' => 'users'],
                'setup_regulations' => ['label' => 'Regulasi Hak Akses', 'icon' => 'shield', 'order' => 30, 'reg' => 'admin_only'],
            ],
        ],
    ];
}"""

new_structure = """function get_default_sidebar_structure(): array
{
    return [
        'group_transaksi' => [
            'label' => 'Transaksi Aset',
            'icon' => 'box',
            'order' => 10,
            'items' => [
                'asset_items' => ['label' => 'Unit Aset', 'icon' => 'box', 'order' => 10, 'reg' => 'asset_items'],
                'asset_loans' => ['label' => 'Peminjaman Aset', 'icon' => 'loan', 'order' => 20, 'reg' => 'asset_loans'],
                'asset_mutations' => ['label' => 'Mutasi Aset', 'icon' => 'move', 'order' => 30, 'reg' => 'asset_mutations'],
            ],
        ],
        'group_maintenance' => [
            'label' => 'Maintenance Aset',
            'icon' => 'tool',
            'order' => 20,
            'items' => [
                'maintenance' => ['label' => 'Preventive Maintenance', 'icon' => 'tool', 'order' => 10, 'reg' => 'maintenance'],
                'tickets' => ['label' => 'Aset Corrective', 'icon' => 'ticket', 'order' => 20, 'reg' => 'tickets'],
            ],
        ],
        'group_reports' => [
            'label' => 'Reports',
            'icon' => 'chart',
            'order' => 30,
            'items' => [
                'reports' => ['label' => 'Report Preventive Maintenance', 'icon' => 'chart', 'order' => 10, 'reg' => 'reports'],
                'corrective_repairs' => ['label' => 'Report Corrective Maintenance', 'icon' => 'chart', 'order' => 20, 'reg' => 'corrective_repairs'],
                'asset_movements' => ['label' => 'Report Mutasi Aset', 'icon' => 'chart', 'order' => 30, 'reg' => 'asset_movements'],
                'report_asset_loans' => ['label' => 'Report Peminjaman Aset', 'icon' => 'chart', 'order' => 40, 'reg' => 'report_asset_loans'],
                'report_asset_items' => ['label' => 'Report Unit Aset', 'icon' => 'chart', 'order' => 50, 'reg' => 'report_asset_items'],
            ],
        ],
        'group_master' => [
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
        ],
        'group_setup' => [
            'label' => 'Pengaturan & Setup',
            'icon' => 'cog',
            'order' => 50,
            'items' => [
                'labels' => ['label' => 'QR Label Unit', 'icon' => 'qr', 'order' => 10, 'reg' => 'labels'],
                'users' => ['label' => 'Akses Users', 'icon' => 'users', 'order' => 20, 'reg' => 'users'],
                'setup_regulations' => ['label' => 'Regulasi Hak Akses', 'icon' => 'shield', 'order' => 30, 'reg' => 'admin_only'],
            ],
        ],
    ];
}"""

if old_structure_target in b_content:
    print("Found old structure in bootstrap.php")
else:
    print("WARNING: Old structure not matching exactly in bootstrap.php")

