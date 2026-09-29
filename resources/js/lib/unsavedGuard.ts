let initial = new Map<Element, string>()

let dirty = false

const TRACKABLE_INPUT_TYPES = ['button', 'submit', 'reset', 'file', 'hidden']

function trackable(el: Element): boolean {
    return el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement
}

function currentValue(el: Element): string {
    if (el instanceof HTMLInputElement && (el.type === 'checkbox' || el.type === 'radio')) {
        return el.checked ? '1' : '0'
    }
    return (el as HTMLInputElement).value
}

function handleInputEvent(event: Event): void {
    const el = event.target
    if (!(el instanceof Element) || !trackable(el)) return
    if (el instanceof HTMLInputElement && TRACKABLE_INPUT_TYPES.includes(el.type)) return

    if (!initial.has(el)) {
        initial.set(el, currentValue(el))
    }

    if (initial.get(el) !== currentValue(el)) {
        dirty = true
    }
}

/**
 * Record every form field's current value as the "clean" baseline for the page.
 * Call this after each page render (navigation) so only genuine user edits are
 * treated as unsaved.
 */
export function snapshotPage(): void {
    initial = new Map()
    dirty = false

    document.querySelectorAll<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>('input, textarea, select').forEach((el) => {
        if (el instanceof HTMLInputElement && TRACKABLE_INPUT_TYPES.includes(el.type)) return
        initial.set(el, currentValue(el))
    })
}

/**
 * Track whether the visitor has typed anything unsaved anywhere on the page.
 * Uses one shared document-level listener so every screen is covered
 * automatically - no screen has to wire this up by hand.
 */
export function installUnsavedTracker(): void {
    snapshotPage()
    document.addEventListener('input', handleInputEvent, true)
    document.addEventListener('change', handleInputEvent, true)
}

export function hasUnsavedChanges(): boolean {
    return dirty
}