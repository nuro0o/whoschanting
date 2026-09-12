<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Copy, QrCode, Share2 } from '@lucide/vue';
import QRCode from 'qrcode';
const props = defineProps<{ url: string; code: string }>();
const qr = ref('');
const showQr = ref(false);
const message = ref('');
const qrError = ref(false);
const reachable = computed(() => {
    try {
        return !['localhost', '127.0.0.1', '[::1]'].includes(
            new URL(props.url).hostname,
        );
    } catch {
        return false;
    }
});
watch(
    () => props.url,
    async (url) => {
        qr.value = '';
        qrError.value = false;
        try {
            const result = await QRCode.toDataURL(url, {
                width: 256,
                margin: 4,
                errorCorrectionLevel: 'M',
                color: { dark: '#112b26', light: '#ffffff' },
            });
            if (props.url === url) qr.value = result;
        } catch {
            if (props.url === url) qrError.value = true;
        }
    },
    { immediate: true },
);
async function copy() {
    try {
        await navigator.clipboard.writeText(props.url);
        message.value = 'Room link copied.';
    } catch {
        message.value = 'Select and copy the room link below.';
    }
}
async function share() {
    if (!navigator.share) {
        await copy();
        return;
    }
    try {
        await navigator.share({
            title: "Join Who's Chanting",
            text: `Join room ${props.code}`,
            url: props.url,
        });
        message.value = 'Invite shared.';
    } catch (error) {
        if (!(error instanceof Error && error.name === 'AbortError'))
            await copy();
    }
}
</script>
<template>
    <section class="lobby-invites" aria-label="Invite friends">
        <div class="invite-buttons">
            <button type="button" @click="share">
                <Share2 :size="16" /> Share invite
            </button>
            <button type="button" @click="copy">
                <Copy :size="16" /> Copy link
            </button>
            <button
                type="button"
                :aria-expanded="showQr"
                aria-controls="invite-qr"
                @click="showQr = !showQr"
            >
                <QrCode :size="16" /> {{ showQr ? 'Hide QR' : 'Scan to join' }}
            </button>
        </div>
        <div v-if="showQr" id="invite-qr" class="invite-qr">
            <img
                v-if="qr"
                :src="qr"
                alt="QR code for this room invitation link"
                width="256"
                height="256"
            />
            <p v-else role="status">
                {{
                    qrError
                        ? 'QR code unavailable. Use the room link below.'
                        : 'Preparing QR code...'
                }}
            </p>
            <p>
                Scan with your phone camera to join room
                <strong>{{ code }}</strong
                >.
            </p>
        </div>
        <label class="invite-link"
            >Room link<input
                readonly
                :value="url"
                aria-label="Room invitation link"
                @focus="($event.target as HTMLInputElement).select()"
        /></label>
        <p v-if="!reachable" class="invite-note">
            Use a reachable room address when inviting another device.
        </p>
        <p v-if="message" role="status" class="invite-note">{{ message }}</p>
    </section>
</template>
<style scoped>
.lobby-invites {
    margin-top: 16px;
}
.invite-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.invite-buttons button {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    border: 1px solid #ffffff30;
    border-radius: 6px;
    padding: 10px 12px;
    color: #e6e9d7;
    background: #ffffff08;
    font-size: 14px;
}
.invite-buttons button:hover {
    background: #ffffff15;
}
.invite-buttons button:focus-visible,
.invite-link input:focus-visible {
    outline: 2px solid #dcecbc;
    outline-offset: 3px;
}
.invite-qr {
    display: grid;
    justify-items: center;
    text-align: center;
    gap: 12px;
    padding: 20px;
    margin-top: 12px;
    border: 1px solid #ffffff20;
}
.invite-qr img {
    width: 224px;
    max-width: 100%;
    height: auto;
    border-radius: 6px;
}
.invite-qr p,
.invite-note {
    color: #c8d6cb;
    font-size: 13px;
    line-height: 1.5;
}
.invite-link {
    display: grid;
    gap: 6px;
    margin-top: 14px;
    font-size: 13px;
    color: #c8d6cb;
}
.invite-link input {
    width: 100%;
    min-width: 0;
    color: #f1eddb;
    background: #0b211d;
    border: 1px solid #ffffff25;
    border-radius: 5px;
    padding: 10px;
    font-size: 14px;
}
.invite-note {
    margin-top: 8px;
}
</style>
