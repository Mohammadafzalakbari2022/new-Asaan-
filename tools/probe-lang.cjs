// Inspect the tail structure of the admin dictionaries without touching them.
const fs = require('fs');

for (const f of ['lang/en/admin.json', 'lang/fa/admin.json', 'lang/ps/admin.json']) {
  const text = fs.readFileSync(f, 'utf8');
  const lines = text.split('\n');
  console.log(`=== ${f} : ${lines.length} lines ===`);
  console.log('  first 4:');
  for (const l of lines.slice(0, 4)) console.log('    ' + JSON.stringify(l));
  console.log('  last 4:');
  for (const l of lines.slice(-4)) console.log('    ' + JSON.stringify(l));
  // Round-trip safety check: does it parse as JSON today?
  try {
    JSON.parse(text);
    console.log('  parses: yes');
  } catch (e) {
    console.log('  parses: NO -> ' + e.message);
  }
  console.log('');
}
