import assert from 'node:assert/strict';
import test from 'node:test';
import {
    createTutorialState,
    resolveTutorialVote,
    tutorialReducer,
} from '../../resources/js/lib/tutorial.ts';

function reachEvidence() {
    let state = tutorialReducer(createTutorialState(), { type: 'begin' });
    state = tutorialReducer(state, {
        type: 'select-investigation',
        target: 'mara',
    });
    return tutorialReducer(state, { type: 'confirm-investigation' });
}

function reachCurse() {
    const state = tutorialReducer(reachEvidence(), {
        type: 'answer-evidence',
        certain: false,
    });
    return tutorialReducer(state, { type: 'continue-evidence' });
}

function light(state, tokens) {
    return tokens.reduce(
        (current, token) =>
            tutorialReducer(current, { type: 'choose-lantern', token }),
        state,
    );
}

function reachVote() {
    const state = light(reachCurse(), [
        '1',
        '2',
        '3',
        '4',
        '6',
        '8',
        '7',
        '10',
        '12',
    ]);
    return tutorialReducer(state, { type: 'continue-curse' });
}

await test('investigation requires selecting the guided target and explicit confirmation', () => {
    const welcome = createTutorialState();
    assert.deepEqual(
        tutorialReducer(welcome, { type: 'confirm-investigation' }),
        welcome,
    );
    const night = tutorialReducer(welcome, { type: 'begin' });
    assert.equal(
        tutorialReducer(night, { type: 'select-investigation', target: 'ivo' })
            .investigationTarget,
        null,
    );
    assert.deepEqual(
        tutorialReducer(night, { type: 'confirm-investigation' }),
        night,
    );
    const selected = tutorialReducer(night, {
        type: 'select-investigation',
        target: 'mara',
    });
    assert.equal(selected.step, 'night');
    assert.equal(selected.investigated, false);
    assert.equal(night.investigationTarget, null);
    const evidence = tutorialReducer(selected, {
        type: 'confirm-investigation',
    });
    assert.equal(evidence.step, 'evidence');
    assert.equal(evidence.investigated, true);
    assert.deepEqual(
        tutorialReducer(evidence, { type: 'confirm-investigation' }),
        evidence,
    );
});

await test('a mistaken interpretation can be corrected before progressing', () => {
    const wrong = tutorialReducer(reachEvidence(), {
        type: 'answer-evidence',
        certain: true,
    });
    assert.equal(wrong.evidenceUnderstood, false);
    assert.equal(
        tutorialReducer(wrong, { type: 'continue-evidence' }).step,
        'evidence',
    );
    const corrected = tutorialReducer(wrong, {
        type: 'answer-evidence',
        certain: false,
    });
    assert.equal(
        tutorialReducer(corrected, { type: 'continue-evidence' }).step,
        'curse',
    );
});

await test('curse mistakes reset only the current seal and cannot bypass the puzzle', () => {
    let state = reachCurse();
    assert.equal(
        tutorialReducer(state, { type: 'continue-curse' }).step,
        'curse',
    );
    state = light(state, ['1', '2', '3', '4']);
    assert.equal(state.sealIndex, 1);
    const wrong = light(state, ['sea']);
    assert.equal(wrong.sealIndex, 1);
    assert.deepEqual(wrong.lanterns, []);
    assert.equal(wrong.curseSolved, false);
    assert.deepEqual(light(wrong, ['unknown']), wrong);
    const first = light(wrong, ['4']);
    assert.deepEqual(light(first, ['4']), first);
    const solved = light(first, ['6', '8', '7', '10', '12']);
    assert.equal(solved.curseSolved, true);
    assert.equal(
        tutorialReducer(solved, { type: 'continue-curse' }).step,
        'vote',
    );
});

await test('lantern ordering is numeric, including two-digit labels', () => {
    let state = light(reachCurse(), ['1', '2', '3', '4', '6', '8']);
    state = light(state, ['10']);
    assert.deepEqual(state.lanterns, []);
    assert.equal(state.curseSolved, false);
    assert.equal(light(state, ['7', '10', '12']).curseSolved, true);
});

await test('a vote can be changed before confirming, and cannot change after submission', () => {
    const vote = reachVote();
    assert.equal(vote.step, 'vote');
    assert.deepEqual(tutorialReducer(vote, { type: 'confirm-vote' }), vote);
    assert.equal(
        tutorialReducer(vote, { type: 'select-vote', target: 'you' })
            .voteTarget,
        null,
    );
    let selected = tutorialReducer(vote, {
        type: 'select-vote',
        target: 'mara',
    });
    selected = tutorialReducer(selected, {
        type: 'select-vote',
        target: 'ivo',
    });
    assert.equal(selected.step, 'vote');
    const recap = tutorialReducer(selected, { type: 'confirm-vote' });
    assert.equal(recap.step, 'recap');
    assert.equal(recap.voteTarget, 'ivo');
    assert.deepEqual(
        tutorialReducer(recap, { type: 'select-vote', target: 'mara' }),
        recap,
    );
    assert.deepEqual(tutorialReducer(recap, { type: 'confirm-vote' }), recap);
});

await test('scripted ballots handle both banishments, third-party votes and abstention', () => {
    for (const target of ['mara', 'ivo']) {
        const outcome = resolveTutorialVote(target);
        assert.equal(outcome.banished, target);
        assert.equal(outcome.counts[target], 3);
        assert.equal(outcome.ballots.length, 5);
    }
    for (const target of ['nell', 'bram', 'abstain']) {
        const outcome = resolveTutorialVote(target);
        assert.equal(outcome.banished, null);
        assert.equal(outcome.counts.mara, 2);
        assert.equal(outcome.counts.ivo, 2);
        assert.equal(outcome.counts[target], 1);
    }
});

await test('restart clears investigation, curse progress, feedback and ballot from any stage', () => {
    for (const state of [
        reachEvidence(),
        light(reachCurse(), ['1', '2', '3']),
        tutorialReducer(reachVote(), {
            type: 'select-vote',
            target: 'abstain',
        }),
    ]) {
        assert.deepEqual(
            tutorialReducer(state, { type: 'restart' }),
            createTutorialState(),
        );
    }
});
