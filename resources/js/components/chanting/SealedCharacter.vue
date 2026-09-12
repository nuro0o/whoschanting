<template>
    <span class="sealed-character" aria-hidden="true"><span>?</span></span>
</template>

<style scoped>
.sealed-character {
    display: grid;
    place-items: center;
    aspect-ratio: 1;
    min-width: 0;
    border: 1px solid #8f96736b;
    background:
        repeating-linear-gradient(135deg, #b3c59b06 0 1px, transparent 1px 9px),
        #1d302e;
    color: #d4c395;
    box-shadow:
        inset 0 0 0 5px #1d302e,
        inset 0 0 0 6px #8f967335;
}
.sealed-character > span {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 2.4em;
    font-weight: 400;
    line-height: 1;
}
</style>
