<script setup>
const counterId = import.meta.env.VITE_YANDEX_METRICA_COUNTER_ID;
</script>

<template>
    <component
        :is="'script'"
        v-if="counterId"
        v-html="`
            (function(m,e,t,r,i,k,a){
                m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                m[i].l=1*new Date();
                for (var j = 0; j < document.scripts.length; j++) {
                    if (document.scripts[j].src === r) { return; }
                }
                k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
            })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');

            ym(${counterId}, 'init', {
                clickmap: true,
                trackLinks: true,
                accurateTrackBounce: true,
                webvisor: true,
                ecommerce: 'dataLayer'
            });
        `"
    />

    <noscript v-if="counterId">
        <div>
            <img
                :src="`https://mc.yandex.ru/watch/${counterId}`"
                style="position:absolute; left:-9999px;"
                alt=""
            >
        </div>
    </noscript>
</template>
