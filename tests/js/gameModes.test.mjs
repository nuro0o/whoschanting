import assert from 'node:assert/strict';
import test from 'node:test';
import {
    copyModeSetup,
    customModeError,
    defaultModeSetup,
    modeName,
    modeSubmission,
    rosterTotals,
} from '../../resources/js/lib/gameModes.ts';

await test('custom defaults provide one copy per active role and drafts cannot mutate saved settings', () => {
    const setup = { ...defaultModeSetup(), mode: 'custom' };
    assert.deepEqual(setup.roles, { oracle: 1, veilweaver: 1, townsperson: 1 });
    assert.equal(customModeError(setup), null);
    const draft = copyModeSetup(setup);
    draft.roles.oracle = 3;
    assert.equal(setup.roles.oracle, 1);
    assert.equal(defaultModeSetup().roles.oracle, 1);
    assert.deepEqual(rosterTotals(draft.roles), { town: 4, cult: 1, total: 5 });
});

await test('custom setup rejects missing factions, invalid counts and excess seats', () => {
    const setup = { ...defaultModeSetup(), mode: 'custom' };
    for (const roles of [
        { oracle: 3 },
        { acolyte: 3 },
        { oracle: 1, acolyte: 1 },
        { oracle: 15, acolyte: 1 },
        { oracle: 2.5, acolyte: 1 },
        { oracle: Number.NaN, acolyte: 1 },
        { oracle: 0, acolyte: 3 },
    ])
        assert.ok(customModeError({ ...setup, roles }));
    assert.equal(
        customModeError({
            ...setup,
            roles: { tracker: 2, herbalist: 2, phantasm: 1, acolyte: 1 },
        }),
        null,
    );
    assert.equal(
        customModeError({ ...setup, mode: 'classic', roles: {} }),
        null,
    );
});

await test('mode names preserve classic legacy rooms and distinguish chaos variants', () => {
    assert.equal(modeName(), 'Classic');
    assert.equal(modeName(undefined, 'illusions'), 'Classic · Illusions');
    assert.equal(modeName({ ...defaultModeSetup(), mode: 'hard' }), 'Hard');
    assert.equal(
        modeName({ ...defaultModeSetup(), mode: 'paranoia' }),
        'Paranoia',
    );
    assert.equal(
        modeName({
            ...defaultModeSetup(),
            mode: 'chaos',
            chaos_variant: 'maelstrom',
        }),
        'Chaos · Maelstrom',
    );
});

await test('custom rosters accept fifteen seats and respect a lower server limit', () => {
    const setup = {
        ...defaultModeSetup(),
        mode: 'custom',
        roles: { oracle: 1, townsperson: 9, acolyte: 5 },
    };
    assert.equal(customModeError(setup), null);
    assert.ok(customModeError(setup, 3, 10));
    assert.ok(customModeError({ ...setup, roles: { ...setup.roles, oracle: 2 } }));
});

await test('preset submissions discard inactive custom errors without mutating the saved draft', () => {
    const setup = {
        ...defaultModeSetup(),
        mode: 'hard',
        roles: { oracle: Number.NaN, acolyte: 20 },
    };
    assert.deepEqual(modeSubmission(setup).roles, {});
    assert.equal(setup.roles.acolyte, 20);
    const custom = { ...defaultModeSetup(), mode: 'custom' };
    const payload = modeSubmission(custom);
    payload.roles.oracle = 4;
    assert.equal(custom.roles.oracle, 1);
});
