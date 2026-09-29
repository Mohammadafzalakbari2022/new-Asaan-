// Confirm a parse -> stringify round trip is byte-identical, so appending keys
// cannot silently reformat or mangle the existing translations.
const fs = require('fs');

for (const f of ['lang/en/admin.json', 'lang/fa/admin.json', 'lang/ps/admin.json']) {
  const original = fs.readFileSync(f, 'utf8');
  const round = JSON.stringify(JSON.parse(original), null, 4) + '\n';

  if (round === original) {
    console.log(`  ${f}: round trip identical`);
  } else {
    console.log(`  ${f}: DIFFERS`);
    const a = original.split('\n');
    const b = round.split('\n');
    console.log(`    original ${a.length} lines, round trip ${b.length} lines`);
    let shown = 0;
    for (let i = 0; i < Math.max(a.length, b.length) && shown < 5; i++) {
      if (a[i] !== b[i]) {
        console.log(`    line ${i + 1}:`);
        console.log(`      was: ${JSON.stringify(a[i])}`);
        console.log(`      now: ${JSON.stringify(b[i])}`);
        shown++;
      }
    }
  }
}
