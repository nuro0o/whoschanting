export interface TableChatMessage {
    id: string;
    player_id?: string;
    sent_at?: string;
    name: string;
    body: string;
    day: number;
}

export interface TableChatBubble {
    id: string;
    playerId: string;
    name: string;
    body: string;
    expiresAt: number;
}

export interface TableChatRect {
    x: number;
    y: number;
    width: number;
    height: number;
}

/** Prefer above the head, then nearby free space; never cover another label. */
export function placeTableChatBubble(
    anchor: { x: number; y: number },
    size: { width: number; height: number },
    viewport: { width: number; height: number },
    obstacles: TableChatRect[],
): TableChatRect | null {
    const gap = 8;
    const preferred = {
        x: anchor.x - size.width / 2,
        y: anchor.y - size.height - gap,
    };
    const xs = [preferred.x, gap, viewport.width - size.width - gap];
    const ys = [preferred.y, gap, viewport.height - size.height - gap];
    for (const obstacle of obstacles) {
        xs.push(
            obstacle.x - size.width - gap,
            obstacle.x + obstacle.width + gap,
        );
        ys.push(
            obstacle.y - size.height - gap,
            obstacle.y + obstacle.height + gap,
        );
    }
    let best: TableChatRect | null = null;
    let bestScore = Infinity;
    for (const x of xs)
        for (const y of ys) {
            if (
                x < gap ||
                y < gap ||
                x + size.width > viewport.width - gap ||
                y + size.height > viewport.height - gap
            )
                continue;
            if (
                obstacles.some(
                    (rect) =>
                        x < rect.x + rect.width + 3 &&
                        x + size.width + 3 > rect.x &&
                        y < rect.y + rect.height + 3 &&
                        y + size.height + 3 > rect.y,
                )
            )
                continue;
            const score = (x - preferred.x) ** 2 + (y - preferred.y) ** 2;
            if (score < bestScore) {
                bestScore = score;
                best = { x, y, ...size };
            }
        }
    return best;
}

/** New public messages only. The regular chat remains the complete transcript. */
export function createTableChat() {
    let initialized = false;
    const seen = new Set<string>();
    let bubbles: TableChatBubble[] = [];
    function expire(now: number) {
        bubbles = bubbles.filter((bubble) => bubble.expiresAt > now);
        return [...bubbles];
    }
    return {
        expire,
        update(
            messages: TableChatMessage[],
            players: { id: string; name: string }[],
            serverTime: string,
            suppressed: boolean,
            now: number,
        ) {
            expire(now);
            const serverNow = Date.parse(serverTime);
            if (suppressed || !messages.length) bubbles = [];
            for (const message of messages) {
                if (seen.has(message.id)) continue;
                seen.add(message.id);
                if (!initialized || suppressed) continue;
                const player = players.find(
                    (entry) => entry.id === message.player_id,
                );
                const sentAt = Date.parse(message.sent_at ?? '');
                const age = serverNow - sentAt;
                // Never guess from display names, replay reconnect backlogs, or
                // trust the viewer's wall clock for a server timestamp.
                if (
                    !player ||
                    !Number.isFinite(age) ||
                    age < -2000 ||
                    age > 18000
                )
                    continue;
                const body = message.body.replace(/\s+/gu, ' ').trim();
                if (!body) continue;
                const duration = Math.min(
                    18000,
                    12000 + Array.from(body).length * 25,
                );
                const remaining = duration - Math.max(0, age);
                if (remaining <= 0) continue;
                bubbles = bubbles.filter(
                    (bubble) => bubble.playerId !== player.id,
                );
                bubbles.push({
                    id: message.id,
                    playerId: player.id,
                    name: player.name,
                    body,
                    expiresAt: now + remaining,
                });
                bubbles = bubbles.slice(-3);
            }
            initialized = true;
            // The server sends a bounded transcript; old ids remain protected
            // by their timestamp when they leave this bounded seen window.
            if (seen.size > 1000) {
                const recent = [...seen].slice(-500);
                seen.clear();
                recent.forEach((id) => seen.add(id));
            }
            bubbles = bubbles.filter((bubble) =>
                players.some((player) => player.id === bubble.playerId),
            );
            return [...bubbles];
        },
    };
}

/** Canvas wrapping splits by Unicode code points, including unbroken words. */
export function wrapTableChat(
    text: string,
    measure: (text: string) => number,
    width: number,
    maxLines: number,
) {
    const characters = Array.from(text.replace(/\s+/gu, ' ').trim());
    const lines: string[] = [];
    let line = '';
    for (let index = 0; index < characters.length; index++) {
        const character = characters[index];
        if (line && measure(line + character) > width) {
            if (lines.length === maxLines - 1) {
                while (line && measure(line + '…') > width)
                    line = Array.from(line).slice(0, -1).join('');
                lines.push(line.trimEnd() + '…');
                return lines;
            }
            const space = line.lastIndexOf(' ');
            if (space > 0 && character !== ' ') {
                lines.push(line.slice(0, space));
                line = line.slice(space + 1) + character;
            } else {
                lines.push(line.trimEnd());
                line = character.trimStart();
            }
        } else line += character;
    }
    if (line) lines.push(line.trimEnd());
    return lines;
}
