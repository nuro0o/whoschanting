export type PracticeRole = 'counterfeiter' | 'tracker' | 'bellkeeper';
export type PracticeScenario = 'oracle' | PracticeRole;
export type Alignment = 'town' | 'cult';
export type TrackingTarget = 'mara' | 'ivo' | 'nell';

export const practiceScenarios = [
    {
        id: 'oracle',
        name: 'The Oracle',
        faction: 'Town',
        title: 'Learn your first round',
        description: 'Investigate, question a clue, clear a curse and vote.',
        length: '6 guided steps',
    },
    {
        id: 'counterfeiter',
        name: 'The Counterfeiter',
        faction: 'Cult',
        title: 'Write a different truth',
        description:
            'Choose an apparent alignment and see what the Oracle reads.',
        length: 'One night',
    },
    {
        id: 'tracker',
        name: 'The Tracker',
        faction: 'Town',
        title: 'Follow the footsteps',
        description: 'Separate an attempted visit from a successful action.',
        length: 'One night',
    },
    {
        id: 'bellkeeper',
        name: 'The Bellkeeper',
        faction: 'Town',
        title: 'One bell. One chance.',
        description:
            'Decide when to spend your only warning before the ritual fills.',
        length: 'One night',
    },
] as const;

export const trackingPlayers = [
    { id: 'mara', name: 'Mara', character: 'botanist' },
    { id: 'ivo', name: 'Ivo', character: 'baker' },
    { id: 'nell', name: 'Nell', character: 'mariner' },
] as const;

// Scripted exercises mirror MatchEngine's reading, tracking and bell resolution.
export function resolvePracticeForgery(alignment: Alignment) {
    return {
        apparent: alignment,
        actual: 'town' as const,
        abilitySpent: true,
        chanting: false,
    };
}

export function resolvePracticeTracking(target: TrackingTarget) {
    const attempted =
        target === 'mara' ? 'Nell' : target === 'ivo' ? 'Bram' : null;
    const concealed = target === 'ivo';
    return {
        attempted,
        visible: concealed ? null : attempted,
        concealed,
        disrupted: target === 'mara',
    };
}

export function resolvePracticeBell(
    ring: boolean,
    earned: 0 | 1 | 2,
    disrupted = false,
) {
    const prevented = ring && !disrupted && earned > 0 ? 1 : 0;
    const tokens = 5 + earned - prevented;
    return { prevented, tokens, finalVote: tokens >= 6, abilitySpent: ring };
}

export interface RolePracticeState {
    role: PracticeRole;
    step: 'choose' | 'reveal' | 'complete';
    choice: string | null;
    earned: 0 | 1 | 2;
    feedback: string;
}

export type RolePracticeAction =
    | { type: 'choose'; choice: string }
    | { type: 'set-earned'; earned: 0 | 1 | 2 }
    | { type: 'confirm' }
    | { type: 'interpret'; certain: boolean }
    | { type: 'restart' };

export function createRolePractice(role: PracticeRole): RolePracticeState {
    return { role, step: 'choose', choice: null, earned: 1, feedback: '' };
}

export function rolePracticeReducer(
    state: RolePracticeState,
    action: RolePracticeAction,
): RolePracticeState {
    if (action.type === 'restart') return createRolePractice(state.role);
    if (
        action.type === 'interpret' &&
        state.role === 'tracker' &&
        state.step === 'reveal'
    ) {
        return action.certain
            ? {
                  ...state,
                  feedback:
                      'Try again. Tracks show attempted targeting, not an ability, allegiance or success. Concealment can hide a real visit.',
              }
            : {
                  ...state,
                  step: 'complete',
                  feedback:
                      'Exactly. Compare the track with other evidence before deciding whom to trust.',
              };
    }
    if (state.step !== 'choose') return state;
    if (
        action.type === 'set-earned' &&
        state.role === 'bellkeeper' &&
        [0, 1, 2].includes(action.earned)
    ) {
        return { ...state, earned: action.earned };
    }
    if (action.type === 'choose') {
        const choices =
            state.role === 'counterfeiter'
                ? ['town', 'cult']
                : state.role === 'tracker'
                  ? ['mara', 'ivo', 'nell']
                  : ['ring', 'save'];
        return choices.includes(action.choice)
            ? { ...state, choice: action.choice }
            : state;
    }
    if (action.type === 'confirm' && state.choice)
        return { ...state, step: 'reveal' };
    return state;
}
