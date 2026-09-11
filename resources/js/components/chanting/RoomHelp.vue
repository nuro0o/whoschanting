<script setup lang="ts">
import { computed } from 'vue';
import { HelpCircle } from '@lucide/vue';
import GameGlossary from '@/components/chanting/GameGlossary.vue';
import RoleGuide from '@/components/chanting/RoleGuide.vue';
import type { RoomState } from '@/lib/chanting';
import { modeName } from '@/lib/gameModes';

const props = defineProps<{ state: RoomState }>();
defineEmits<{ play: []; 'reopen-hint': [] }>();
const nextAction = computed(() => {
    const { phase, me } = props.state;
    if (phase === 'finished')
        return 'Review the table and match recap in Play. The host can choose Play again.';
    if (phase === 'lobby')
        return 'Use Invite friends in Play to share this room. Everyone chooses Ready; the host then chooses Start game.';
    if (!me.alive)
        return 'You are watching until the next match. Read public events and chat in the meantime.';
    if (me.curse?.type === 'puzzle' || me.curse?.type === 'mist')
        return 'Complete the curse challenge to return to the room. The phase timer keeps running; the curse also expires at the next dawn.';
    if (me.submitted)
        return 'Your choice is submitted. Wait for the next phase; during discussion and voting you can still use Chat.';
    if (props.state.ritual.final_vote)
        return 'The ritual is full. Use this final discussion and vote to banish every remaining cultist. If any cultist survives the vote, the cult wins immediately.';
    if (phase === 'reveal')
        return 'Open My role and reveal your secrets. Read your objective and ability, then return to Play and choose Ready for night.';
    if (phase === 'night')
        return 'In Play, reveal your private action. Choose a target if your ability needs one, then confirm before the timer ends.';
    if (phase === 'discussion')
        return 'Open Chat to compare stories with the village. Choose Ready for voting in Play when you have finished discussing.';
    return 'In Play, choose a living player or Abstain, then press the confirmation button. Your submitted vote is final.';
});
</script>

<template>
    <div class="game-panel room-help-panel">
        <p class="eyebrow"><HelpCircle :size="15" /> HELP AT THE TABLE</p>
        <h2>A little less mystery.</h2>
        <section class="help-answer">
            <h3>What do I do now?</h3>
            <p>{{ nextAction }}</p>
            <button class="button" @click="$emit('play')">Go to Play</button>
        </section>
        <section class="help-answer">
            <h3>
                This room’s mode: {{ modeName(state.mode_setup, state.roster) }}
            </h3>
            <p>
                The host can open Modes in the lobby and apply settings before
                everyone readies up. Classic keeps the familiar rules; Hard adds
                Tracker and Herbalist. Chaos can repeat roles, and Maelstrom
                announces a different rule each night.
            </p>
            <p>
                Custom uses the exact enabled role counts, with at least one
                Town and one Cult player. Everyone can join before the match
                starts; the number of players must then match the chosen cast.
            </p>
        </section>
        <RoleGuide
            compact
            :minimum-players="{
                ...state.rules.town_roles_min_players,
                ...state.rules.cult_roles_min_players,
            }"
        />
        <section class="help-answer">
            <h3>How do we win?</h3>
            <p>
                <strong>Town:</strong> find and banish every cultist. When the
                ritual fills, you still get one final discussion and vote.
            </p>
            <p>
                <strong>Cult:</strong> fill the ritual and have at least one
                cultist survive the final vote, eliminate every town player, or
                reach a final pair of one cultist and one town player.
            </p>
            <p>
                Your private objective and any shared mission are in My role.
                Ritual progress is public in Play.
            </p>
        </section>
        <section class="help-answer">
            <h3>Why can’t I select someone?</h3>
            <p>
                Targets are available during voting and for night abilities that
                use a target. You must be alive, and you cannot select yourself
                or a banished player.
            </p>
            <p>
                Reveal your secrets to use a private night ability. A submitted
                choice is final. Soul Bind and Mind Mist must be cleared before
                you can return to the room. The Medium instead targets a
                banished player when using their ability at night.
            </p>
            <p>
                Selecting a player never submits automatically. Use the
                confirmation button when you are ready.
            </p>
            <p>
                The Warden cannot protect the same player on consecutive nights.
                That player is unavailable as a protection target until the
                following night; the Warden may also skip protection.
            </p>
            <button class="quiet-link" @click="$emit('reopen-hint')">
                Show the selection hint again
            </button>
        </section>
        <details class="help-rules rules-details">
            <summary>
                Rules & phase order <span aria-hidden="true">+</span>
            </summary>
            <ol>
                <li>
                    <strong>Read your role.</strong> Your role is secret and
                    separate from your character’s appearance. Read your
                    objective, then get ready.
                </li>
                <li>
                    <strong>Night.</strong> Everyone confirms one private
                    action. Cultists chant, the Oracle investigates, and the
                    Warden protects against new curses, the Lamplighter watches
                    for other visitors, the Tracker follows outgoing targets,
                    and townspeople keep watch. The Medium, Dreamweaver,
                    Bellkeeper, Herbalist, Phantasm and Counterfeiter can use
                    their once-per-match ability or save it. Disruptions resolve
                    first. A committed ability is spent even if disrupted.
                    Missing the deadline forfeits the action without spending a
                    saved ability.
                </li>
                <li>
                    <strong>Discussion.</strong> Read public events and any
                    private investigation, then compare stories in Chat. Voting
                    starts when all living players are ready or the timer ends.
                    The Exorcist can cleanse another player once per match; the
                    Oathkeeper can make one public voting promise each day.
                </li>
                <li>
                    <strong>Voting.</strong> Choose a player to banish or
                    abstain. The option with the most votes wins; ties or
                    abstention winning banish nobody. Missing the deadline
                    counts as abstention.
                </li>
                <li>
                    <strong>Repeat until a team wins.</strong> Banished players
                    watch, then keep their seats for the next game.
                </li>
            </ol>
            <p>
                This room needs {{ state.rules.min_players }}–{{
                    state.rules.max_players
                }}
                players. The ritual goal adjusts to the number of players and is
                shown in Play.
            </p>
        </details>
        <GameGlossary />
    </div>
</template>
