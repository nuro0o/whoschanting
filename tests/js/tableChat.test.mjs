import assert from 'node:assert/strict';
import test from 'node:test';
import {
    createTableChat,
    placeTableChatBubble,
    wrapTableChat,
} from '../../resources/js/lib/tableChat.ts';

const epoch = Date.parse('2026-09-11T12:00:00Z');

test('placement keeps the dial and portraits clear, including at compact edges', () => {
    const viewport = { width: 390, height: 230 };
    const dial = { x: 157, y: 83, width: 76, height: 64 };
    const first = placeTableChatBubble(
        { x: 195, y: 190 },
        { width: 184, height: 60 },
        viewport,
        [dial],
    );
    assert.ok(first);
    assert.ok(first.x >= 8 && first.y >= 8);
    assert.ok(first.x + first.width <= 382 && first.y + first.height <= 222);
    assert.ok(
        first.y + first.height <= dial.y ||
            first.y >= dial.y + dial.height ||
            first.x + first.width <= dial.x ||
            first.x >= dial.x + dial.width,
    );
    const portrait = { x: 40, y: 20, width: 96, height: 108 };
    const second = placeTableChatBubble(
        { x: 88, y: 20 },
        { width: 194, height: 85 },
        { width: 900, height: 400 },
        [portrait],
    );
    assert.ok(second);
    assert.ok(
        second.x >= portrait.x + portrait.width ||
            second.y >= portrait.y + portrait.height,
    );
    assert.equal(
        placeTableChatBubble(
            { x: 50, y: 50 },
            { width: 194, height: 85 },
            { width: 100, height: 100 },
            [],
        ),
        null,
    );
});
const players = ['a', 'b', 'c', 'd'].map((id) => ({ id, name: 'Same name' }));
const message = (
    id,
    player_id = 'a',
    age = 0,
    body = 'Who lit that candle?',
) => ({
    id,
    player_id,
    name: 'Untrusted name',
    body,
    day: 1,
    sent_at: new Date(epoch - age).toISOString(),
});
const update = (
    chat,
    messages,
    suppressed = false,
    now = 1000,
    roster = players,
) =>
    chat.update(
        messages,
        roster,
        new Date(epoch).toISOString(),
        suppressed,
        now,
    );

test('initial history stays silent; only authoritative sender IDs identify seats', () => {
    const chat = createTableChat();
    assert.deepEqual(update(chat, [message('history')]), []);
    const result = update(chat, [message('history'), message('new', 'b')]);
    assert.equal(result.length, 1);
    assert.equal(result[0].playerId, 'b');
    assert.equal(result[0].name, 'Same name');
    assert.equal(
        update(chat, [
            message('unknown', 'missing'),
            { ...message('legacy'), player_id: undefined },
        ]).length,
        1,
    );
});

test('polls never extend expiry or replay a message after timeout', () => {
    const chat = createTableChat();
    update(chat, []);
    const first = update(chat, [message('one')]);
    assert.equal(
        update(chat, [message('one')], false, 9000)[0].expiresAt,
        first[0].expiresAt,
    );
    assert.deepEqual(chat.expire(first[0].expiresAt), []);
    assert.deepEqual(update(chat, [message('one')], false, 50000), []);
});

test('an empty transcript clears active bubbles when the room resets', () => {
    const chat = createTableChat();
    update(chat, []);
    assert.equal(update(chat, [message('last-round')]).length, 1);
    assert.deepEqual(update(chat, []), []);
    assert.equal(update(chat, [message('new-round')]).length, 1);
});

test('latest message replaces its speaker and at most three speakers remain', () => {
    const chat = createTableChat();
    update(chat, []);
    const result = update(chat, [
        message('a'),
        message('b', 'b'),
        message('c', 'c'),
        message('d', 'd'),
        message('new-b', 'b'),
    ]);
    assert.deepEqual(
        result.map((bubble) => bubble.id),
        ['c', 'd', 'new-b'],
    );
    assert.deepEqual(update(chat, [], false, 1001, players.slice(0, 1)), []);
});

test('mist clears visible text and consumes suppressed messages without replay on release', () => {
    const chat = createTableChat();
    update(chat, []);
    assert.equal(update(chat, [message('clear')]).length, 1);
    assert.deepEqual(
        update(chat, [message('clear'), message('fog')], true),
        [],
    );
    assert.deepEqual(update(chat, [message('clear'), message('fog')]), []);
    assert.equal(update(chat, [message('after-fog')]).length, 1);
});

test('server age limits reconnect backlog independently of the local clock', () => {
    const chat = createTableChat();
    update(chat, []);
    const result = update(
        chat,
        [
            message('old', 'a', 19000),
            message('recent', 'b', 11000),
            { ...message('invalid'), sent_at: 'bad' },
        ],
        false,
        99999999,
    );
    assert.deepEqual(
        result.map((bubble) => bubble.id),
        ['recent'],
    );
    assert.ok(result[0].expiresAt > 99999999);
    assert.ok(result[0].expiresAt < 99999999 + 2000);
    assert.deepEqual(chat.expire(99999999 + 18000), []);
});

test('plain text wrapping bounds long words, preserves emoji, and truncates visibly', () => {
    const measure = (text) => Array.from(text).length;
    const lines = wrapTableChat(
        '🕯️'.repeat(20) + '<script>alert(1)</script>',
        measure,
        10,
        3,
    );
    assert.equal(lines.length, 3);
    assert.ok(lines.every((line) => measure(line) <= 10));
    assert.ok(lines[2].endsWith('…'));
    assert.ok(!lines.join('').includes('\uFFFD'));
    assert.deepEqual(wrapTableChat('one two three', measure, 8, 3), [
        'one two',
        'three',
    ]);
    assert.deepEqual(wrapTableChat('   ', measure, 8, 3), []);
});
