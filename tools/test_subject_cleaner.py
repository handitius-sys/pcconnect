import re

def clean_ticket_subject(subject, requested_action=None):
    if not subject:
        return '-'
    s = str(subject).strip()
    
    # Pattern: Perbaikan [Job Desk ...] KODE - NAMA - KODE
    # If it starts with Perbaikan [
    if re.match(r'^Perbaikan\s*\[', s, re.IGNORECASE):
        # If there is a requested action title, use that as the real subject
        if requested_action and requested_action != 'Perbaikan Tiket':
            return requested_action
        # Otherwise extract the Job Desk name or clean it
        m = re.match(r'^Perbaikan\s*\[([^\]]+)\]\s*(.*)$', s, re.IGNORECASE)
        if m:
            job_desk = m.group(1).strip()
            rest = m.group(2).strip()
            # If rest just contains asset codes or specs, return Job Desk
            # Or return job_desk itself
            return job_desk
    
    # Also if subject is "Perbaikan KODE - NAMA"
    return s

print(clean_ticket_subject("Perbaikan [Job Desk Corrective IT Asset Notebook] IT-NBK-000001 - Lenovo Lenovo G40-80 Core i5-5200U - IT-NBK-000001", "Trouble Shot System Operasi"))
print(clean_ticket_subject("Perbaikan [Job Desk Corrective IT Asset Notebook] IT-NBK-000001 - Lenovo Lenovo G40-80 Core i5-5200U - IT-NBK-000001", None))
print(clean_ticket_subject("Layar bluescreen tidak bisa booting", "Ganti Hardisk"))
