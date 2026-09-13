import assert from 'node:assert/strict';
import test from 'node:test';
import { faeRecipients } from '../../resources/js/lib/faeCourt.ts';
import { actionConsequence } from '../../resources/js/lib/gameUx.ts';

const state = {
    phase: 'night',
    me: {
        id: 'collector',
        role: 'fae_collector',
        alignment: 'fae',
        allies: [{ id: 'broker' }],
    },
    players: [
        'collector',
        'broker',
        'old',
        'promised',
        'fulfilled',
        'fresh',
        'dead',
    ].map((id) => ({ id, name: id, alive: id !== 'dead' })),
    fae: {
        collector_used: false,
        renewable_ids: ['failed'],
        bargains: [
            {
                id: 'failed',
                kind: 'passage',
                recipient_id: 'old',
                promise_target: 'promised',
                status: 'broken',
            },
            { id: 'success', recipient_id: 'fulfilled', status: 'fulfilled' },
        ],
    },
};

test('renewal targets exclude teammates, the old partner, promised target, dead and fulfilled partners', () => {
    assert.deepEqual(
        faeRecipients(state, 'failed').map((p) => p.id),
        ['fresh'],
    );
    assert.deepEqual(faeRecipients(state), []);
    assert.deepEqual(faeRecipients(state, 'success'), []);
    assert.deepEqual(
        faeRecipients(
            { ...state, fae: { ...state.fae, collector_used: true } },
            'failed',
        ),
        [],
    );
});

test('renewal confirmation explains the retained terms and spent charge', () => {
    const text = actionConsequence(state, {
        target: 'fresh',
        useAbility: false,
    });
    assert.match(text, /original terms/);
    assert.match(text, /spent even if disrupted or voided/);
    assert.doesNotMatch(text, /Chant/);
});
