import sys

# Test nested sidebar data structure
group_master = {
    'label': 'Data Master',
    'icon': 'layers',
    'order': 40,
    'items': {
        'pcs': {'label': 'Pendataan PC', 'icon': 'pc', 'order': 10, 'reg': 'pcs'},
    },
    'subgroups': {
        'subgroup_asset': {
            'label': 'Master Aset',
            'icon': 'box',
            'order': 20,
            'items': {
                'asset_master_items': {'label': 'Master Barang', 'icon': 'layers', 'order': 10, 'reg': 'asset_master_items'},
                'asset_groups': {'label': 'Master Komoditas', 'icon': 'layers', 'order': 20, 'reg': 'asset_groups'},
                'asset_types': {'label': 'Master Kategori', 'icon': 'layers', 'order': 30, 'reg': 'asset_types'},
                'asset_brands': {'label': 'Master Brand / Merk', 'icon': 'box', 'order' : 40, 'reg': 'asset_brands'},
                'asset_identifiers': {'label': 'Identifier Aset', 'icon': 'qr', 'order': 50, 'reg': 'asset_identifiers'},
                'asset_specifications': {'label': 'Spesifikasi Aset', 'icon': 'tool', 'order': 60, 'reg': 'asset_specifications'},
            }
        },
        'subgroup_lokasi': {
            'label': 'Lokasi & Pengguna',
            'icon': 'patrol',
            'order': 30,
            'items': {
                'asset_companies': {'label': 'Company', 'icon': 'shield', 'order': 10, 'reg': 'asset_companies'},
                'asset_locations': {'label': 'Master Lokasi', 'icon': 'patrol', 'order': 20, 'reg': 'asset_locations'},
                'master_pengguna': {'label': 'Master Pengguna', 'icon': 'users', 'order': 30, 'reg': 'master_pengguna'},
            }
        },
        'subgroup_maint': {
            'label': 'Pemeliharaan',
            'icon': 'tool',
            'order': 40,
            'items': {
                'jobs': {'label': 'Master Jobdesk PM', 'icon': 'layers', 'order': 10, 'reg': 'jobs'},
                'corrective_job_desks': {'label': 'Master Jobdesk Corrective', 'icon': 'layers', 'order': 20, 'reg': 'corrective_job_desks'},
                'corrective_workshops': {'label': 'Master Bengkel', 'icon': 'cog', 'order': 30, 'reg': 'corrective_workshops'},
            }
        }
    }
}

print("Data Master structure parsed successfully")

