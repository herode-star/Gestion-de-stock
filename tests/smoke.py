"""Integration checks against a disposable Docker database; never production."""
import base64, http.cookiejar, json, re, subprocess, urllib.request, urllib.parse, urllib.error
BASE = 'http://127.0.0.1:8080/'
jar = http.cookiejar.CookieJar()
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
def request(path, data=None, expected=200):
    payload = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        response = client.open(BASE + path, payload)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode()
    assert response.status == expected, (path, response.status, body[:200])
    assert 'Fatal error' not in body and 'Warning:' not in body, (path, body[:200])
    return body

def upload(data, image_bytes):
    boundary = 'StockIntegrationBoundary2026'
    parts = []
    for key, value in data.items():
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n').encode())
    parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="test.png"\r\nContent-Type: image/png\r\n\r\n').encode() + image_bytes + b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    req = urllib.request.Request(BASE + 'products.php', b''.join(parts), {'Content-Type': 'multipart/form-data; boundary=' + boundary})
    with client.open(req) as response:
        body = response.read().decode()
        assert response.status == 200 and 'Fatal error' not in body, body[:200]

def token(path):
    return re.search(r'name="csrf" value="([^"]+)"', request(path)).group(1)

def backup():
    return json.loads(request('backup.php'))['tables']

csrf = token('setup.php')
request('setup.php', dict(csrf=csrf, business_name='Test Stock', name='Test Owner', email='owner@example.test', password='Test-only-12345', currency='HTG'))
for page in ['index.php','products.php','suppliers.php','users.php','sales.php','analytics.php','activity.php','settings.php','stock.php','ajoutproduit.php']:
    request(page)
csrf = token('products.php?new=1')
product = dict(csrf=csrf, action='save', id='0', model='Test product', reference='TEST-001', brand='Test', category='Test', price='12.50', cost_price='7.25', quantity='10', supplier_id='', description='Integration test')
request('products.php', product)
rows = backup()['produit']
p = next(row for row in rows if row['referance'] == 'TEST-001')
pid = p['id']
assert int(p['quantite']) == 10
request('sales.php',dict(csrf=csrf, product_id=pid, quantity='3', unit_price='12.50', customer='Test',payment='Lajan kach'))
b = backup()
assert int(next(row for row in b['produit'] if row['id']==pid)['quantite']) == 7
assert float(b['sales'][-1]['total']) == 37.5
assert float(b['sale_items'][-1]['cost_price']) == 7.25
sales_count = len(b['sales'])
for quantity, price in [('99','12.50'),('0','12.50'),('-1','12.50'),('1.5','12.50'),('1','abc'),('1','-2')]:
    request('sales.php',dict(csrf=csrf, product_id=pid, quantity=quantity, unit_price=price, customer='Test',payment='Lajan kach'))
assert len(backup()['sales']) == sales_count
# A stale product form cannot overwrite a sale's stock deduction.
request('products.php',dict(product, id=pid, original_quantity='10', quantity='10'))
assert int(next(row for row in backup()['produit'] if row['id']==pid)['quantite']) == 7
request('products.php',dict(product, id=pid, original_quantity='7', quantity='12'))
assert int(next(row for row in backup()['produit'] if row['id']==pid)['quantite']) == 12
request('products.php',dict(csrf=csrf, action='delete', id=pid))
assert any(row['id']==pid for row in backup()['produit'])
request('sales.php',dict(product_id=pid,quantity=1,unit_price=1),expected=403)
for path in ['bd/otechnologie.sql','core/bootstrap.php','classe/user.php','.env','.git/config','docker-compose.yml']:
    request(path,expected=403)
request('logout.php')
assert 'Konekte' in request('products.php')
csrf=token('login.php')
request('login.php',dict(csrf=csrf,email='owner@example.test',password='Test-only-12345'))
assert 'Test Stock' in request('index.php')
# Verify image replacement rollback and persistence across container recreation.
png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXioAAAAASUVORK5CYII=')
csrf = token('products.php?edit=' + str(pid))
upload(dict(product, csrf=csrf, id=pid, original_quantity='12', quantity='12'), png)
image_name = next(row for row in backup()['produit'] if row['id']==pid)['img']
assert image_name.startswith('upload-')
with client.open(BASE + 'produit/' + image_name) as response:
    assert response.read() == png
upload(dict(product, csrf=csrf, id=pid, original_quantity='12', quantity='12', supplier_id='2147483647'), png)
assert next(row for row in backup()['produit'] if row['id']==pid)['img'] == image_name
with client.open(BASE + 'produit/' + image_name) as response:
    assert response.read() == png
subprocess.run(['docker','compose','up','-d','--force-recreate','--no-deps','--wait','app'],check=True)
with client.open(BASE + 'produit/' + image_name) as response:
    assert response.read() == png
print('PASS: setup, login, pages, products, sales, stock conflicts, backup, CSRF, protected files, image rollback and persistence')
