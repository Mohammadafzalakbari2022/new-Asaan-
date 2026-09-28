/**
 * The $t() helper used by every theme template.
 *
 * Theme templates call $t('Some text') and sometimes pass replacements, for
 * example $t('Welcome back, {name}!', { name: user.name }). Until the
 * translation dictionaries land, this resolves a key to itself and fills in
 * the placeholders, so the wording in the template is what a reader sees.
 *
 * When the dictionaries arrive, translate() becomes the only thing that needs
 * to change: a real implementation looks the key up and falls back to the key
 * itself, exactly as this one does.
 */
export function translate(key: string, params?: Record<string, string | number>): string {
    if (!params) {
        return key;
    }

    return key.replace(/\{(\w+)\}/g, (match, name: string) =>
        Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : match,
    );
}

export default translate;
