with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's find where declare(strict_types=1) is
idx_dec = text.find('declare(strict_types=1);')
print("declare idx:", idx_dec)

# Remove format_clean_ticket_subject from the top
idx_fn = text.find('function format_clean_ticket_subject(')
idx_fn_end = text.find('}\n\n', idx_fn) + 3

fn_code = text[idx_fn:idx_fn_end]
text = text[:idx_fn] + text[idx_fn_end:]

# Place fn_code right after declare(strict_types=1);\n
idx_dec = text.find('declare(strict_types=1);')
idx_after_dec = text.find('\n', idx_dec) + 1

text = text[:idx_after_dec] + "\n" + fn_code + "\n" + text[idx_after_dec:]

with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
    f.write(text)

print("Placed format_clean_ticket_subject after declare statement successfully!")
