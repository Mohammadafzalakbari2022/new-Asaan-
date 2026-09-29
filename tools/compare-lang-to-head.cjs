/**
 * Confirm the working-tree admin dictionaries contain exactly the same
 * key/value pairs as HEAD, so re-applying the original ordering loses nothing.
 */
const { execSync } = require('child_process');
const fs = require('fs');

for (const path of ['lang/en/admin.json', 'lang/fa/admin.json', 'lang/ps/admin.json']) {
  const head = JSON.parse(execSync(`git show HEAD:${path}`, { encoding: 'utf8' }));
  const work = JSON.parse(fs.readFileSync(path, 'utf8'));

  const headKeys = Object.keys(head);
  const workKeys = Object.keys(work);

  const lostKeys = headKeys.filter((k) => !(k in work));
  const changedValues = headKeys
    .filter((k) => k in work && work[k] !== head[k])
    .map((k) => `${k}\n      HEAD: ${head[k]}\n      WORK: ${work[k]}`);
  const addedKeys = workKeys.filter((k) => !(k in head));

  console.log(`=== ${path} ===`);
  console.log(`  HEAD keys : ${headKeys.length}`);
  console.log(`  WORK keys : ${workKeys.length}`);
  console.log(`  lost      : ${lostKeys.length}${lostKeys.length ? ' -> ' + lostKeys.join(' | ') : ''}`);
  console.log(`  changed   : ${changedValues.length}`);
  for (const c of changedValues) console.log('      ' + c);
  console.log(`  added     : ${addedKeys.length}`);
  console.log('');
}
