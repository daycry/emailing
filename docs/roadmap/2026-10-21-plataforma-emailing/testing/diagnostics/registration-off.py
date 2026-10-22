import http.cookiejar
import json
import os
import pathlib
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid
from html.parser import HTMLParser

root = pathlib.Path(__file__).resolve().parents[5]
run = str(uuid.uuid4())
email = 'qa-' + run + '-off@example.test'
name = 'QA Disabled diagnostic ' + run
env = dict(os.environ, PHP_INI_SCAN_DIR=str(root / 'storage/php-conf'))

def counts(cleanup=False):
    args = ['php', str(pathlib.Path(__file__).with_name('registration-count.php')), email, name]
    if cleanup:
        args.append('cleanup')
    result = subprocess.run(args, cwd=root, env=env, capture_output=True, text=True, check=True, timeout=30, creationflags=subprocess.CREATE_NO_WINDOW)
    return json.loads(result.stdout)

class Token(HTMLParser):
    value = None
    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'input' and values.get('name') == '_token':
            self.value = values.get('value')

before = counts()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
with opener.open('http://127.0.0.1:8084/login', timeout=30) as response:
    page = response.read().decode('utf-8')
    login_status = response.status
parser = Token()
parser.feed(page)
if not parser.value or any(before.values()):
    raise RuntimeError('Precondition failed; no request sent.')
body = urllib.parse.urlencode({'_token': parser.value, 'name': 'QA Diagnostic', 'account_name': name, 'email': email, 'password': 'Example-diagnostic-' + run, 'password_confirmation': 'Example-diagnostic-' + run}).encode()
try:
    with opener.open('http://127.0.0.1:8084/register', data=body, timeout=30) as response:
        post_status = response.status
except urllib.error.HTTPError as error:
    post_status = error.code
result = {'login_status': login_status, 'csrf_present': True, 'registration_post_status': post_status, 'before': before, 'after': counts()}
if any(result['after'].values()):
    result['after_cleanup'] = counts(cleanup=True)
print(json.dumps(result))
