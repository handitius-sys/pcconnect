# -*- coding: utf-8 -*-
with open('app/lib/bootstrap.php', 'r', encoding='utf-8') as f:
    text = f.read()

target = """        'group_master' => [
            'label' => 'Data Master',
            'icon' => 'layers',
            'order' => 40,
            'items' => [
                'pcs' => ['label' => 'Pendataan PC', 'icon' => 'pc', 'order' => 10, 'reg' => 'pcs'],
                'asset_groups' => ['label' => 'Master Komoditas', 'icon' => 'layers', 'order' => 20, 'reg' => 'asset_groups'],
                'corrective_workshops' => ['label' => 'Master Bengkel', 'icon' => 'cog', 'order' => 30, 'reg' => 'corrective_workshops'],
                'asset_types' => ['label' => 'Master Kategori', 'icon' => 'layers', 'order' => 40, 'reg' => 'asset_types'],
                'jobs' => ['label' => 'Master Jobdesk PM', 'icon' => 'layers', 'order' => 50, 'reg' => 'jobs'],
                'corrective_job_desks' => ['label' => 'Master Jobdesk Corrective', 'icon' => 'layers', 'order' => 60, 'reg' => 'corrective_job_desks'],
                'asset_brands' => ['label' => 'Master Brand / Merk', 'icon' => 'box', 'order' => 70, 'reg' => 'asset_brands'],
                'asset_master_items' => ['label' => 'Master Barang', 'icon' => 'box', 'order' => 80, 'reg' => 'asset_master_items'],
                'asset_identifiers' => ['label' => 'Identifier Aset', 'icon' => 'qr', 'order' => 90, 'reg' => 'asset_identifiers'],
                'asset_specifications' => ['label' => 'Spesifikasi Aset', 'icon' => 'tool', 'order' => 100, 'reg' => 'asset_specifications'],
                'asset_locations' => ['label' => 'Master Lokasi', 'icon' => 'patrol', 'order' => 110, 'reg' => 'asset_locations'],
                'asset_companies' => ['label' => 'Company', 'icon' => 'shield', 'order' => 120, 'reg' => 'asset_companies'],
                'master_pengguna' => ['label' => 'Master Pengguna', 'icon' => 'users', 'order' => 130, 'reg' => 'master_pengguna'],
            ],
        ],"""

replacement = """        'group_master_pc' => [
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

if target in text:
    text = text.replace(target, replacement, 1)
    with open('app/lib/bootstrap.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Updated get_default_sidebar_structure in bootstrap.php successfully!")
else:
    print("Target not found in bootstrap.php")

