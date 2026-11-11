with open('app/modules/corrective.php', 'r', encoding='utf-8') as f:
    text = f.read()

# Let's inspect ticket action options or job desk actions
idx = text.find('Perbaikan [')
print('idx in corrective:', idx)
