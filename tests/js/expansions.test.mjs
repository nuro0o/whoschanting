import assert from 'node:assert/strict';
import test from 'node:test';
import {
    expansionTargets,
    victoryTitle,
} from '../../resources/js/lib/expansions.ts';
import { actionConsequence } from '../../resources/js/lib/gameUx.ts';
import { privateResultText } from '../../resources/js/lib/roleActions.ts';
import { journalResultLabel } from '../../resources/js/lib/personalJournal.ts';

const state = {
    phase: 'night',
    me: {
        id: 'me',
        role: 'drowned_tidecaller',
        alignment: 'drowned',
        allies: [{ id: 'ally' }],
    },
    players: [
        { id: 'me', name: 'You', alive: true },
        { id: 'ally', name: 'Ada', alive: true },
        { id: 'marked', name: 'Mara', alive: true },
        { id: 'clear', name: 'Rowan', alive: true },
        { id: 'dead', name: 'Ghost', alive: false },
    ],
    expansion: { marks: ['marked', 'dead'], night_choices: [] },
};

test('Drowned targets exclude allies, existing marks and departed seats; ferry selects existing living marks', () => {
    assert.deepEqual(
        expansionTargets(state, { id: 'mark', target: 'other' }).map(
            (p) => p.id,
        ),
        ['clear'],
    );
    assert.deepEqual(
        expansionTargets(state, { id: 'ferry', target: 'other' }).map(
            (p) => p.id,
        ),
        ['marked'],
    );
    assert.deepEqual(
        expansionTargets(state, { id: 'sound', target: 'living' }).map(
            (p) => p.id,
        ),
        ['me', 'ally', 'marked', 'clear'],
    );
    assert.deepEqual(
        expansionTargets(state, { id: 'locate', target: 'none' }),
        [],
    );
});

test('an expansion action replaces the Cult curse consequence and explains relic destination without inventing success', () => {
    const choice = {
        id: 'give',
        label: 'Hand over a relic',
        description: 'Conflicting moves fail.',
        target: 'other',
        relic: 'owned',
    };
    const text = actionConsequence(
        {
            ...state,
            me: { ...state.me, role: 'acolyte', alignment: 'cult' },
            expansion: { ...state.expansion, night_choices: [choice] },
        },
        {
            target: 'clear',
            useAbility: false,
            curse: 'puzzle',
            forgedAlignment: 'cult',
            expansionAction: 'give',
            relicId: 'silver_key',
        },
    );
    assert.match(text, /Hand over a relic: Rowan · Silver Key/);
    assert.match(text, /replaces your usual night ability or chant/);
    assert.doesNotMatch(text, /attempt to curse|successfully|Chant and/);
});

test('targeted expansion choices require clearing Misdirection; zero-target choices remain usable', () => {
    const choices = [
        { id: 'sound', label: 'Sound', target: 'living', description: '' },
        { id: 'siphon', label: 'Siphon', target: 'none', description: '' },
    ];
    const cursed = {
        ...state,
        me: { ...state.me, curse: { type: 'misdirection' } },
        expansion: { ...state.expansion, night_choices: choices },
    };
    const action = {
        target: 'clear',
        useAbility: false,
        curse: 'puzzle',
        forgedAlignment: 'cult',
        expansionAction: 'sound',
    };
    assert.match(actionConsequence(cursed, action), /Break Misdirection/);
    assert.doesNotMatch(
        actionConsequence(cursed, {
            ...action,
            target: null,
            expansionAction: 'siphon',
        }),
        /Break Misdirection|redirect/,
    );
});

test('shared victories name each faction and private expansion results remain literal text', () => {
    assert.equal(
        victoryTitle(['cult', 'gilded'], 'cult'),
        'Cult and The Gilded Hand share victory',
    );
    assert.equal(
        victoryTitle(['town', 'choir'], 'town'),
        'Town and The Hollow Choir share victory',
    );
    const result = {
        kind: 'expansion',
        day: 2,
        target: '',
        text: '<Rowan> carries no Drowned mark.',
    };
    assert.equal(privateResultText(result), result.text);
    assert.equal(journalResultLabel(result), 'Expansion result');
});
