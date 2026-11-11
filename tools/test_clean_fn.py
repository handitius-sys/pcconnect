with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect format_clean_ticket_subject
clean_fn = """function format_clean_ticket_subject(?string $subject, ?string $fallbackAction = null): string
{
    $s = trim((string)$subject);
    if ($s === '') {
        return $fallbackAction ?: '-';
    }

    // Jika subject format lama/otomatis: "Perbaikan [Job Desk ...] KODE - NAMA"
    if (preg_match('/^Perbaikan\s*\[([^\]]+)\]\s*(.*)$/i', $s, $m)) {
        if (!empty($fallbackAction) && $fallbackAction !== 'Perbaikan Tiket' && $fallbackAction !== 'Reparasi') {
            return $fallbackAction;
        }
        $jobDesk = trim($m[1]);
        $rest = trim($m[2]);
        // Hilangkan Kode Aset dan Spesifikasi jika ada di rest
        // Jika ada deskripsi setelah strip ketiga atau kata tertentu
        return $jobDesk ?: ($fallbackAction ?: 'Perbaikan Unit');
    }

    // Jika format "Perbaikan KODE - NAMA" tanpa keluhan nyata
    if (preg_match('/^Perbaikan\s+([A-Z0-9_-]+)\s*-\s*(.*)$/i', $s, $m)) {
        if (!empty($fallbackAction) && $fallbackAction !== 'Perbaikan Tiket' && $fallbackAction !== 'Reparasi') {
            return $fallbackAction;
        }
        return $fallbackAction ?: 'Perbaikan Unit';
    }

    return $s;
}
"""
print("Helper ready")
