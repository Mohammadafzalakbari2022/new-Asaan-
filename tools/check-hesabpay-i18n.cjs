/**
 * Cross-check every $t('...') string used by the new HesabPay admin screen against
 * the three admin dictionaries, so a missing key is caught here instead of showing
 * up as a raw English string on a Dari page.
 */
const fs = require('fs');

const vue = fs.readFileSync(
  'resources/js/pages/Admin/Settings/PaymentMethods/ConfigureHesabPay.vue',
  'utf8'
);

// $t('key') and $t("key")
const used = new Set();
for (const m of vue.matchAll(/\$t\(\s*'((?:[^'\\]|\\.)*)'/g)) used.add(m[1].replace(/\\'/g, "'"));
for (const m of vue.matchAll(/\$t\(\s*"((?:[^"\\]|\\.)*)"/g)) used.add(m[1].replace(/\\"/g, '"'));

const dicts = {
  en: JSON.parse(fs.readFileSync('lang/en/admin.json', 'utf8')),
  fa: JSON.parse(fs.readFileSync('lang/fa/admin.json', 'utf8')),
  ps: JSON.parse(fs.readFileSync('lang/ps/admin.json', 'utf8')),
};

let problems = 0;
for (const key of [...used].sort()) {
  const missing = Object.entries(dicts)
    .filter(([, d]) => !(key in d))
    .map(([l]) => l);
  if (missing.length) {
    console.log(`  MISSING [${missing.join(', ')}]  ${key}`);
    problems++;
  }
}

console.log('');
console.log(`  ${used.size} distinct keys used, ${problems} with a missing translation`);

// Also confirm the three dictionaries have identical key sets, so no locale is
// silently short of strings the others have.
const base = JSON.stringify(Object.keys(dicts.en).sort());
for (const [loc, d] of Object.entries(dicts)) {
  const same = JSON.stringify(Object.keys(d).sort()) === base;
  console.log(`  ${loc}: ${Object.keys(d).length} keys, key set matches en: ${same ? 'yes' : 'NO'}`);
}

if (problems) process.exit(1);
