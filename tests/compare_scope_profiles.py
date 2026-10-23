"""Compare captured local before/after HTML and taxonomy query counters."""
import argparse
from html.parser import HTMLParser
import json
from pathlib import Path
from urllib.parse import urlsplit

parser = argparse.ArgumentParser()
parser.add_argument('directory', type=Path)
parser.add_argument('--report', type=Path)
args = parser.parse_args()
class Result(HTMLParser):
    def __init__(self, text):
        super().__init__()
        self.article = False
        self.first = None
        self.cards = []
        self.active = []
        self.feed(text)
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'article': self.article, self.first = True, None
        if tag == 'a' and self.article and self.first is None: self.first = attrs.get('href')
        classes = attrs.get('class', '').split()
        if tag == 'a' and 'library-taxonomy__option' in classes and 'is-active' in classes:
            self.active.append(urlsplit(attrs['href']).path)
    def handle_endtag(self, tag):
        if tag == 'article': self.cards.append(self.first); self.article = False
before = Result((args.directory / 'nonempty-before.html').read_text(encoding='utf-8'))
after = Result((args.directory / 'nonempty-after.html').read_text(encoding='utf-8'))
assert before.cards and before.cards == after.cards, 'Nonempty article results or ordering changed.'
assert before.active and before.active == after.active, 'Active filter controls changed.'
metrics = [json.loads((args.directory / ('nonempty-' + version + '-metrics.json')).read_text(encoding='utf-8-sig')) for version in ['before', 'after']]
assert metrics[1]['queries'] < metrics[0]['queries'], 'No measured taxonomy query reduction.'
report = {'same_article_links_in_order': True, 'articles': len(after.cards), 'same_active_filter_paths': True, 'active_options': len(after.active), 'before_scope_selects': metrics[0]['queries'], 'after_scope_selects': metrics[1]['queries'], 'before_resolve_calls': metrics[0]['calls'], 'after_resolve_calls': metrics[1]['calls'], 'note': 'Counts cover only CategoryTaxonomyResolver SELECTs; this is not an all-query benchmark or load test.'}
serialized = json.dumps(report, indent=2)
if args.report: args.report.write_text(serialized, encoding='utf-8')
print(serialized)
