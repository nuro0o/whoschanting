<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { X, LockKeyhole } from '@lucide/vue';
import { eliminationLabel, roles, type RoomState } from '@/lib/chanting';
import CharacterPortrait from './CharacterPortrait.vue';
import SuspicionBoard from './SuspicionBoard.vue';
const props = defineProps<{
    state: RoomState;
    playerId: string | null;
    privateVisible: boolean;
}>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();
const player = computed(() =>
    props.state.players.find((p) => p.id === props.playerId),
);
const seat = computed(
    () => props.state.players.findIndex((p) => p.id === props.playerId) + 1,
);
const ready = computed(() =>
    props.state.phase === 'lobby'
        ? player.value?.ready
        : props.state.phase === 'discussion' && player.value?.discussion_ready,
);
const claims = computed(() => {
    if (
        props.state.phase !== 'finished' &&
        props.state.me.curse?.type === 'mist'
    )
        return [];
    return (props.state.table?.claims ?? []).filter(
        (c) => c.player_id === props.playerId,
    );
});
let returnFocus: HTMLElement | null = null;
function close() {
    emit('close');
}
function restoreFocus() {
    returnFocus?.focus();
    returnFocus = null;
}
watch([() => props.state.match_id, () => props.state.me.id], () =>
    emit('close'),
);
watch(
    () => player.value?.id,
    async (id) => {
        await nextTick();
        if (id && player.value) {
            if (!dialog.value?.open) {
                returnFocus =
                    document.activeElement instanceof HTMLElement
                        ? document.activeElement
                        : null;
                dialog.value?.showModal();
            }
        } else {
            dialog.value?.close();
            restoreFocus();
        }
    },
    { immediate: true },
);
onBeforeUnmount(restoreFocus);
</script>
<template>
    <Teleport to="body">
        <dialog
            ref="dialog"
            class="chanting player-drawer"
            aria-labelledby="player-drawer-title"
            @cancel.prevent="close"
            @click="$event.target === dialog && close()"
        >
            <div v-if="player" class="player-drawer-body">
                <header class="player-drawer-heading">
                    <CharacterPortrait
                        :character="player.character"
                        :creator="player.customization?.creator"
                        :frame="player.customization?.frame"
                        :accent="player.customization?.accent"
                        :background="player.customization?.background"
                        decorative
                    />
                    <div>
                        <p class="eyebrow">
                            VILLAGER DETAILS &middot; SEAT {{ seat }}
                        </p>
                        <h2 id="player-drawer-title">{{ player.name }}</h2>
                        <p>
                            {{
                                player.alive
                                    ? ready
                                        ? 'Ready'
                                        : 'Still at the table'
                                    : eliminationLabel(
                                          player.elimination_reason,
                                      )
                            }}<span v-if="player.id === state.me.id">
                                &middot; You</span
                            >
                        </p>
                    </div>
                    <button
                        type="button"
                        class="drawer-close"
                        aria-label="Close player details"
                        autofocus
                        @click="close"
                    >
                        <X :size="20" />
                    </button>
                </header>
                <p
                    v-if="state.phase === 'finished' && player.role"
                    class="drawer-public-role"
                >
                    Revealed role: {{ roles[player.role]?.name ?? player.role }}
                </p>
                <section v-if="claims.length" class="drawer-claims">
                    <h3>Public claims</h3>
                    <p>
                        What this player says about themselves. Claims are
                        unverified.
                    </p>
                    <p v-for="claim in claims" :key="claim.id">
                        <strong
                            >Day {{ claim.day }} &middot;
                            {{ roles[claim.role]?.name ?? claim.role }}</strong
                        ><br />{{ claim.body }}
                    </p>
                </section>
                <SuspicionBoard
                    v-if="
                        privateVisible &&
                        player.id !== state.me.id &&
                        state.phase !== 'lobby'
                    "
                    :state="state"
                    :visible="true"
                    :player-id="player.id"
                />
                <p
                    v-else-if="!privateVisible && state.phase !== 'lobby'"
                    class="drawer-private-lock"
                >
                    <LockKeyhole :size="16" /> Reveal your private information
                    and clear any blocking curse to view notes and clues.
                </p>
                <p v-else class="drawer-private-lock">
                    {{
                        player.id === state.me.id
                            ? 'Your role and results are in your private journal.'
                            : 'Your private notes open when the match begins.'
                    }}
                </p>
            </div>
        </dialog>
    </Teleport>
</template>
<style scoped>
.player-drawer {
    position: fixed;
    inset: 0 0 0 auto;
    margin: 0;
    width: min(580px, 100vw);
    max-width: 100vw;
    height: 100dvh;
    max-height: 100dvh;
    padding: 0;
    border: 0;
    border-left: 1px solid #45635f;
    color: #eee7d6;
    background: #142724;
    overscroll-behavior: contain;
}
.player-drawer::backdrop {
    background: #051311b8;
}
.player-drawer-body {
    padding: 24px 22px max(30px, env(safe-area-inset-bottom));
}
.player-drawer-heading {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-bottom: 20px;
    border-bottom: 1px solid #ffffff26;
}
.player-drawer-heading :deep(.character-portrait) {
    width: 56px;
    height: 64px;
    flex-shrink: 0;
}
.player-drawer-heading h2 {
    font:
        28px 'Fraunces',
        Georgia,
        serif;
    margin: 4px 0;
    overflow-wrap: anywhere;
}
.player-drawer-heading p {
    font-size: 14px;
    color: #c6d7cf;
}
.drawer-close {
    align-self: flex-start;
    margin-left: auto;
    min-width: 44px;
    min-height: 44px;
    display: grid;
    place-items: center;
    background: #ffffff10;
    border: 1px solid #ffffff30;
    border-radius: 8px;
}
.drawer-close:focus-visible {
    outline: 2px solid #dcecbc;
    outline-offset: 3px;
}
.drawer-claims {
    margin: 20px 0;
    border-bottom: 1px solid #ffffff26;
    padding-bottom: 14px;
}
.drawer-claims h3 {
    font:
        21px 'Fraunces',
        Georgia,
        serif;
}
.drawer-claims p,
.drawer-public-role,
.drawer-private-lock {
    font-size: 14px;
    line-height: 1.6;
    margin-top: 14px;
}
.drawer-private-lock {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    color: #c6d7cf;
}
</style>
