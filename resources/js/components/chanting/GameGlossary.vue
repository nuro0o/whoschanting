<script setup lang="ts">
const terms = [
    [
        'Role',
        'Your secret job and abilities for this match, shown on your private role card. It is assigned separately from your cosmetic character.',
    ],
    [
        'Phase',
        'The current part of the game: role reveal, night actions, daytime discussion, or voting. The timer shows when this part ends.',
    ],
    [
        'Alignment',
        'The side you belong to: town or cult. Your character’s appearance has nothing to do with your alignment.',
    ],
    [
        'Town',
        'The villagers trying to stop the ritual. The town wins by banishing every cultist.',
    ],
    [
        'Cult',
        'The hidden team working together to finish the ritual. They also win if no town players remain, or if the final two players are one cultist and one town player.',
    ],
    [
        'Oracle',
        'A town role. Investigates one other living player each night and privately learns their apparent alignment at dawn.',
    ],
    [
        'Townsperson',
        'A town role without a special night ability. Keeps watch, compares stories, and helps identify cultists through discussion and voting.',
    ],
    [
        'Veilweaver',
        'A cult role. Can chant while veiling and cursing one other living player. The veil reverses their apparent alignment for the Oracle tonight; a random curse takes hold at dawn.',
    ],
    [
        'Acolyte',
        'A cult role. Chants to advance the ritual when the shared mission condition is met, and can optionally curse one other living player. A random curse takes hold at dawn.',
    ],
    [
        'Veiled',
        'Disguised for one night: the Oracle reads a town player as cult, or a cult player as town. The player’s real side stays the same. Your own role card always tells the truth.',
    ],
    [
        'Eldritch curse',
        'A private affliction placed by a cultist. It takes hold at dawn and expires at the following dawn. The type is random; challenge difficulty grows with the ritual level. Being cursed does not reveal your alignment.',
    ],
    [
        'Puzzle curse',
        'Solve a fresh cipher, ordering puzzle, missing pattern, arithmetic puzzle, odd-one-out, or reversal. Until solved, you cannot target a player at night or in a vote. Untargeted actions and abstention remain available.',
    ],
    [
        'Mind mist',
        'Blurs the village scenery and turns chat into gibberish. Complete the ordered focus challenge to restore your view and read chat again. Action controls and the timer stay clear.',
    ],
    [
        'Misdirection',
        'Only appears at ritual level 3, from two-thirds progress. Your next chosen night or vote target changes once to another legal living player. Abstaining does not trigger it. It fades at the following dawn.',
    ],
    [
        'Chant',
        'A cultist’s night action. Each chanting cultist can add one step to the ritual if the shared mission condition is met.',
    ],
    [
        'Shared mission',
        'The cult’s private condition for making ritual progress. In larger gatherings, chant together, avoid investigation, or keep cultists safe in the previous vote. All cultists share the same mission. In a small gathering, the lone cultist only needs to chant.',
    ],
    [
        'Small gathering',
        'A match with 3 or 4 players: one cultist, one Oracle, and the remaining players are townspeople. The ritual takes 3 steps with 3 players, or 4 steps with 4. A submitted chant adds one step even when the Oracle investigates the cultist.',
    ],
    [
        'Ritual progress',
        'The public countdown to a cult victory, measured in steps. When the track fills, the ritual is complete. The exact goal is shown beside the track.',
    ],
    [
        'Banishment',
        'Removal from the living village after a vote. Banished players watch until the match ends and keep their seat for the next match.',
    ],
    [
        'Abstain',
        'Vote to banish nobody. Abstentions are counted together as an option alongside each player. A tie for most votes, or abstention receiving the most votes, means nobody is banished. Missing the vote deadline counts as abstaining.',
    ],
    [
        'Ready for voting',
        'A public, final signal that you have finished discussing. When every living player is ready, voting starts early. Otherwise it starts when the timer ends.',
    ],
];
</script>
<template>
    <details class="game-glossary rules-details">
        <summary>Village dictionary <span aria-hidden="true">+</span></summary>
        <p class="glossary-intro">Strange words. Simple meanings.</p>
        <dl>
            <div v-for="[term, meaning] in terms" :key="term">
                <dt>{{ term }}</dt>
                <dd>{{ meaning }}</dd>
            </div>
        </dl>
    </details>
</template>
