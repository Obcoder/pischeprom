<script setup>
defineProps({ guide: { type: Object, required: true } })
</script>

<template>
    <section id="sources" class="wrap sources-section">
        <details>
            <summary>Источники и фотографии<span>Проверено {{ guide.verifiedOn }}</span><b aria-hidden="true">+</b></summary>
            <div class="sources-body">
                <p>Материал помогает сравнить товары; характеристики конкретной партии определяются её маркировкой и документами.</p>
                <ol class="source-list">
                    <li v-for="source in guide.sources" :id="source.id" :key="source.id"><a :href="source.url" target="_blank" rel="noopener noreferrer">{{ source.title }}</a> — {{ source.description }}</li>
                </ol>
                <div id="photo-credits" class="photo-credits">
                    <h3>Фотографии</h3>
                    <p>Фотографии иллюстрируют класс продукта и пример приготовления; это не снимки поставляемых партий.</p>
                    <p v-for="photo in guide.photos" :key="photo.source">{{ photo.title }} — <a :href="photo.source" target="_blank" rel="noopener noreferrer">{{ photo.author }} / Wikimedia Commons</a>, <a :href="photo.licenseUrl" target="_blank" rel="noopener noreferrer">{{ photo.license }}</a>.</p>
                    <p>Фотографии уменьшены и сжаты для сайта, отображение адаптировано кадрированием; права на фотографии сохраняются за авторами. Адаптации распространяются на условиях соответствующих лицензий.</p>
                </div>
            </div>
        </details>
    </section>
</template>
