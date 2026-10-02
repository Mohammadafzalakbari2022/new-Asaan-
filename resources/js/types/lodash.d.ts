/**
 * Types for the one lodash function this app imports.
 *
 * lodash ships no declarations of its own, and @types/lodash is a large
 * package to carry for a single import. Every caller in this codebase imports
 * `debounce` and nothing else, so this declares exactly that one function and
 * leaves the rest of the module out.
 */
declare module 'lodash' {
    export interface DebounceOptions {
        leading?: boolean;
        trailing?: boolean;
        maxWait?: number;
    }

    export interface DebouncedFunction<T extends (...args: any[]) => any> {
        (this: ThisParameterType<T>, ...args: Parameters<T>): ReturnType<T> | undefined;
        cancel(): void;
        flush(): ReturnType<T> | undefined;
        pending(): boolean;
    }

    export function debounce<T extends (...args: any[]) => any>(
        func: T,
        wait?: number,
        options?: DebounceOptions,
    ): DebouncedFunction<T>;
}
