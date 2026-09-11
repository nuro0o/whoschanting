import assert from 'node:assert/strict';
import test from 'node:test';
import {
    decodeSuspicionBoard,
    emptySuspicionEntry,
    linkedSuspicionResults,
    MAX_SUSPICION_NOTE_LENGTH,
    serializeSuspicionBoard,
    suspicionBoardStorageKey,
    updateSuspicionEntry,
} from '../../resources/js/lib/suspicionBoard.ts';
import { journalStorageKey } from '../../resources/js/lib/personalJournal.ts';

const playerIds = ['mara-id', 'ivo-id'];
const roleIds = ['oracle', 'warden', 'veilweaver'];
const results = [
    { day: 1, target: 'Mara', alignment: 'cult' },
    { day: 2, target: 'Ivo', kind: 'visits', visited: true },
    { day: 3, target: 'Mara', kind: 'tracking', visited_target: 'Ivo' },
];
const decode = (raw) =>
    decodeSuspicionBoard(raw, playerIds, roleIds, results.length);

await test('storage isolates viewer, match and room without overwriting the existing journal', () => {
    const key = suspicionBoardStorageKey(4, 'viewer-a', 'match-a');
    assert.equal(key, suspicionBoardStorageKey(4, 'viewer-a', 'match-a'));
    assert.notEqual(key, suspicionBoardStorageKey(5, 'viewer-a', 'match-a'));
    assert.notEqual(key, suspicionBoardStorageKey(4, 'viewer-b', 'match-a'));
    assert.notEqual(key, suspicionBoardStorageKey(4, 'viewer-a', 'match-b'));
    assert.notEqual(key, journalStorageKey(4, 'viewer-a', 'match-a'));
    assert.notEqual(
        suspicionBoardStorageKey(4, 'a:b', 'c'),
        suspicionBoardStorageKey(4, 'a', 'b:c'),
    );
    for (const matchId of [null, undefined, ''])
        assert.equal(suspicionBoardStorageKey(4, 'a', matchId), null);
});

await test('per-player edits round-trip with independent trust, claims, notes and evidence', () => {
    let board = updateSuspicionEntry({}, 'mara-id', {
        stance: 'trusted',
        claimedRole: 'warden',
        note: 'Ask about night two.',
        resultIndices: [2, 0],
    });
    board = updateSuspicionEntry(board, 'ivo-id', {
        stance: 'suspicious',
        note: 'Changing story.',
    });
    const loaded = decode(serializeSuspicionBoard(board));
    assert.equal(loaded.invalid, false);
    assert.deepEqual(loaded.entries, board);
    assert.equal(loaded.entries['mara-id'].stance, 'trusted');
    assert.equal(loaded.entries['mara-id'].claimedRole, 'warden');
    assert.equal(loaded.entries['ivo-id'].claimedRole, null);
});

await test('result links use original indices and do not convert apparent alignments into judgments', () => {
    const entry = {
        ...emptySuspicionEntry(),
        resultIndices: [0, 2, 0, -1, 7, 1.5],
    };
    const linked = linkedSuspicionResults(entry, results);
    assert.deepEqual(
        linked.map(({ index }) => index),
        [2, 0],
    );
    assert.equal(linked[1].result, results[0]);
    assert.equal(entry.stance, 'unmarked');
    assert.equal(entry.claimedRole, null);
    assert.equal(results[0].alignment, 'cult');
    assert.deepEqual(entry.resultIndices, [0, 2, 0, -1, 7, 1.5]);
    assert.deepEqual(
        linkedSuspicionResults(entry, [
            ...results,
            { day: 4, target: 'Ivo', kind: 'disrupted' },
        ]).map(({ index }) => index),
        [2, 0],
    );
});

await test('unreadable or unsupported stored data can be reported without throwing', () => {
    assert.deepEqual(decode(null), { entries: {}, invalid: false });
    for (const raw of [
        'broken',
        '',
        'null',
        '[]',
        '{}',
        '{"version":2,"entries":{}}',
        '{"version":1,"entries":[]}',
        ' '.repeat(250001),
    ]) {
        assert.deepEqual(decode(raw), { entries: {}, invalid: true });
    }
});

await test('loading bounds untrusted storage and discards unknown seats, role labels and stale links', () => {
    const saved = JSON.stringify({
        version: 1,
        entries: {
            'mara-id': {
                stance: 'confirmed-cult',
                claimedRole: 'invented',
                note: 'x'.repeat(MAX_SUSPICION_NOTE_LENGTH + 20),
                resultIndices: [0, 0, 2, 3, -1, '1', 1.5],
                alignment: 'cult',
            },
            'ivo-id': null,
            outsider: { ...emptySuspicionEntry(), note: 'Not this match.' },
        },
    });
    const loaded = decode(saved);
    assert.equal(loaded.invalid, true);
    assert.deepEqual(Object.keys(loaded.entries), ['mara-id']);
    assert.equal(loaded.entries['mara-id'].stance, 'unmarked');
    assert.equal(loaded.entries['mara-id'].claimedRole, null);
    assert.equal(
        loaded.entries['mara-id'].note.length,
        MAX_SUSPICION_NOTE_LENGTH,
    );
    assert.deepEqual(loaded.entries['mara-id'].resultIndices, [0, 2]);
    assert.equal(Object.hasOwn(loaded.entries['mara-id'], 'alignment'), false);
});

await test('prototype-shaped storage keys never alter board or global prototypes', () => {
    const raw =
        '{"version":1,"entries":{"__proto__":{"polluted":true},"constructor":{"note":"bad"}}}';
    assert.deepEqual(decode(raw).entries, {});
    assert.equal({}.polluted, undefined);
    const edited = updateSuspicionEntry({}, '__proto__', {
        note: 'literal key',
    });
    assert.equal(Object.getPrototypeOf(edited), Object.prototype);
    assert.equal(Object.hasOwn(edited, '__proto__'), true);
    assert.equal(edited.__proto__.note, 'literal key');
});

await test('editing and removing links preserves other notes, claims and prior snapshots', () => {
    const indices = [0, 1];
    const original = updateSuspicionEntry({}, 'mara-id', {
        stance: 'trusted',
        note: 'My note',
        claimedRole: 'oracle',
        resultIndices: indices,
    });
    indices.push(2);
    assert.deepEqual(original['mara-id'].resultIndices, [0, 1]);
    const updated = updateSuspicionEntry(original, 'mara-id', {
        resultIndices: [1],
        stance: 'unmarked',
    });
    assert.equal(updated['mara-id'].note, 'My note');
    assert.equal(updated['mara-id'].claimedRole, 'oracle');
    assert.deepEqual(updated['mara-id'].resultIndices, [1]);
    assert.deepEqual(original['mara-id'].resultIndices, [0, 1]);
    assert.equal(original['mara-id'].stance, 'trusted');
    const clearedClaim = updateSuspicionEntry(updated, 'mara-id', {
        claimedRole: null,
    });
    assert.equal(clearedClaim['mara-id'].claimedRole, null);
    const blank = emptySuspicionEntry();
    blank.resultIndices.push(2);
    assert.deepEqual(emptySuspicionEntry().resultIndices, []);
});
