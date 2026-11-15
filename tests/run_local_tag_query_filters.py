"""Run candidate PHP sources in the local Joomla container without installation."""
from pathlib import Path
import base64
import json
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
base = root / 'package/plugins/system/tagrouting/src'
sources = {'SuperSoft\\Plugin\\System\\TagRouting\\' + '\\'.join(p.relative_to(base).with_suffix('').parts): base64.b64encode(p.read_bytes()).decode() for p in base.rglob('*.php')}
addon = root / 'package/plugins/system/smarttagenhancements/src'
sources.update({'SuperSoft\\Plugin\\System\\SmartTagEnhancements\\' + '\\'.join(p.relative_to(addon).with_suffix('').parts): base64.b64encode(p.read_bytes()).decode() for p in addon.rglob('*.php')})
script = (root / (sys.argv[1] if len(sys.argv)>1 else 'tests/local_tag_query_filters.php')).read_text(encoding='utf-8')
if '--editor-fixtures' in sys.argv[2:]:
    script = script.replace('// Run via', '$exportEditorFixtures = true;\n// Run via', 1)
key = 'SuperSoft\\Plugin\\System\\TagRouting\\Extension\\TagRouting'
code = base64.b64decode(sources[key]).replace(b"dirname(__DIR__, 2) . '/forms/routing.xml'", b"$GLOBALS['smarttag_form_fixture']")
sources[key] = base64.b64encode(code).decode()
options_key = 'SuperSoft\\Plugin\\System\\SmartTagEnhancements\\Configuration\\OptionsForm'
options_code = base64.b64decode(sources[options_key]).replace(b"dirname(__DIR__, 2) . '/smarttagenhancements.xml'", b"$GLOBALS['enhancements_manifest_fixture']")
sources[options_key] = base64.b64encode(options_code).decode()
editor_key = 'SuperSoft\\Plugin\\System\\SmartTagEnhancements\\Site\\Controller\\TagController'
editor_code = base64.b64decode(sources[editor_key]).replace(b"dirname(__DIR__, 3) . '/layouts'", b"$GLOBALS['smarttag_editor_layout_path']")
sources[editor_key] = base64.b64encode(editor_code).decode()
manifest_data = base64.b64encode((addon.parent / 'smarttagenhancements.xml').read_bytes()).decode()
manifest_setup = '$enhancementsManifestFile = tempnam(sys_get_temp_dir(), \'smarttag-options-\'); file_put_contents($enhancementsManifestFile, base64_decode(' + repr(manifest_data) + ')); $GLOBALS[\'enhancements_manifest_fixture\'] = $enhancementsManifestFile; register_shutdown_function(static function() use ($enhancementsManifestFile) {unlink($enhancementsManifestFile);});\n'
script = script.replace('// Run via', manifest_setup + '// Run via', 1)
editor_languages = {lang: base64.b64encode((addon.parent / ('language/' + lang + '/plg_system_smarttagenhancements.ini')).read_bytes()).decode() for lang in ['en-GB', 'el-GR']}
script = script.replace('// Run via', '$editorLanguages = json_decode(base64_decode(' + repr(base64.b64encode(json.dumps(editor_languages).encode()).decode()) + '), true);\n// Run via', 1)
editor_layout = base64.b64encode((addon.parent / 'layouts/edit.php').read_bytes()).decode()
script = script.replace('// Run via', '$editorLayoutSource = base64_decode(' + repr(editor_layout) + ');\n// Run via', 1)
forms = [base64.b64encode((root / p).read_bytes()).decode() for p in ['package/plugins/system/tagrouting/forms/routing.xml','package/components/com_tagrouting/site/tmpl/tags/default.xml','package/plugins/system/tagrouting/tagrouting.xml','package/plugins/system/tagrouting/language/en-GB/plg_system_tagrouting.ini']]
script = script.replace('// Run via', '$formSources = ' + repr(forms) + ';\n// Run via', 1)
script = script.replace('// Run via', '$sources = json_decode(base64_decode(\'' + base64.b64encode(json.dumps(sources).encode()).decode() + '\'), true);\n// Run via', 1)
result = subprocess.run(['docker','exec','-u','www-data','-i','catichisi-local-web-1','php'],input=script,text=True,capture_output=True,encoding='utf-8',timeout=60)
print(result.stdout)
if result.stderr:
    print(result.stderr)
raise SystemExit(result.returncode)
