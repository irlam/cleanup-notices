"""Keep the offline editor identical to the live notice form (no PHP execution)."""
from pathlib import Path
import re
root = Path(__file__).resolve().parents[1]
source = (root / 'forms/clean-up/form.php').read_text()
form = re.search(r'<main class="container docs-form-page">[\s\S]*?</main>', source).group(0)
form = '\n'.join(line for line in form.splitlines() if 'name="csrf_token"' not in line and 'name="client_submission_id"' not in line)
assert '<?' not in form, 'Offline form must not include PHP or session credentials.'
p = root / 'field.html'
s = p.read_text()
s = re.sub(r'(<div id="offlineEditor">)[\s\S]*?(</div>\s*<footer)', lambda m: m.group(1) + form + m.group(2), s)
p.write_text(s)
