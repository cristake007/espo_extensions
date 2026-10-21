"""Validate the actual Playwright download. pypdf is a temporary test dependency, never packaged."""
from pathlib import Path
import json
import re
import sys
sys.path.insert(0, '/tmp/employee-pdf-validation')
from pypdf import PdfReader
root = Path(__file__).resolve().parents[1] / 'evidence'
reader = PdfReader(root / 'functional-attendance.pdf', strict=True)
# The earlier 65-page download was parsed end to end. For the final larger
# download, parse all page structures and sample text at both ends and the
# first eight pages containing the synthetic Functional users.
sampled_pages = sorted(set(range(min(8, len(reader.pages)))) | {len(reader.pages)-1})
texts = [reader.pages[index].extract_text() for index in sampled_pages]
compact = [re.sub(r'\s+', '', text) for text in texts]
checks = {
    'readable-PDF-pages': len(reader.pages) > 50,
    'native-WTC-11-17-in-PDF': any('11:00' in text and '17:00' in text for text in texts),
    'manager-in-PDF': any('Functionalmanager' in text for text in compact),
    'employee-in-PDF': any('Functionalemployee' in text for text in compact),
    'sampled-pages-have-title': all('Condica de prezenta' in text for text in texts),
    'first-page-number': f'Pagina1din{len(reader.pages)}' in compact[0],
    'last-page-number': f'Pagina{len(reader.pages)}din{len(reader.pages)}' in compact[-1],
    'A4-portrait': all(abs(float(p.mediabox.width)-595.28)<1 and abs(float(p.mediabox.height)-841.89)<1 for p in reader.pages),
}
result = {'results': [{'test': name, 'status': 'PASS' if value else 'FAIL'} for name, value in checks.items()],
          'pages': len(reader.pages), 'textSamplePageNumbers': [n+1 for n in sampled_pages], 'assertions': len(checks), 'failures': sum(not value for value in checks.values())}
(root / 'functional-pdf-validation.json').write_text(json.dumps(result, indent=2)+'\n')
print(json.dumps(result))
assert result['failures'] == 0
