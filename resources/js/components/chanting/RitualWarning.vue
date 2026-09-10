<template>
    <section class="ritual-warning" role="alert" aria-atomic="true">
        <strong>The ritual is complete. One final vote remains.</strong>
        <p>
            The cult is preparing to summon. The whispers in your head are
            getting stronger. Banish every remaining cultist in this final
            vote—or the summoning begins.
        </p>
    </section>
</template>

<style scoped>
.ritual-warning {
    grid-column: 1 / -1;
    border: 1px solid #d7787f;
    border-left: 4px solid #ff9c9c;
    border-radius: 5px;
    background: #321c23;
    padding: 14px 18px;
    color: #ffc5c5;
    font-size: 14px;
    line-height: 1.6;
}
.ritual-warning strong {
    display: block;
    color: #ffaaaa;
}
.ritual-warning p {
    margin: 4px 0 0;
    color: inherit;
    font-size: inherit;
    line-height: inherit;
}
@media (max-width: 760px) {
    .ritual-warning {
        padding: 12px 14px;
        font-size: 13px;
    }
}
</style>
