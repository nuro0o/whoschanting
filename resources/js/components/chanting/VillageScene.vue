<script setup lang="ts">
import { t } from '@/i18n';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const canvas = ref<HTMLElement>();
let game: { destroy: (removeCanvas: boolean) => void } | undefined;
let disposed = false;
onMounted(async () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    try {
        const Phaser = await import('phaser');
        if (disposed || !canvas.value) return;
        const host = canvas.value;
        class MistScene extends Phaser.Scene {
            create() {
                const width = this.scale.width;
                const height = this.scale.height;
                for (let i = 0; i < 18; i++) {
                    const mote = this.add.circle(
                        Math.random() * width,
                        Math.random() * height,
                        1 + Math.random(),
                        0xe2dfa5,
                        0.18 + Math.random() * 0.25,
                    );
                    this.tweens.add({
                        targets: mote,
                        y: mote.y - 80,
                        x: mote.x + 25,
                        alpha: 0,
                        duration: 4000 + Math.random() * 5000,
                        delay: Math.random() * 3000,
                        repeat: -1,
                        yoyo: true,
                    });
                }
            }
        }
        game = new Phaser.Game({
            type: Phaser.CANVAS,
            parent: host,
            transparent: true,
            width: host.clientWidth,
            height: host.clientHeight,
            scene: MistScene,
            audio: { noAudio: true },
            banner: false,
        });
    } catch {
        /* The illustration remains fully usable without the optional animation. */
    }
});
onBeforeUnmount(() => {
    disposed = true;
    game?.destroy(true);
});
</script>

<template>
    <div class="village-scene">
        <img
            src="/assets/chanting/village.png"
            :alt="t('villageScene.description')"
            width="1536"
            height="1024"
            fetchpriority="high"
        />
        <div ref="canvas" class="village-particles" aria-hidden="true"></div>
    </div>
</template>
