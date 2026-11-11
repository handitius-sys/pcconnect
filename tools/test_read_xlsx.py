with open('tools/sample.xlsx', 'rb') as f:
    data = f.read()

# Let's verify we can unzip and check contents in python
import zipfile, io
z = zipfile.ZipFile(io.BytesIO(data))
print("Files in sample.xlsx:", z.namelist())

