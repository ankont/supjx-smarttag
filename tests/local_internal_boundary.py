"""Bounded local-only checks for internal state pollution and clean redirects."""
import html
import json
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://localhost:8089'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs): return None
client = urllib.request.build_opener(NoRedirect)
observations = []
def get(path):
    url = urllib.parse.urljoin(BASE, path)
    assert urllib.parse.urlsplit(url).netloc == 'localhost:8089'
    try: response = client.open(url, timeout=60)
    except urllib.error.HTTPError as error: response = error
    body = response.read().decode('utf-8', 'replace')
    observations.append({'url': url, 'status': response.code, 'location': response.headers.get('Location')})
    print(response.code, path, flush=True)
    return response.code, response.headers, body
def assert_clean(body):
    links = [html.unescape(x) for x in re.findall(r'href="([^"]+)"', body)]
    assert not any('__tagrouting' in urllib.parse.unquote(x) for x in links), 'Internal state in rendered hrefs.'
    return links
tag = '/tag/series/agia-grafi-thisavros.html?cat-view=details'
status, _, body = get(tag)
assert status == 200
baseline_links = assert_clean(body)
state = json.dumps({'v': 2, 'category': 9, 'expression': 'primary', 'groups': [[7]], 'resolved': [[{'id': 7, 'alias': 'primary', 'root': 2}]]}, separators=(',', ':'))
for raw in [state, state.replace('"', '\\"'), '', '{malformed']:
    status, headers, _ = get(tag + '&' + urllib.parse.urlencode({'__tagrouting': raw}))
    assert status == 301
    target = headers['Location']
    assert '__tagrouting' not in target and 'cat-view=details' in target
    status, _, clean_body = get(target)
    assert status == 200
    assert assert_clean(clean_body) == baseline_links, 'Clean tag consumers changed.'
status, headers, _ = get('/library/lessons/!/primary,secondary.html?' + urllib.parse.urlencode({'cat-view': 'list', 'start': 30, '__tagrouting': state}))
assert status == 301 and '/!/primary,secondary.html' in headers['Location'] and 'start=30' in headers['Location']
status, _, body = get(headers['Location'])
assert status == 200
links = assert_clean(body)
assert any('/!/primary,secondary.html?cat-view=list' in link for link in links), 'Validated pagination lost the actual URL filters.'
print(json.dumps({'result': 'PASS', 'requests': len(observations), 'observations': observations}, ensure_ascii=False, indent=2))
