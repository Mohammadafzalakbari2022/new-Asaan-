/**
 * Fast syntax gate for the new referral Vue files.
 *
 * The full `npm run build` on this repo takes 20+ minutes and died twice before
 * producing any output. This parses and compiles each SFC on its own with the
 * same compiler Vite uses, which catches template and script errors in seconds.
 * It does NOT resolve imports, so a full build is still required afterwards.
 *
 * Usage: node scripts/check-referral-sfc.mjs
 */
import { readFileSync } from 'node:fs';
import { parse, compileScript, compileTemplate } from 'vue/compiler-sfc';
import { transform } from 'esbuild';

const files = [
  'resources/js/pages/Admin/Marketing/Referrals/Index.vue',
  'resources/js/pages/Admin/Marketing/Referrals/TopReferrers.vue',
  'resources/js/pages/Admin/Marketing/Referrals/People.vue',
  'resources/js/pages/Admin/Marketing/Referrals/PersonShow.vue',
  'resources/js/pages/Admin/Marketing/Referrals/Commissions.vue',
  'resources/js/pages/Admin/Marketing/Referrals/Settings.vue',
  'templates/storefront/general/cartxis-default/resources/views/pages/Account/Referrals/Index.vue',
  'templates/storefront/general/cartxis-default/resources/views/pages/Account/Referrals/Landing.vue',
  'templates/storefront/general/cartxis-default/resources/views/pages/Checkout/Index.vue',
  'templates/storefront/general/cartxis-default/resources/views/pages/Account/Dashboard.vue',
  'templates/storefront/general/cartxis-default/resources/views/components/ThemeHeader.vue',
];

// Plain TypeScript, checked with esbuild rather than the SFC compiler.
const scripts = ['resources/js/composables/useMenuIcons.ts', 'resources/js/composables/useCurrency.ts'];

let failures = 0;

/**
 * The SFC compiler happily accepts a component that was never imported, and the
 * failure only shows up at runtime as a blank spot with a console warning. That
 * is how a paginator written as <Link> survived a full compile. So check every
 * PascalCase tag against the script block.
 */
const BUILT_IN_TAGS = new Set([
  'Transition', 'TransitionGroup', 'KeepAlive', 'Teleport', 'Suspense',
  'Component', 'RouterLink', 'RouterView', 'template', 'component', 'slot',
]);

function unresolvedComponents(descriptor, filename) {
  if (!descriptor.template) return [];

  const script = [descriptor.script?.content, descriptor.scriptSetup?.content]
    .filter(Boolean)
    .join('\n');

  // Names the script actually brings into scope. Parsing the import clauses
  // properly matters: a loose regex happily matches "Link" mentioned in some
  // other import and passes a component that was never imported at all.
  const declared = new Set();

  for (const match of script.matchAll(/import\s+([\s\S]*?)\s+from\s+['"][^'"]+['"]/g)) {
    for (const part of match[1].split(',')) {
      // Strip the braces and the `type` keyword, then take the last identifier
      // in the part: that is the bound name in `Foo`, `* as Icons` and
      // `Foo as Bar` alike.
      const identifiers = part
        .replace(/[{}]/g, ' ')
        .match(/[A-Za-z_$][\w$]*/g);
      if (!identifiers) continue;
      const name = identifiers[identifiers.length - 1];
      if (name !== 'type' && name !== 'as' && name !== 'from') declared.add(name);
    }
  }

  for (const match of script.matchAll(/\b(?:const|let|function|class|interface|type)\s+([A-Za-z_$][\w$]*)/g)) {
    declared.add(match[1]);
  }

  // Anything destructured out of defineProps is usable in the template.
  for (const match of script.matchAll(/defineProps\s*(?:<[^>]*>)?\s*\(\s*\{([\s\S]*?)\}/g)) {
    for (const part of match[1].split(',')) {
      const name = part.replace(/\btype\b/g, '').split(':')[0].split('=')[0].trim();
      if (/^[A-Za-z_$][\w$]*$/.test(name)) declared.add(name);
    }
  }

  const used = new Set();
  for (const match of descriptor.template.content.matchAll(/<([A-Z][A-Za-z0-9]*)\b/g)) {
    used.add(match[1]);
  }

  const missing = [...used].filter((tag) => !BUILT_IN_TAGS.has(tag) && !declared.has(tag));

  if (missing.length) {
    return [`template: <${missing.join('>, <')}> used but never imported in ${filename}`];
  }
  return [];
}

for (const file of files) {
  const source = readFileSync(file, 'utf8');
  const problems = [];
  const { descriptor, errors } = parse(source, { filename: file });

  for (const error of errors) {
    problems.push(`parse: ${error.message}`);
  }

  // Script + template, the two things a typo usually breaks.
  if (descriptor.script || descriptor.scriptSetup) {
    try {
      compileScript(descriptor, { id: file, isProd: true });
    } catch (error) {
      problems.push(`script: ${error.message}`);
    }
  }

  if (descriptor.template) {
    const result = compileTemplate({
      source: descriptor.template.content,
      filename: file,
      id: file,
      compilerOptions: { isProd: true },
    });
    for (const error of result.errors) {
      problems.push(`template: ${error.message ?? error}`);
    }
  }

  problems.push(...unresolvedComponents(descriptor, file));

  if (problems.length) {
    failures++;
    console.log(`FAIL ${file}`);
    for (const problem of problems) {
      console.log(`     ${problem}`);
    }
  } else {
    console.log(`ok   ${file}`);
  }
}

for (const file of scripts) {
  try {
    await transform(readFileSync(file, 'utf8'), { loader: 'ts' });
    console.log(`ok   ${file}`);
  } catch (error) {
    failures++;
    console.log(`FAIL ${file}`);
    console.log(`     script: ${error.message}`);
  }
}

console.log(failures ? `\n${failures} file(s) failed` : `\nAll ${files.length + scripts.length} files compiled`);
process.exit(failures ? 1 : 0);
