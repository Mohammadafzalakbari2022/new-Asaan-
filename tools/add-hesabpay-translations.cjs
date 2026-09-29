/**
 * Add the HesabPay admin screen strings to the Dari, Pashto and English admin
 * dictionaries.
 *
 * Existing keys keep their original order and value; new keys are appended.
 * Existing keys are never overwritten, so this is safe to re-run.
 */
const fs = require('fs');
const NEW = require('./hesabpay-translations.json');

const files = {
  en: 'lang/en/admin.json',
  fa: 'lang/fa/admin.json',
  ps: 'lang/ps/admin.json',
};

for (const [locale, path] of Object.entries(files)) {
  const dict = JSON.parse(fs.readFileSync(path, 'utf8'));
  const additions = NEW[locale];

  let added = 0;
  let skipped = 0;

  // Rebuild in the existing order, then append only genuinely new keys.
  const merged = {};
  for (const [key, value] of Object.entries(dict)) {
    merged[key] = value;
  }
  for (const [key, value] of Object.entries(additions)) {
    if (Object.prototype.hasOwnProperty.call(merged, key)) {
      skipped++;
      continue;
    }
    merged[key] = value;
    added++;
  }

  fs.writeFileSync(path, JSON.stringify(merged, null, 4) + '\n', 'utf8');

  const verify = JSON.parse(fs.readFileSync(path, 'utf8'));
  const missing = Object.keys(additions).filter((k) => !(k in verify));
  if (missing.length) {
    throw new Error(`${path}: keys missing after write: ${missing.join(', ')}`);
  }

  console.log(`  ${path}: +${added} added, ${skipped} already present, ${Object.keys(verify).length} total`);
}
