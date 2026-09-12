<script setup lang="ts">
import { t } from '@/i18n';
const terms = [
    [t('glossary.modes.term'), t('glossary.modes.description')],
    [t('glossary.maelstrom.term'), t('glossary.maelstrom.description')],
    [t('glossary.tracker.term'), t('glossary.tracker.description')],
    [t('glossary.herbalist.term'), t('glossary.herbalist.description')],
    [t('glossary.phantasm.term'), t('glossary.phantasm.description')],
    [t('glossary.counterfeiter.term'), t('glossary.counterfeiter.description')],
    [t('glossary.exorcist.term'), t('glossary.exorcist.description')],
    [t('glossary.oathkeeper.term'), t('glossary.oathkeeper.description')],
    [t('glossary.medium.term'), t('glossary.medium.description')],
    [t('glossary.dreamweaver.term'), t('glossary.dreamweaver.description')],
    [t('glossary.bellkeeper.term'), t('glossary.bellkeeper.description')],
    [t('glossary.disrupted.term'), t('glossary.disrupted.description')],
    [t('glossary.role.term'), t('glossary.role.description')],
    [t('glossary.phase.term'), t('glossary.phase.description')],
    [t('glossary.alignment.term'), t('glossary.alignment.description')],
    [t('glossary.town.term'), t('glossary.town.description')],
    [t('glossary.cult.term'), t('glossary.cult.description')],
    [t('glossary.oracle.term'), t('glossary.oracle.description')],
    [t('glossary.townsperson.term'), t('glossary.townsperson.description')],
    [t('glossary.veilweaver.term'), t('glossary.veilweaver.description')],
    [t('glossary.warden.term'), t('glossary.warden.description')],
    [t('glossary.lamplighter.term'), t('glossary.lamplighter.description')],
    [t('glossary.acolyte.term'), t('glossary.acolyte.description')],
    [t('glossary.veiled.term'), t('glossary.veiled.description')],
    [
        t('glossary.eldritch_curse.term'),
        t('glossary.eldritch_curse.description'),
    ],
    [t('glossary.soul_bind.term'), t('glossary.soul_bind.description')],
    [t('glossary.mind_mist.term'), t('glossary.mind_mist.description')],
    [t('glossary.misdirection.term'), t('glossary.misdirection.description')],
    [t('glossary.chant.term'), t('glossary.chant.description')],
    [
        t('glossary.shared_mission.term'),
        t('glossary.shared_mission.description'),
    ],
    [
        t('glossary.small_gathering.term'),
        t('glossary.small_gathering.description'),
    ],
    [
        t('glossary.ritual_progress.term'),
        t('glossary.ritual_progress.description'),
    ],
    [t('glossary.banishment.term'), t('glossary.banishment.description')],
    [t('glossary.abstain.term'), t('glossary.abstain.description')],
    [
        t('glossary.ready_for_voting.term'),
        t('glossary.ready_for_voting.description'),
    ],
];
</script>
<template>
    <details class="game-glossary rules-details">
        <summary>
            {{ t('glossary.title') }} <span aria-hidden="true">+</span>
        </summary>
        <p class="glossary-intro">{{ t('glossary.intro') }}</p>
        <dl tabindex="0" :aria-label="t('glossary.label')">
            <div v-for="[term, meaning] in terms" :key="term">
                <dt>{{ term }}</dt>
                <dd>{{ meaning }}</dd>
            </div>
        </dl>
    </details>
</template>
