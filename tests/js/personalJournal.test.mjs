import assert from 'node:assert/strict';
import test from 'node:test';
import {
    groupJournalResults,
    journalStorageKey,
} from '../../resources/js/lib/personalJournal.ts';

await test('notes remain scoped to the same room, seat and match across reloads', () => {
    const current = journalStorageKey(1, 'player-a', 'match-a');
    assert.equal(current, journalStorageKey(1, 'player-a', 'match-a'));
    assert.notEqual(current, journalStorageKey(2, 'player-a', 'match-a'));
    assert.notEqual(current, journalStorageKey(1, 'player-b', 'match-a'));
    assert.notEqual(current, journalStorageKey(1, 'player-a', 'match-b'));
    assert.equal(journalStorageKey(1, 'player-a', null), null);
    assert.equal(journalStorageKey(1, 'player-a', undefined), null);
    assert.notEqual(
        journalStorageKey(1, 'a:b', 'c'),
        journalStorageKey(1, 'a', 'b:c'),
    );
});

await test('journal orders cumulative results newest first without changing server history', () => {
    const results = [
        { day: 1, target: 'Mara', alignment: 'town' },
        { day: 3, target: 'Rowan', kind: 'tracking', visited_target: null },
        { day: 2, target: 'Silas', kind: 'disrupted' },
        { day: 3, target: 'Elowen', kind: 'exorcism' },
        { day: 3, target: 'Mara', kind: 'oath', kept: true },
    ];
    const original = structuredClone(results);
    const groups = groupJournalResults(results);
    assert.deepEqual(
        groups.map(({ day }) => day),
        [3, 2, 1],
    );
    assert.deepEqual(
        groups[0].entries.map(({ index }) => index),
        [4, 3, 1],
    );
    assert.equal(groups[0].entries[0].result, results[4]);
    assert.deepEqual(results, original);
    assert.deepEqual(groupJournalResults([]), []);
});
