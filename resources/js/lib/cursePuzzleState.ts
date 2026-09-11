import type { CurseChallenge } from './chanting';

export function ringTurn(ring: { options: string[] }, id?: string): number {
    return Math.max(0, ring.options.indexOf(id ?? ''));
}

export function ringDirection(start: number, turn: number): string {
    return ['North', 'East', 'South', 'West'][(start + turn) % 4];
}

/** Full ring state is always submitted in scene order, never click order. */
export function rotateCurseRing(
    challenge: CurseChallenge,
    answer: string[],
    index: number,
): string[] {
    const rings = challenge.scene?.rings ?? [];
    if (!rings[index]) return answer;
    return rings.map((ring, i) => {
        const turn = ringTurn(ring, answer[i]);
        return ring.options[i === index ? (turn + 1) % 4 : turn];
    });
}

export function selectCurseObject(
    challenge: CurseChallenge,
    answer: string[],
    id: string,
): string[] {
    if (
        answer.length >= challenge.answer_length ||
        answer.includes(id) ||
        !challenge.options.some((option) => option.id === id)
    ) return answer;
    return [...answer, id];
}
