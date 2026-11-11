with open('app/modules/asset_loan.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Extract blocks
prefix = lines[:694]
part1 = lines[694:729] # Identitas Peminjam
part2 = lines[729:749] # Jadwal & Keperluan
part3 = lines[749:816] # Scanner & Unit Aset
suffix = lines[816:]

# Update numbering in part3:
# Change // Bagian 3: to // Bagian 1:
# Change 3. Scan QR Code atau Masukkan Kode Unit / Cari Pengguna to 1. Scan QR Code atau Masukkan Kode Unit / Cari Pengguna
part3_new = []
for l in part3:
    l_mod = l.replace('// Bagian 3: Scanner Kode Unit / QR Code', '// Bagian 1: Scanner Kode Unit / QR Code')
    l_mod = l_mod.replace('3. Scan QR Code atau Masukkan Kode Unit / Cari Pengguna', '1. Scan QR Code atau Masukkan Kode Unit / Cari Pengguna')
    part3_new.append(l_mod)

# Update numbering in part1:
# Change // Bagian 1: to // Bagian 2:
# Change 1. Identitas Peminjam (Dari Master Pengguna) to 2. Identitas Peminjam (Dari Master Pengguna)
part1_new = []
for l in part1:
    l_mod = l.replace('// Bagian 1: Identitas Peminjam', '// Bagian 2: Identitas Peminjam')
    l_mod = l_mod.replace('1. Identitas Peminjam (Dari Master Pengguna)', '2. Identitas Peminjam (Dari Master Pengguna)')
    part1_new.append(l_mod)

# Update numbering in part2:
# Change // Bagian 2: to // Bagian 3:
# Change 2. Jadwal Peminjaman & Keperluan to 3. Jadwal Peminjaman & Keperluan
part2_new = []
for l in part2:
    l_mod = l.replace('// Bagian 2: Waktu & Keperluan', '// Bagian 3: Waktu & Keperluan')
    l_mod = l_mod.replace('2. Jadwal Peminjaman & Keperluan', '3. Jadwal Peminjaman & Keperluan')
    part2_new.append(l_mod)

new_lines = prefix + part3_new + part1_new + part2_new + suffix

with open('app/modules/asset_loan.php', 'w', encoding='utf-8') as f:
    f.writelines(new_lines)

print("SUCCESS: asset_loan.php reordered successfully!")
