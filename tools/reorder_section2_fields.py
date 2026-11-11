with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

old_sec2 = """            <h2>2. Rincian Masalah & Foto Bukti</h2>
            <?php
            // Master Lokasi dari database
            $locationsList = $pdo->query("SELECT id, location_code, location_name FROM asset_locations WHERE is_active=1 ORDER BY location_name")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div id="locationFieldWrapper" style="margin-bottom:16px;">
                <label>Lokasi / Gedung / Ruangan (Khusus Penanganan Internal)
                    <select name="location_label" id="locInput">
                        <option value="">-- Pilih Lokasi dari Database --</option>
                        <?php foreach ($locationsList as $loc): ?>
                            <option value="<?= e($loc['location_name']) ?>"><?= e($loc['location_code'] . ' - ' . $loc['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="grid three">
                <label>Kategori Masalah
                    <select name="issue_category" id="ticketIssueCat">
                        <option value="hardware">Kerusakan Hardware / Komponen</option>
                        <option value="software">Masalah Software / OS / Aplikasi</option>
                        <option value="network">Jaringan / Koneksi Internet</option>
                        <option value="printer">Printer / Scanner Error</option>
                        <option value="engine">Mesin / Ban / Rem (Kendaraan)</option>
                        <option value="electrical">Kelistrikan / Aki / Lampu</option>
                        <option value="hvac">AC / Pendingin Ruangan</option>
                        <option value="plumbing">Pipa / Sanitasi Air</option>
                        <option value="general">Lain-lain / Kerusakan Fisik</option>
                    </select>
                </label>
                <label>Tingkat Prioritas *
                    <select name="priority" required>
                        <option value="low">Low (Bisa ditunda)</option>
                        <option value="medium" selected>Medium (Standard)</option>
                        <option value="high">High (Mendesak / Mengganggu Operasional)</option>
                        <option value="critical">CRITICAL (Operasional Terhenti Total)</option>
                    </select>
                </label>
                <label>Foto Kerusakan / Bukti Error
                    <input type="file" name="photo_before" accept="image/*" capture="environment">
                </label>
            </div>

            <label>Judul / Subjek Kendala *
                <input name="subject" required placeholder="Contoh: Layar bluescreen tidak bisa masuk Windows / Ban mobil bocor / AC tidak dingin">
            </label>

            <label>Deskripsi Lengkap Gejala Kerusakan
                <textarea name="description" placeholder="Jelaskan kronologi, pesan error, atau kondisi fisik saat terjadi masalah..."></textarea>
            </label>"""

new_sec2 = """            <h2>2. Rincian Masalah & Foto Bukti</h2>
            <label>Judul / Subjek Kendala *
                <input name="subject" required placeholder="Contoh: Layar bluescreen tidak bisa masuk Windows / Ban mobil bocor / AC tidak dingin">
            </label>

            <label>Deskripsi Lengkap Gejala Kerusakan
                <textarea name="description" placeholder="Jelaskan kronologi, pesan error, atau kondisi fisik saat terjadi masalah..."></textarea>
            </label>

            <div class="grid three">
                <label>Kategori Masalah
                    <select name="issue_category" id="ticketIssueCat">
                        <option value="hardware">Kerusakan Hardware / Komponen</option>
                        <option value="software">Masalah Software / OS / Aplikasi</option>
                        <option value="network">Jaringan / Koneksi Internet</option>
                        <option value="printer">Printer / Scanner Error</option>
                        <option value="engine">Mesin / Ban / Rem (Kendaraan)</option>
                        <option value="electrical">Kelistrikan / Aki / Lampu</option>
                        <option value="hvac">AC / Pendingin Ruangan</option>
                        <option value="plumbing">Pipa / Sanitasi Air</option>
                        <option value="general">Lain-lain / Kerusakan Fisik</option>
                    </select>
                </label>
                <label>Tingkat Prioritas *
                    <select name="priority" required>
                        <option value="low">Low (Bisa ditunda)</option>
                        <option value="medium" selected>Medium (Standard)</option>
                        <option value="high">High (Mendesak / Mengganggu Operasional)</option>
                        <option value="critical">CRITICAL (Operasional Terhenti Total)</option>
                    </select>
                </label>
                <label>Foto Kerusakan / Bukti Error
                    <input type="file" name="photo_before" accept="image/*" capture="environment">
                </label>
            </div>

            <?php
            // Master Lokasi dari database
            $locationsList = $pdo->query("SELECT id, location_code, location_name FROM asset_locations WHERE is_active=1 ORDER BY location_name")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <div id="locationFieldWrapper" style="margin-bottom:16px;">
                <label>Lokasi / Gedung / Ruangan (Khusus Penanganan Internal)
                    <select name="location_label" id="locInput">
                        <option value="">-- Pilih Lokasi dari Database --</option>
                        <?php foreach ($locationsList as $loc): ?>
                            <option value="<?= e($loc['location_name']) ?>"><?= e($loc['location_code'] . ' - ' . $loc['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>"""

if old_sec2 in text:
    text = text.replace(old_sec2, new_sec2)
    with open('app/modules/corrective.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Reordered Section 2 fields successfully!")
else:
    print("Warning: old_sec2 pattern not found!")
