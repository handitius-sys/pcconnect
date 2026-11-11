with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect handle_route_ticket_form around line 1315-1335
idx = text.find('if ($repairCost > 0 || $solutionDetails !== \'\' || $partNewName !== \'\' || $invoicePhoto !== null)')
print(text[idx-50:idx+600])
