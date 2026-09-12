import en from './en/index.ts';

type Catalog = typeof en;
export type TranslationKey = {
    [
        Namespace in keyof Catalog
    ]: `${Namespace}.${keyof Catalog[Namespace] & string}`;
}[keyof Catalog];

const messages: Record<string, string> = Object.fromEntries(
    Object.entries(en).flatMap(([namespace, entries]) =>
        Object.entries(entries).map(([key, value]) => [
            `${namespace}.${key}`,
            value,
        ]),
    ),
);

/** English is the initial catalog. Keep named values inside complete messages. */
export function t(
    key: TranslationKey,
    values: Record<string, string | number> = {},
): string {
    return messages[key].replace(/\{(\w+)\}/g, (placeholder, name: string) =>
        Object.prototype.hasOwnProperty.call(values, name)
            ? String(values[name])
            : placeholder,
    );
}
