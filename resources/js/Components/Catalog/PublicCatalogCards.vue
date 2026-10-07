<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({ items: { type: Array, default: () => [] } })
</script>

<template>
    <div class="catalog-cards">
        <Link v-for="item in items" :key="item.id" :href="item.public_url" class="catalog-card">
            <div class="catalog-card__image">
                <img v-if="item.image" :src="item.image" :alt="item.name" loading="lazy">
                <v-icon v-else icon="mdi-package-variant-closed" size="40" />
            </div>
            <span class="catalog-card__name">{{ item.name }}</span>
            <v-icon icon="mdi-arrow-top-right" size="19" class="catalog-card__arrow" />
        </Link>
    </div>
</template>

<style scoped>
.catalog-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 18px; }
.catalog-card { position: relative; display: flex; flex-direction: column; overflow: hidden; border: 1px solid #dce6e1; border-radius: 20px; background: white; color: #173e32; text-decoration: none; transition: box-shadow .18s, transform .18s; }
.catalog-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px #173e3212; }
.catalog-card:focus-visible { outline: 3px solid #168865; outline-offset: 3px; }
.catalog-card__image { display: grid; place-items: center; height: 170px; background: #f1f6f3; color: #81a798; }
.catalog-card__image img { width: 100%; height: 100%; object-fit: cover; }
.catalog-card__name { padding: 18px 42px 20px 18px; font-size: 16px; font-weight: 650; line-height: 1.45; overflow-wrap: anywhere; }
.catalog-card__arrow { position: absolute; bottom: 23px; right: 17px; }
</style>
