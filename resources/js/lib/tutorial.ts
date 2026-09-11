export const tutorialPlayers = [
    { id: 'you', name: 'You', character: 'astronomer' },
    { id: 'mara', name: 'Mara', character: 'botanist' },
    { id: 'nell', name: 'Nell', character: 'mariner' },
    { id: 'ivo', name: 'Ivo', character: 'baker' },
    { id: 'bram', name: 'Bram', character: 'lamplighter' },
] as const;

export type TutorialTarget = 'mara' | 'nell' | 'ivo' | 'bram';
export type TutorialVote = TutorialTarget | 'abstain';
export type TutorialStep =
    | 'welcome'
    | 'night'
    | 'evidence'
    | 'curse'
    | 'vote'
    | 'recap';

export const tutorialSeals = [
    { tokens: ['3', 'fog', '1', '2'], answer: ['1', '2', '3'] },
    { tokens: ['8', '4', 'sea', '6'], answer: ['4', '6', '8'] },
    { tokens: ['12', 'tide', '7', '10'], answer: ['7', '10', '12'] },
];

export interface TutorialState {
    step: TutorialStep;
    investigationTarget: TutorialTarget | null;
    investigated: boolean;
    evidenceUnderstood: boolean;
    sealIndex: number;
    lanterns: string[];
    curseSolved: boolean;
    voteTarget: TutorialVote | null;
    feedback: string;
}

export type TutorialAction =
    | {
          type:
              | 'begin'
              | 'confirm-investigation'
              | 'continue-evidence'
              | 'continue-curse'
              | 'confirm-vote'
              | 'restart';
      }
    | { type: 'select-investigation'; target: TutorialTarget }
    | { type: 'answer-evidence'; certain: boolean }
    | { type: 'choose-lantern'; token: string }
    | { type: 'select-vote'; target: TutorialVote };

export function createTutorialState(): TutorialState {
    return {
        step: 'welcome',
        investigationTarget: null,
        investigated: false,
        evidenceUnderstood: false,
        sealIndex: 0,
        lanterns: [],
        curseSolved: false,
        voteTarget: null,
        feedback: '',
    };
}

const validTargets: readonly string[] = ['mara', 'nell', 'ivo', 'bram'];

export function resolveTutorialVote(vote: TutorialVote) {
    const ballots: { voter: string; target: TutorialVote }[] = [
        { voter: 'You', target: vote },
        { voter: 'Mara', target: 'ivo' },
        { voter: 'Nell', target: 'ivo' },
        { voter: 'Ivo', target: 'mara' },
        { voter: 'Bram', target: 'mara' },
    ];
    const counts: Record<TutorialVote, number> = {
        mara: 0,
        nell: 0,
        ivo: 0,
        bram: 0,
        abstain: 0,
    };
    for (const ballot of ballots) counts[ballot.target]++;
    const highest = Math.max(...Object.values(counts));
    const leaders = Object.keys(counts).filter(
        (target) => counts[target as TutorialVote] === highest,
    ) as TutorialVote[];
    const winner = leaders.length === 1 ? leaders[0] : null;
    const banished: TutorialTarget | null =
        winner && winner !== 'abstain' ? winner : null;
    return { ballots, counts, banished };
}

/** Local guided exercise: no timers, persistence, or real match actions. */
export function tutorialReducer(
    state: TutorialState,
    action: TutorialAction,
): TutorialState {
    if (action.type === 'restart') return createTutorialState();
    switch (action.type) {
        case 'begin':
            return state.step === 'welcome'
                ? { ...state, step: 'night', feedback: '' }
                : state;
        case 'select-investigation':
            if (state.step !== 'night' || state.investigated) return state;
            return action.target === 'mara'
                ? {
                      ...state,
                      investigationTarget: 'mara',
                      feedback:
                          'Mara selected. Confirm to use your once-per-match investigation.',
                  }
                : {
                      ...state,
                      feedback:
                          'For this guided night, select Mara. In a real match, you choose whom to investigate.',
                  };
        case 'confirm-investigation':
            return state.step === 'night' &&
                state.investigationTarget === 'mara' &&
                !state.investigated
                ? {
                      ...state,
                      investigated: true,
                      step: 'evidence',
                      feedback: '',
                  }
                : state;
        case 'answer-evidence':
            if (state.step !== 'evidence' || state.evidenceUnderstood)
                return state;
            return {
                ...state,
                evidenceUnderstood: !action.certain,
                feedback: action.certain
                    ? 'Not necessarily. A Veilweaver can reverse an Oracle reading. Try the other answer.'
                    : 'Exactly. The reading is a clue, not proof. A veil can make Town appear Cult, or Cult appear Town.',
            };
        case 'continue-evidence':
            return state.step === 'evidence' && state.evidenceUnderstood
                ? { ...state, step: 'curse', feedback: '' }
                : state;
        case 'choose-lantern': {
            if (state.step !== 'curse' || state.curseSolved) return state;
            const seal = tutorialSeals[state.sealIndex];
            if (
                !seal ||
                !seal.tokens.includes(action.token) ||
                state.lanterns.includes(action.token)
            )
                return state;
            if (action.token !== seal.answer[state.lanterns.length]) {
                return {
                    ...state,
                    lanterns: [],
                    feedback:
                        'The mist returns to this seal. Choose only numbers, from smallest to largest. Earlier seals stay solved.',
                };
            }
            const lanterns = [...state.lanterns, action.token];
            if (lanterns.length < seal.answer.length)
                return {
                    ...state,
                    lanterns,
                    feedback: `${action.token} lit. Find the next larger number.`,
                };
            if (state.sealIndex === tutorialSeals.length - 1)
                return {
                    ...state,
                    lanterns,
                    curseSolved: true,
                    feedback:
                        'All three seals are clear. Your voice and actions are restored.',
                };
            return {
                ...state,
                sealIndex: state.sealIndex + 1,
                lanterns: [],
                feedback: `Seal ${state.sealIndex + 1} cleared. Start the next seal with its smallest number.`,
            };
        }
        case 'continue-curse':
            return state.step === 'curse' && state.curseSolved
                ? { ...state, step: 'vote', feedback: '' }
                : state;
        case 'select-vote':
            return state.step === 'vote' &&
                (validTargets.includes(action.target) ||
                    action.target === 'abstain')
                ? {
                      ...state,
                      voteTarget: action.target,
                      feedback: `${action.target === 'abstain' ? 'Abstention' : tutorialPlayers.find((player) => player.id === action.target)?.name} selected. Your ballot is not submitted until you confirm.`,
                  }
                : state;
        case 'confirm-vote':
            return state.step === 'vote' && state.voteTarget !== null
                ? { ...state, step: 'recap', feedback: '' }
                : state;
    }
}
