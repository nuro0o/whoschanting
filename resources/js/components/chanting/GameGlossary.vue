<script setup lang="ts">
const terms = [
    [
        'Modes',
        'Classic preserves the original roles, with an optional Illusions variant. Hard adds a wider cast from 5 players. Chaos draws random roles with possible repeats. Custom uses exactly the role counts chosen by the host; the total must match the players at the start.',
    ],
    [
        'Maelstrom',
        'A Chaos variant with one public rule each night: Mirrors reverses base Oracle readings before veils and forgeries; Sanctuary stops all new curses; Eclipse hides visits from Lamplighters and Trackers. The rule is announced to everyone.',
    ],
    [
        'Tracker',
        'A town role. Follows another living player each night and privately learns whom they targeted, or that no outgoing visit was visible. Disrupted attempts count; Phantasm concealment and Eclipse hide tracks. No role or ability is revealed.',
    ],
    [
        'Herbalist',
        'A town role. Once per match at night, protects all living players from new curses without selecting a target. It does not remove existing curses or stop haunting, forgery or disruption. A quiet night or disrupted attempt still spends the ability.',
    ],
    [
        'Phantasm',
        'A cult role. Once, gives up chanting to haunt another player through discussion and hide their outgoing visits from the Lamplighter that night. False faces and sounds reveal no allegiance; names and controls stay truthful.',
    ],
    [
        'Counterfeiter',
        'A cult role. Once, gives up chanting to choose how another player appears to the Oracle tonight. The forgery overrides a veil, but never changes true roles or Medium results.',
    ],
    [
        'Exorcist',
        'A town role. Once during discussion, clears another living player’s active curse and haunting. It is spent even if the player was unaffected and gives no future protection.',
    ],
    [
        'Oathkeeper',
        'A town role. Once each discussion, publicly promises a vote. Keeping that promise with the actual ballot protects against new curses next night. Abstaining, missing the vote or voting elsewhere earns no protection.',
    ],
    [
        'Medium',
        'A town role. Once per match, contacts a banished player at night and privately learns their true alignment at dawn. Veils cannot change it. Keeping watch saves the ability; a disrupted attempt spends it.',
    ],
    [
        'Dreamweaver',
        'A cult role. Chants without a target, or once per match replaces chanting with a disruption of another living player. Disruptions resolve before other night actions and bypass Warden protection. The target privately learns a submitted action failed. A disruption also breaks a mission requiring every cultist to chant.',
    ],
    [
        'Bellkeeper',
        'A town role. Once per match, prevents one ritual step earned that night. Existing progress stays unchanged. Ringing on a quiet night or being disrupted spends the ability. Keeping watch saves it.',
    ],
    [
        'Disrupted',
        'Your submitted night action had no effect. Any committed once-per-match ability is still spent. The Lamplighter can still detect your attempted visit. Simultaneous Dreamweaver disruptions resolve together before all other actions.',
    ],
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
        'The hidden team working together to fill the ritual and survive the final vote. They also win if no town players remain, or if the final two players are one cultist and one town player.',
    ],
    [
        'Oracle',
        'A town role. Once per match, investigates another living player and privately learns their apparent alignment at dawn. Can keep watch to save the investigation. It is spent on submission, even if disrupted.',
    ],
    [
        'Townsperson',
        'A town role without a special night ability. Keeps watch, compares stories, and helps identify cultists through discussion and voting.',
    ],
    [
        'Veilweaver',
        'A cult role. Can chant while veiling and cursing one other living player. The veil reverses their apparent alignment for the Oracle tonight; their chosen curse takes hold at dawn.',
    ],
    [
        'Warden',
        'A town role. Protects another living player from all new curses for one night, or skips protection. Cannot protect the same person on consecutive nights. Does not remove existing curses or stop veils, investigations, or chanting.',
    ],
    [
        'Lamplighter',
        'A town role, separate from the cosmetic character of the same name. Watches another living player and privately learns whether anyone else targeted them that night. Their own watch is excluded; blocked curse attempts still count. No visitor identity, role, or ability is revealed.',
    ],
    [
        'Acolyte',
        'A cult role. Chants to advance the ritual when the shared mission condition is met, and can optionally curse one other living player. Their chosen curse takes hold at dawn.',
    ],
    [
        'Veiled',
        'Disguised for one night: the Oracle reads a town player as cult, or a cult player as town. The player’s real side stays the same. Your own role card always tells the truth.',
    ],
    [
        'Eldritch curse',
        'A private affliction placed by a cultist. It takes hold at dawn and expires at the following dawn. The caster chooses Soul Bind, Mind Mist, or Misdirection (unlocked at ritual level 3). Every new curse has three seals. Higher ritual levels add more pieces and closer tower heights; lanterns have random numbers and decoys without next-step hints. Cleared seals stay cleared across refreshes. Being cursed does not reveal your alignment.',
    ],
    [
        'Soul Bind',
        'Break a series of seals by rotating rune rings toward the north beacon or waking stone towers from shortest to tallest. Early seals have fewer objects and obvious differences; higher ritual levels bring more complex mechanisms. Clear every seal to return to the room; the phase timer continues. Keyboard controls are available.',
    ],
    [
        'Mind Mist',
        'Blurs the village scenery and turns chat into gibberish. Light lanterns in numerical order, not height order. Level 1 uses just 1, 2, 3 with a next-lantern hint and forgiving clicks. Higher levels add more numbers, word decoys, and thicker mist. Clear every seal to restore the room and chat; the phase timer continues.',
    ],
    [
        'Misdirection',
        'Only appears at ritual level 3, from two-thirds progress. Your next chosen night or vote target changes once to another legal player unless you first solve its rune rings. You can keep playing or open the optional puzzle. The Medium can only be redirected to another banished player. Abstaining does not trigger it. It fades at the following dawn.',
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
        'In Classic, a match with 3 or 4 players has one cultist, one Oracle, and the remaining players are townspeople. Chaos and Custom can use other roles. The ritual takes 3 steps with 3 players, or 4 steps with 4. A submitted chant adds one step even when the Oracle investigates the cultist.',
    ],
    [
        'Ritual progress',
        'The public countdown to the summoning, measured in steps. When the track fills, the village gets one final discussion and vote. Town wins by banishing every remaining cultist; otherwise the cult wins when that vote resolves. The exact goal is shown beside the track.',
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
        <summary>Glossary <span aria-hidden="true">+</span></summary>
        <p class="glossary-intro">Strange words. Simple meanings.</p>
        <dl tabindex="0" aria-label="Village dictionary definitions">
            <div v-for="[term, meaning] in terms" :key="term">
                <dt>{{ term }}</dt>
                <dd>{{ meaning }}</dd>
            </div>
        </dl>
    </details>
</template>
