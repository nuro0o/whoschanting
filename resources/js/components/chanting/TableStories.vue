<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { roles, type RoomState } from '@/lib/chanting';

const props = defineProps<{
    state: RoomState;
    pending: boolean;
    blocked: boolean;
    mist: boolean;
    error: string;
    submit: (type: string, extra?: object) => Promise<boolean>;
}>();
const role = ref('');
const claimBody = ref('');
const claimTarget = ref('');
const responseBody = ref('');
const responseTarget = ref('');
const failure = ref('');
const table = computed(() => props.state.table);
const canWrite = computed(
    () => props.state.phase === 'discussion' && props.state.me.alive,
);
const claimed = computed(() =>
    table.value?.claims.some(
        (entry) =>
            entry.player_id === props.state.me.id &&
            entry.day === props.state.day,
    ),
);
const responded = computed(() =>
    table.value?.responses.some(
        (entry) =>
            entry.player_id === props.state.me.id &&
            entry.day === props.state.day,
    ),
);
const targets = computed(() =>
    props.state.players.filter((player) => player.id !== props.state.me.id),
);
const entries = computed(() =>
    [
        ...(table.value?.claims ?? []).map((entry) => ({
            ...entry,
            kind: 'claim' as const,
        })),
        ...(table.value?.responses ?? []).map((entry) => ({
            ...entry,
            kind: 'response' as const,
        })),
    ].sort((a, b) => b.day - a.day),
);
const living = computed(() =>
    props.state.players.filter((player) => player.alive),
);
const votes = computed(
    () =>
        table.value?.extension.voter_ids.filter((id) =>
            living.value.some((player) => player.id === id),
        ) ?? [],
);
function name(id: string) {
    return (
        props.state.players.find((player) => player.id === id)?.name ??
        'A former villager'
    );
}
watch(
    () => `${props.state.match_id}:${props.state.day}`,
    () => {
        role.value = '';
        claimBody.value = '';
        claimTarget.value = '';
        responseBody.value = '';
        responseTarget.value = '';
        failure.value = '';
    },
);
async function publish(kind: 'claim' | 'discussion_response') {
    if (props.pending || props.blocked || !canWrite.value) return;
    failure.value = '';
    const claim = kind === 'claim';
    const body = (claim ? claimBody.value : responseBody.value).trim();
    if (!body || (claim && !role.value)) return;
    const ok = await props.submit(kind, {
        body,
        target: (claim ? claimTarget.value : responseTarget.value) || null,
        ...(claim ? { role: role.value } : {}),
    });
    if (!ok)
        failure.value =
            props.error || 'Your story was not saved. Please try again.';
}
async function extend() {
    failure.value = '';
    if (!(await props.submit('extend_discussion')))
        failure.value =
            props.error || 'Your agreement was not saved. Please try again.';
}
</script>

<template>
    <section
        v-if="table"
        class="game-panel table-experience"
        aria-labelledby="table-stories-title"
    >
        <div class="panel-title">
            <h2 id="table-stories-title">The village record</h2>
            <span>Stories, not proof</span>
        </div>
        <p class="table-note">
            Compare what people say across rounds. Every claim and answer comes
            from a player; the village does not verify them.
        </p>
        <p v-if="mist" class="table-notice" role="status">
            Mind Mist hides the record. Break the curse to read and share
            stories.
        </p>
        <template v-else>
            <div v-if="canWrite && table.prompt" class="table-prompt">
                <span class="table-eyebrow"
                    >A question for everyone · Day {{ state.day }}</span
                >
                <h3>{{ table.prompt.question }}</h3>
                <p class="table-note">
                    Any role can answer, and anyone can bluff. Share one answer
                    today; it stays in the record.
                </p>
                <p v-if="responded" class="table-notice" role="status">
                    Your answer is in the record below.
                </p>
                <form
                    v-else
                    class="table-form"
                    @submit.prevent="publish('discussion_response')"
                >
                    <label for="discussion-response"
                        >Your answer<textarea
                            id="discussion-response"
                            v-model="responseBody"
                            rows="2"
                            maxlength="280"
                            required
                            :disabled="pending || blocked"
                        />
                    </label>
                    <div class="table-form-row">
                        <label for="response-target"
                            >About someone?
                            <span class="table-note">Optional</span
                            ><select
                                id="response-target"
                                v-model="responseTarget"
                                :disabled="pending || blocked"
                            >
                                <option value="">No specific player</option>
                                <option
                                    v-for="player in targets"
                                    :key="player.id"
                                    :value="player.id"
                                >
                                    {{ player.name }}
                                </option>
                            </select></label
                        >
                        <button
                            class="button"
                            :disabled="
                                pending || blocked || !responseBody.trim()
                            "
                        >
                            {{ pending ? 'Please wait…' : 'Publish my answer' }}
                        </button>
                    </div>
                </form>
            </div>
            <details
                v-if="canWrite"
                class="table-compose"
                :open="!claimed && !entries.length"
            >
                <summary>
                    {{
                        claimed
                            ? 'Your role claim is recorded for today'
                            : 'Put your role claim on the record'
                    }}
                </summary>
                <p class="table-note">
                    One claim per day. Once published, it cannot be edited or
                    removed.
                </p>
                <form
                    v-if="!claimed"
                    class="table-form"
                    @submit.prevent="publish('claim')"
                >
                    <div class="table-form-row">
                        <label for="claim-role"
                            >The role you claim<select
                                id="claim-role"
                                v-model="role"
                                required
                                :disabled="pending || blocked"
                            >
                                <option disabled value="">Choose a role</option>
                                <option
                                    v-for="(details, id) in roles"
                                    :key="id"
                                    :value="id"
                                >
                                    {{ details.name }}
                                </option>
                            </select></label
                        >
                        <label for="claim-target"
                            >About someone?
                            <span class="table-note">Optional</span
                            ><select
                                id="claim-target"
                                v-model="claimTarget"
                                :disabled="pending || blocked"
                            >
                                <option value="">No specific player</option>
                                <option
                                    v-for="player in targets"
                                    :key="player.id"
                                    :value="player.id"
                                >
                                    {{ player.name }}
                                </option>
                            </select></label
                        >
                    </div>
                    <label for="claim-body"
                        >What do you want the village to know?<textarea
                            id="claim-body"
                            v-model="claimBody"
                            rows="2"
                            maxlength="280"
                            required
                            :disabled="pending || blocked"
                            placeholder="I protected Mara last night…"
                        />
                    </label>
                    <div class="table-form-row">
                        <span class="table-note"
                            >{{ claimBody.length }}/280 · Public and permanent
                            for this match</span
                        ><button
                            class="button"
                            :disabled="
                                pending || blocked || !role || !claimBody.trim()
                            "
                        >
                            {{ pending ? 'Please wait…' : 'Publish my claim' }}
                        </button>
                    </div>
                </form>
            </details>
            <p v-if="blocked && canWrite" class="table-notice">
                Break your curse before adding to the record.
            </p>
            <ol v-if="entries.length" class="table-record">
                <li v-for="entry in entries" :key="`${entry.kind}-${entry.id}`">
                    <div class="table-entry-heading">
                        <strong>{{ name(entry.player_id) }}</strong
                        ><span
                            >Day {{ entry.day }} ·
                            {{
                                entry.kind === 'claim'
                                    ? 'Role claim'
                                    : 'Discussion answer'
                            }}</span
                        >
                    </div>
                    <p v-if="entry.kind === 'claim'" class="table-claimed-role">
                        Claims {{ roles[entry.role]?.name ?? entry.role }}
                    </p>
                    <p v-else-if="entry.question" class="table-note">
                        {{ entry.question }}
                    </p>
                    <blockquote>{{ entry.body }}</blockquote>
                    <p v-if="entry.target_id" class="table-note">
                        About {{ name(entry.target_id) }}
                    </p>
                </li>
            </ol>
            <p v-else class="table-empty">
                No stories on the record yet. Living players can add a claim or
                answer during discussion.
            </p>
        </template>
        <div v-if="state.phase === 'discussion'" class="table-extension">
            <div>
                <h3>A little more time?</h3>
                <p class="table-note">
                    {{
                        table.extension.used
                            ? 'The village added 30 seconds and reset readiness for voting. This discussion’s extension is used.'
                            : `${votes.length}/${living.length} agree. Everyone still alive must agree to add 30 seconds, once this discussion. When everyone agrees, readiness for voting resets to give the village more time.`
                    }}
                </p>
            </div>
            <button
                v-if="state.me.alive && !table.extension.used"
                class="button"
                :disabled="pending || blocked || votes.includes(state.me.id)"
                @click="extend"
            >
                {{
                    votes.includes(state.me.id)
                        ? 'You agreed'
                        : 'Agree to +30 seconds'
                }}
            </button>
        </div>
        <p v-if="failure" class="table-error" role="alert">{{ failure }}</p>
    </section>
</template>
