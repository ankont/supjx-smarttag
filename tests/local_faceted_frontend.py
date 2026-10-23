"""Bounded, sequential frontend regression requests; loopback hosts only.

Run after deploying the changed SmartTag PHP files to an isolated local Joomla.
No installation, database writes, server changes or cache purges are performed.
"""
import argparse
import html
from html.parser import HTMLParser
import json
from pathlib import Path
import urllib.error
import urllib.parse
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--base', default='http://localhost:8089')
parser.add_argument('--baseline', type=Path)
parser.add_argument('--report', type=Path)
args = parser.parse_args()
base = args.base.rstrip('/')
assert urllib.parse.urlsplit(base).hostname in ('localhost', '127.0.0.1', '::1')

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None

client = urllib.request.build_opener(NoRedirect)
observed = []

def get(path):
    target = urllib.parse.urljoin(base, path)
    assert urllib.parse.urlsplit(target).netloc == urllib.parse.urlsplit(base).netloc
    try:
        response = client.open(target, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode('utf-8', errors='replace')
    observed.append({'path': path, 'status': response.code, 'location': response.headers.get('Location')})
    print(f'{response.code} {path}', flush=True)
    return response.code, response.headers, body

class Links(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.links = []
        self.cards = []
        self.in_article = False
        self.card_link = None
        self.feed(body)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'article':
            self.in_article = True
            self.card_link = None
        if tag == 'a' and 'href' in attrs:
            self.links.append(attrs)
            if self.in_article and self.card_link is None:
                self.card_link = attrs['href']

    def handle_endtag(self, tag):
        if tag == 'article':
            self.cards.append(self.card_link)
            self.in_article = False

prefix = '/library/lessons'
canonical = prefix + '/!/primary+virgin-mary.html'
_, _, baseline_canonical = get(canonical)
for expression in ['virgin-mary+primary', 'primary,primary+virgin-mary', 'primary+virgin-mary+primary']:
    status, headers, _ = get(prefix + '/!/' + expression + '.html?start=30&cat-view=list&lang=el-GR')
    assert status == 301, (expression, status)
    location = headers['Location']
    parts = urllib.parse.urlsplit(location)
    assert parts.path == canonical, location
    query = urllib.parse.parse_qs(parts.query)
    assert query['start'] == ['30'] and query['cat-view'] == ['list'], location
    # A current-language parameter may be consumed by Joomla's language router.
    status, _, body = get(location)
    assert status == 200, (location, status)  # no second redirect / no loop
    assert 'lang="el-gr"' in body.lower(), 'Page language changed.'

status, headers, _ = get(prefix + '/!/secondary,primary.html')
assert status == 301 and urllib.parse.urlsplit(headers['Location']).path == prefix + '/!/primary,secondary.html'
status, _, _ = get(headers['Location'])
assert status == 200
status, headers, _ = get(prefix + '/!/primary,secondary+primary.html')
assert status == 301 and urllib.parse.urlsplit(headers['Location']).path == prefix + '/!/primary.html'
status, _, _ = get(headers['Location'])
assert status == 200

filtered = prefix + '/!/primary,secondary.html'
status, _, body = get(filtered + '?cat-view=list')
assert status == 200
first = Links(body)
page2 = [a['href'] for a in first.links if urllib.parse.parse_qs(urllib.parse.urlsplit(a['href']).query).get('start') == ['30'] and 'aria-label' in a]
assert page2 and all(urllib.parse.urlsplit(link).path == filtered for link in page2), page2
assert all(urllib.parse.parse_qs(urllib.parse.urlsplit(link).query).get('cat-view') == ['list'] for link in page2)
if args.baseline:
    original = Links(args.baseline.read_text(encoding='utf-8'))
    assert original.cards and original.cards == first.cards, 'Filtered page-1 article links changed.'
status, _, body = get(page2[0])
assert status == 200
second = Links(body)
assert first.cards and second.cards and not set(first.cards).intersection(second.cards)
previous = [a['href'] for a in second.links if 'aria-label' in a and urllib.parse.urlsplit(a['href']).path == filtered and urllib.parse.parse_qs(urllib.parse.urlsplit(a['href']).query).get('start', ['0']) == ['0']]
assert previous, 'No filtered first/previous link.'
status, _, _ = get(previous[0])
assert status == 200
filter_controls = [a['href'] for a in second.links if 'library-taxonomy__option' in a.get('class', '')]
assert filter_controls and all(not {'start', 'limitstart'}.intersection(urllib.parse.parse_qs(urllib.parse.urlsplit(link).query)) for link in filter_controls), 'Filter toggle inherited pagination offset.'
assert 'data-reset-url="/library/lessons.html?cat-view=list"' in body, 'Reset inherited filter/offset.'

status, _, body = get(prefix + '.html?cat-view=list&start=30')
assert status == 200
unfiltered = [a['href'] for a in Links(body).links if a.get('aria-label', '').startswith('Μετάβαση') and a['href'].startswith(prefix + '.html')]
assert unfiltered and all('/!/' not in link and 'cat-view=list' in link for link in unfiltered)
unfiltered_offsets = {int(urllib.parse.parse_qs(urllib.parse.urlsplit(link).query).get('start', ['0'])[0]) for link in unfiltered}
assert {0, 60}.issubset(unfiltered_offsets), 'Unfiltered previous/first/next offsets are incorrect.'
for expression in [','.join(['primary'] * 11), '+'.join(['primary'] * 11), ','.join(['primary'] * 8) + '+' + ','.join(['secondary'] * 8), 'primary,,secondary', 'no-such-tag-for-regression']:
    status, _, _ = get(prefix + '/!/' + expression + '.html')
    assert status == 404, (expression, status)
report = json.dumps({'requests': len(observed), 'result': 'PASS', 'checks': observed}, ensure_ascii=False, indent=2)
if args.report:
    args.report.write_text(report, encoding='utf-8')
print(report)
