<?php

/**
 * Guards against translation keys that exist in code but not in the
 * dictionaries. Laravel silently falls back to printing the raw key, so a
 * Dari or Pashto shopper would see the English sentence (or worse, a
 * "settings.missing_key" style string) with no error anywhere.
 */

$skipNamespaces = [
    'validation', 'auth', 'passwords', 'password', 'pagination', 'http',
    'Illuminate', 'components', 'livewire', 'sanctum', 'fortify', 'jetstream',
];

$scanDirs = ['resources/views', 'resources/js', 'packages', 'templates', 'app'];

/**
 * Strings that are obviously code, not something to translate.
 */
$isCode = function (string $key): bool {
    if ($key === '') {
        return true;
    }

    // Paths, urls, css selectors, globs, hex colours.
    if (preg_match('~^[/#.*]~', $key)) {
        return true;
    }
    if (preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_./-]+$~', $key)) {
        return true;
    }
    if (preg_match('/^#[0-9a-f]{3,8}$/i', $key)) {
        return true;
    }
    // Punctuation that never appears in a real sentence.
    if (preg_match('~[\\\\|^\[\]()<>{}=;#@$]~', $key)) {
        return true;
    }
    // Needs at least a real word.
    if (! preg_match('/[A-Za-z]{3,}/', $key)) {
        return true;
    }
    // A bare lowercase slug is a key name, not a sentence.
    if (! preg_match('/\s/', $key) && preg_match('~^[a-z0-9_.]+$~', $key)) {
        return true;
    }

    return false;
};

it('has no translation key in code that is missing from the dictionaries', function () use ($scanDirs, $skipNamespaces, $isCode) {
    $root = base_path();

    $files = [];
    foreach ($scanDirs as $dir) {
        $path = $root.'/'.$dir;
        if (! is_dir($path)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (preg_match('/\.(php|vue|js|ts)$/', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
    }

    $used = [];
    $stringRegex = '/([\'"])((?:\\\\.|(?!\1).)*)\1/';

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file);

        if (! preg_match_all($stringRegex, $contents, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $hit) {
            $offset = $hit[0][1];
            $key = stripcslashes($hit[2][0]);
            $prefix = rtrim(substr($contents, 0, $offset));

            // Only strings that are the first argument of a translation call.
            $isCall = preg_match('/(?:__|trans|lang|@lang|\$t)\($/', $prefix) === 1
                || preg_match('/(?<![A-Za-z0-9_$])t\($/', $prefix) === 1;

            if (! $isCall) {
                continue;
            }
            // Replacements built at runtime.
            if (str_contains($key, '$') || str_contains($key, '{')) {
                continue;
            }
            // Namespaced keys come from their own package.
            if (str_contains($key, '::')) {
                continue;
            }
            if (in_array(strtok($key, '.'), $skipNamespaces, true)) {
                continue;
            }
            if ($isCode($key)) {
                continue;
            }

            $used[$key][] = str_replace('\\', '/', substr($file, strlen($root) + 1));
        }
    }

    expect($used)->not->toBeEmpty();

    $dictionary = array_merge(
        (array) json_decode((string) file_get_contents($root.'/lang/en/storefront.json'), true),
        (array) json_decode((string) file_get_contents($root.'/lang/en/admin.json'), true),
    );

    $missing = [];
    foreach ($used as $key => $where) {
        if (! array_key_exists($key, $dictionary)) {
            $missing[$key] = $where[0];
        }
    }

    ksort($missing);

    $report = '';
    foreach ($missing as $key => $where) {
        $report .= "\n  - \"{$key}\"  ({$where})";
    }

    expect($missing)->toBe([], count($missing).' translation key(s) used in code but absent from lang/en/*.json:'.$report);
});

it('has the same keys in every locale', function () {
    $root = base_path();

    foreach (['storefront', 'admin'] as $area) {
        $english = array_keys((array) json_decode((string) file_get_contents($root.'/lang/en/'.$area.'.json'), true));

        foreach (['fa', 'ps'] as $locale) {
            $translated = array_keys((array) json_decode((string) file_get_contents($root.'/lang/'.$locale.'/'.$area.'.json'), true));

            expect(array_diff($english, $translated))
                ->toBe([], $locale.' '.$area.'.json is missing '.count(array_diff($english, $translated)).' key(s) present in en.');

            expect(array_diff($translated, $english))
                ->toBe([], $locale.' '.$area.'.json has '.count(array_diff($translated, $english)).' key(s) not present in en.');
        }
    }
});

it('has no empty translation values', function () {
    $root = base_path();

    foreach (['en', 'fa', 'ps'] as $locale) {
        foreach (['storefront', 'admin'] as $area) {
            $data = (array) json_decode((string) file_get_contents($root.'/lang/'.$locale.'/'.$area.'.json'), true);

            $empty = [];
            foreach ($data as $key => $value) {
                if (! is_string($value) || trim($value) === '') {
                    $empty[] = $key;
                }
            }

            expect($empty)->toBe([], $locale.'/'.$area.'.json has '.count($empty).' empty value(s).');
        }
    }
});