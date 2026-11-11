with open('app/modules/maintenance.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Remove the prefix before <?php
if text.startswith('/**'):
    idx_php = text.find('<?php')
    helper_code = text[:idx_php]
    # Place helper_code right after <?php\n
    text = "<?php\n" + helper_code + text[idx_php + 5:]
    with open('app/modules/maintenance.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Fixed maintenance.php <?php placement!")
