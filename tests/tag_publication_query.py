"""Execute the production visibility predicate against deliberately stale UCM rows."""
from pathlib import Path
import sqlite3
import subprocess

root = Path(__file__).resolve().parents[1]
php = "require 'package/plugins/system/tagrouting/src/Tags/PublicationQueryGuard.php'; echo \\SuperSoft\\Plugin\\System\\TagRouting\\Tags\\PublicationQueryGuard::condition(\"'com_content.article'\", \"'com_content.category'\", \"'2026-10-07 12:00:00'\", \"'0000-00-00 00:00:00'\", [1]);"
predicate = subprocess.check_output(['php', '-r', php], cwd=root, text=True).replace('#__', '')
db = sqlite3.connect(':memory:')
db.executescript('''
CREATE TABLE categories(id INTEGER, lft INTEGER, rgt INTEGER, published INTEGER, access INTEGER, extension TEXT);
INSERT INTO categories VALUES (1,0,20,1,1,'system'),(2,1,10,1,1,'com_content'),(3,2,5,1,1,'com_content'),(4,11,18,0,1,'com_content'),(5,12,15,1,1,'com_content');
CREATE TABLE content(id INTEGER, state INTEGER, access INTEGER, catid INTEGER, publish_up TEXT, publish_down TEXT);
CREATE TABLE contentitem_tag_map(content_item_id INTEGER, type_alias TEXT);
CREATE TABLE ucm_content(core_content_item_id INTEGER, core_state INTEGER);
''')
cases = [
    (1, 1, 1, 3, None, None),  # published
    (2, 0, 1, 3, None, None),  # unpublished
    (3, -2, 1, 3, None, None),  # trashed
    (4, 2, 1, 3, None, None),  # archived
    (5, 1, 1, 3, '2026-10-08 00:00:00', None),  # scheduled
    (6, 1, 1, 3, None, '2026-10-06 00:00:00'),  # expired
    (7, 1, 2, 3, None, None),  # restricted
    (8, 1, 1, 5, None, None),  # unpublished ancestor
    (9, 1, 1, 3, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
    (10, 1, 1, 3, '2026-10-07 12:00:00', '2026-10-07 12:00:00'),
]
db.executemany('INSERT INTO content VALUES (?,?,?,?,?,?)', cases)
for item_id in range(1, 12):  # ID 11 no longer exists in content
    db.execute('INSERT INTO contentitem_tag_map VALUES (?,?)', (item_id, 'com_content.article'))
    db.execute('INSERT INTO ucm_content VALUES (?,1)', (item_id,))
for item_id, type_alias in [(3, 'com_content.category'), (4, 'com_content.category'), (5, 'com_content.category'), (99, 'other.type')]:
    db.execute('INSERT INTO contentitem_tag_map VALUES (?,?)', (item_id, type_alias))
query = 'SELECT m.content_item_id FROM contentitem_tag_map AS m WHERE ' + predicate + ' AND m.type_alias = ? ORDER BY m.content_item_id'
assert [r[0] for r in db.execute(query, ('com_content.article',))] == [1, 9, 10]
assert [r[0] for r in db.execute(query, ('com_content.category',))] == [3]
assert [r[0] for r in db.execute(query, ('other.type',))] == [99]
db.execute('UPDATE categories SET access=2 WHERE id=2')
assert list(db.execute(query, ('com_content.article',))) == []
assert list(db.execute(query, ('com_content.category',))) == []
db.execute('UPDATE categories SET access=1, published=0 WHERE id=3')
assert list(db.execute(query, ('com_content.article',))) == []
print('OK (live publication SQL: status, dates, access, ancestors, missing records, unrelated types)')
