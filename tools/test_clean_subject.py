with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect format_ticket_subject_clean or clean_ticket_subject
# Let's write a python function to test regex cleaning
import re

def clean_subject(raw):
    s = str(raw or '').strip()
    # Pattern 1: "Perbaikan [Job Desk ...] KODE - NAMA - KODE"
    # If it starts with Perbaikan [Job Desk ...]
    m = re.match(r'^Perbaikan\s*\[[^\]]+\]\s*(.*)$', s, re.IGNORECASE)
    if m:
        rest = m.group(1).strip()
        # rest might be "IT-NBK-000001 - Lenovo Lenovo G40-80 Core i5-5200U - IT-NBK-000001"
        # or maybe the user appended something or it was just auto generated
        parts = [p.strip() for p in rest.split(' - ') if p.strip()]
        # If it's just code - name - code or code - name
        # If parts has at least 2 and first is code
        # What should be displayed if it was auto-generated?
        # If the whole subject was auto-generated without user-specific issue text:
        # e.g. "Perbaikan [Job Desk Corrective IT Asset Notebook]" -> "Perbaikan IT Asset Notebook" or just extract the actual complaint?
        return rest
    return s

test_s = "Perbaikan [Job Desk Corrective IT Asset Notebook] IT-NBK-000001 - Lenovo Lenovo G40-80 Core i5-5200U - IT-NBK-000001"
print("Cleaned:", clean_subject(test_s))
