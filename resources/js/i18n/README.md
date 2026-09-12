# English language keys

The welcome page and its shared components use the English JSON catalogs in
`en/`. Page copy belongs in `welcome.json`; reusable component copy belongs in
its component catalog. Role descriptions and room errors have shared catalogs.

```vue
<script setup lang="ts">
import { t } from '@/i18n';
</script>

<template>
    <p>{{ t('welcome.hero.player_count', { minPlayers: 3, maxPlayers: 15 }) }}</p>
</template>
```

Use stable, descriptive keys and named placeholders such as `{count}`. Keep a
complete sentence in one message where possible. Translations are rendered as
text; keep markup in Vue templates. Translate labels, placeholders, metadata,
image descriptions, and accessibility text as well as visible copy. Keep game
IDs, URLs, and user or server supplied content separate from language keys.

For the next page, add an English JSON catalog and export it from `en/index.ts`.
The `t()` key type automatically includes that catalog. Only English is wired
up for now; language selection and additional locales can be added separately.
