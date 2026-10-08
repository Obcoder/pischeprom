<script setup>
defineProps({ title: { type: String, required: true }, hero: { type: Object, required: true } })
</script>

<template>
    <section class="hero wrap" aria-labelledby="hero-title">
        <div class="hero-copy">
            <div class="eyebrow light"><span class="small-line" /> ТОВАРНЫЙ ГИД</div>
            <h1 id="hero-title">{{ title }}</h1>
            <p class="hero-serif"><template v-for="(line, index) in hero.subtitle" :key="line"><br v-if="index">{{ line }}</template></p>
            <p class="hero-description"><template v-for="(line, index) in hero.description" :key="line"><br v-if="index" :class="{ 'desktop-break': index === 2 }">{{ line }}{{ index < hero.description.length - 1 ? ' ' : '' }}</template></p>
            <div class="hero-actions"><a class="button lime" href="#catalog">{{ hero.action }}</a><a class="hero-text-link" href="#guide">Читать гид</a></div>
            <div class="hero-audiences">РОЗНИЦА <span>·</span> HoReCa <span>·</span> ПЕРЕРАБОТКА</div>
        </div>
        <figure class="hero-visual">
            <img :src="hero.image" :alt="hero.imageAlt" :width="hero.imageWidth" :height="hero.imageHeight" fetchpriority="high">
            <div class="photo-index" aria-hidden="true">01 / РЫБА</div>
            <figcaption><span>{{ hero.caption }}</span><i>{{ hero.scientificName }}</i></figcaption>
        </figure>
    </section>
</template>
